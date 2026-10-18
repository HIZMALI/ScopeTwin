<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ReplayRun;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Explanatory sidecar. Never invokes the evaluator or mutates domain records. */
final class ReplayAdvisor
{
    private const URL = 'https://app.backboard.io/api/threads';

    private const VERSION = 'aggregate-advisor-v1';

    private const PROMPT = 'Explain this simulated historical policy replay in two or three short English sentences. Return only a JSON object with explanation (string) and questions (array of at most two short human review questions). Use no numerals, identifiers, personal data, URLs, HTML or markdown. Counts are displayed separately by the deterministic engine. Do not infer causes, prevented losses, savings or legal/compliance guarantees. Do not recommend authorization decisions, policy edits, approvals, rejections or transactions. Do not use tools. Describe uncertainty and suggest questions for a human reviewer only. The simulation does not execute actions.';

    public function state(): string
    {
        if (! config('backboard.enabled')) {
            return 'disabled';
        }
        foreach (['api_key', 'llm_provider', 'model_name'] as $field) {
            $value = config('backboard.'.$field);
            if (! is_string($value) || trim($value) === '' || strlen($value) > 256 || preg_match('/[\x00-\x20\x7f]/', $value)) {
                return 'not_configured';
            }
        }

        return 'ready';
    }

    public function view(ReplayRun $run): array
    {
        return ['state' => $this->state(), 'fallback' => $this->fallback($run)];
    }

    private function fallback(ReplayRun $run): string
    {
        $count = (int) $run->sample_count;
        $changed = (int) ($run->summary['changed'] ?? 0);

        return "Deterministic summary: {$changed} of {$count} sampled decisions changed between the frozen baseline and candidate. This replay is simulated; no actions were executed.";
    }

    /** Fixed, bounded aggregate schema. No policy definitions, identifiers or raw samples. */
    public function summary(ReplayRun $run): array
    {
        $count = (int) $run->sample_count;
        $source = $run->summary;
        $transitions = [];
        foreach (['ALLOW', 'REVIEW', 'DENY'] as $before) {
            foreach (['ALLOW', 'REVIEW', 'DENY'] as $after) {
                $key = $before.'_'.$after;
                $value = $source['transitions'][$key] ?? 0;
                if (! is_int($value) || $value < 0 || $value > 500) {
                    throw new RuntimeException('Invalid aggregate.');
                }
                $transitions[$key] = $value;
            }
        }
        $unchanged = $transitions['ALLOW_ALLOW'] + $transitions['REVIEW_REVIEW'] + $transitions['DENY_DENY'];
        $denied = $transitions['ALLOW_DENY'] + $transitions['REVIEW_DENY'];
        $reviewed = $transitions['ALLOW_REVIEW'] + $transitions['DENY_REVIEW'];
        if ($run->status !== 'COMPLETED' || $count < 0 || $count > 500 || array_sum($transitions) !== $count
            || ($source['changed'] ?? null) !== $count - $unchanged || ($source['newly_denied'] ?? null) !== $denied
            || ($source['newly_reviewed'] ?? null) !== $reviewed) {
            throw new RuntimeException('Invalid aggregate.');
        }
        $summary = ['simulated' => true, 'sample_count' => $count, 'transitions' => $transitions,
            'changed' => $count - $unchanged, 'newly_denied' => $denied, 'newly_reviewed' => $reviewed, 'unchanged' => $unchanged];
        if (strlen(json_encode($summary, JSON_THROW_ON_ERROR)) > 2048) {
            throw new RuntimeException('Aggregate too large.');
        }

        return $summary;
    }

    public function advise(User $actor, string $id, array $input = []): array
    {
        $membership = OrganizationContext::membership($actor);
        $run = ReplayRun::forOrganization($membership->organization_id)->findOrFail($id);
        abort_unless(in_array($membership->role, ['OWNER', 'ADMIN'], true), 403);
        // Authorize scope first, then reject ALL submitted content/settings/selectors.
        abort_if($input !== [], 422, 'No input fields are accepted.');
        $base = ['fallback' => $this->fallback($run), 'cached' => false];
        if ($this->state() !== 'ready') {
            return $base + ['status' => $this->state()];
        }
        try {
            $summary = $this->summary($run);
        } catch (Throwable) {
            return $base + ['status' => 'unavailable'];
        }
        $cacheKey = hash('sha256', json_encode([$summary, $run->baseline_fingerprint, $run->candidate_fingerprint,
            $run->sample_manifest, config('backboard.llm_provider'), config('backboard.model_name'), self::VERSION], JSON_THROW_ON_ERROR));
        $reservation = $this->reserve($actor, $run->organization_id, $cacheKey);
        if ($reservation['status'] !== 'reserved') {
            return array_replace($base, $reservation);
        }

        $started = hrtime(true);
        $thread = null;
        $cleanup = false;
        $result = null;
        $class = 'invalid_response';
        try {
            $response = $this->client()->post(self::URL.'/messages', [
                'content' => json_encode($summary, JSON_THROW_ON_ERROR), 'system_prompt' => self::PROMPT,
                'llm_provider' => config('backboard.llm_provider'), 'model_name' => config('backboard.model_name'),
                'memory' => 'off', 'web_search' => 'off', 'tools' => [], 'stream' => false, 'json_output' => true,
                'image_generation' => 'off', 'video_generation' => 'off',
            ]);
            $class = match ($response->status()) {
                401, 403 => 'authentication', 402 => 'credit', 429 => 'rate_limit',
                default => $response->serverError() ? 'provider_error' : 'invalid_response',
            };
            if (strlen($response->body()) <= 32768) {
                $outer = $response->json();
                $candidate = is_array($outer) ? ($outer['thread_id'] ?? null) : null;
                if (is_string($candidate) && Str::isUuid($candidate)) {
                    $thread = $candidate;
                }
                if ($response->successful() && $thread && ($outer['status'] ?? '') === 'COMPLETED' && empty($outer['tool_calls'])) {
                    $result = $this->validateOutput($outer['content'] ?? null);
                    $class = $result ? 'completed' : 'invalid_response';
                }
            }
        } catch (ConnectionException) {
            $class = 'timeout';
        } catch (Throwable) {
            $class = 'transport_error';
        } finally {
            if ($thread !== null) {
                try {
                    $cleanup = $this->client(3)->delete(self::URL.'/'.$thread)->successful();
                } catch (Throwable) {
                    $cleanup = false;
                }
            }
            if (! $cleanup) {
                Log::warning('Replay advisor', ['advisor_status' => 'cleanup_unconfirmed', 'elapsed_ms' => (int) ((hrtime(true) - $started) / 1e6)]);
            }
        }
        Log::warning('Replay advisor', ['advisor_status' => $class, 'elapsed_ms' => (int) ((hrtime(true) - $started) / 1e6)]);
        // Compare-and-set: a slow old request cannot replace a newer expired lease.
        DB::table('advisor_explanations')->where('id', $reservation['reservation'])->where('status', 'PENDING')->update([
            'status' => $result ? 'COMPLETED' : 'FAILED', 'result_json' => $result ? json_encode($result, JSON_THROW_ON_ERROR) : null,
            'cleanup_confirmed' => $cleanup, 'updated_at' => now(),
        ]);

        return $base + ['status' => $result ? 'completed' : 'unavailable', 'advice' => $result, 'cleanup_confirmed' => $cleanup];
    }

    private function reserve(User $actor, string $org, string $key): array
    {
        return DB::transaction(function () use ($actor, $org, $key) {
            // Same lock order everywhere; HTTP happens AFTER this short transaction commits.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Organization::whereKey($org)->lockForUpdate()->firstOrFail();
            $now = now('UTC');
            $cached = DB::table('advisor_explanations')->where('organization_id', $org)->where('cache_key', $key)->first();
            if ($cached && $cached->expires_at > $now->format('Y-m-d H:i:s')) {
                return ['status' => match ($cached->status) {
                    'COMPLETED' => 'completed', 'PENDING' => 'in_progress', default => 'unavailable'
                },
                    'cached' => true, 'advice' => $cached->result_json ? json_decode($cached->result_json, true, flags: JSON_THROW_ON_ERROR) : null,
                    'cleanup_confirmed' => $cached->cleanup_confirmed === null ? null : (bool) $cached->cleanup_confirmed];
            }
            $buckets = [
                [hash('sha256', 'org:'.$org.':'.$now->format('Y-m-d')), max(0, min(3, (int) config('backboard.daily_calls'))), $now->copy()->addDay()->startOfDay()],
                [hash('sha256', 'user:'.$actor->id), max(0, min(1, (int) config('backboard.user_calls_per_minute'))), $now->copy()->addMinute()],
            ];
            foreach ($buckets as [$bucket, $limit]) {
                if ((int) DB::table('advisor_quotas')->where('key', $bucket)->where('expires_at', '>', $now)->value('calls') >= $limit) {
                    return ['status' => 'quota_exhausted'];
                }
            }
            foreach ($buckets as [$bucket, $limit, $expiry]) {
                DB::table('advisor_quotas')->updateOrInsert(['key' => $bucket], ['organization_id' => $org,
                    'calls' => 1 + (int) DB::table('advisor_quotas')->where('key', $bucket)->where('expires_at', '>', $now)->value('calls'), 'expires_at' => $expiry]);
            }
            $id = (string) Str::uuid();
            // Replace expired lease identity to prevent a late response overwriting the new one.
            if ($cached) {
                DB::table('advisor_explanations')->where('id', $cached->id)->delete();
            }
            DB::table('advisor_explanations')->insert(['id' => $id, 'organization_id' => $org, 'cache_key' => $key, 'status' => 'PENDING',
                'expires_at' => $now->copy()->addSeconds(max(60, min(3600, (int) config('backboard.cache_seconds')))), 'created_at' => $now, 'updated_at' => $now]);

            return ['status' => 'reserved', 'reservation' => $id];
        }, 3);
    }

    private function client(?int $timeout = null): PendingRequest
    {
        return Http::withHeaders(['X-API-Key' => config('backboard.api_key')])->acceptJson()->asJson()
            ->connectTimeout(3)->timeout($timeout ?? max(2, min(20, (int) config('backboard.timeout_seconds'))))
            ->withOptions(['allow_redirects' => false, 'on_headers' => function ($response) {
                if ((int) $response->getHeaderLine('Content-Length') > 32768) {
                    throw new RuntimeException('Response too large.');
                }
            }, 'progress' => function ($total, $downloaded) {
                if ($downloaded > 32768) {
                    throw new RuntimeException('Response too large.');
                }
            }]);
    }

    private function validateOutput(mixed $content): ?array
    {
        if (! is_string($content) || strlen($content) > 4096) {
            return null;
        }
        $data = json_decode($content, true);
        if (! is_array($data) || count($data) !== 2 || ! is_string($data['explanation'] ?? null)
            || ! is_array($data['questions'] ?? null) || ! array_is_list($data['questions']) || count($data['questions']) > 2) {
            return null;
        }
        $texts = [$data['explanation'], ...$data['questions']];
        if (strlen(implode('', $texts)) > 900 || strlen($data['explanation']) > 640
            || ! preg_match('/^[^.?!]+[.!?]\s+[^.?!]+[.!?](?:\s+[^.?!]+[.!?])?$/D', $data['explanation'])) {
            return null;
        }
        foreach ($texts as $i => $text) {
            if (! is_string($text) || trim($text) === '' || ($i > 0 && (strlen($text) > 120 || ! str_ends_with($text, '?')))
                || preg_match('/[^a-zA-Z ,.;:?!\x27()\-]/', $text)
                || preg_match('/(?:https?|www|\b(?:approve|reject|execute|publish|authorize|transfer|refund|guarantee|compliant|savings)\w*)/i', $text)
                || str_contains($text, (string) config('backboard.api_key'))) {
                return null;
            }
        }

        return $data;
    }
}
