<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ReplayRun;
use App\Models\User;
use App\Support\AdvisorDiagnostics;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

/** Explanatory sidecar. Never invokes the evaluator or mutates domain records. */
final class ReplayAdvisor
{
    private const URL = 'https://app.backboard.io/api/threads';

    private const VERSION = 'aggregate-advisor-v3-contract-diagnostics';

    private const PROMPT = 'Explain this simulated historical policy replay briefly in English. Return only a JSON object with explanation (string) and questions (array of at most two short human review questions). Use only supplied aggregate facts; if citing numbers, copy the exact corresponding counts. Counts and policy decisions are computed separately by the deterministic engine. Do not invent causes, risk reductions, prevented losses, savings or legal/compliance guarantees. Do not include identifiers, personal data, URLs, HTML or markdown. Do not recommend authorization decisions, policy edits, approvals, rejections or transactions. Do not use tools. Describe uncertainty and suggest questions for a human reviewer only. This simulation executes no actions.';

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
        $assistant = config('backboard.assistant_id');
        if (! is_string($assistant) || ! Str::isUuid($assistant)) {
            return 'not_configured';
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
            $run->sample_manifest, config('backboard.llm_provider'), config('backboard.model_name'), strtolower(config('backboard.assistant_id')), self::VERSION], JSON_THROW_ON_ERROR));
        $reservation = $this->reserve($actor, $run->organization_id, $cacheKey);
        if ($reservation['status'] !== 'reserved') {
            return array_replace($base, $reservation);
        }

        $started = hrtime(true);
        $thread = null;
        $cleanup = false;
        $result = null;
        $diagnostics = ['advisor_status' => 'invalid_response', 'failure_stage' => 'transport', 'validation_reason' => 'connection_failed',
            'http_status' => null, 'cleanup_http_status' => null, 'generation_attempted' => true];
        try {
            $response = $this->client()->post(self::URL.'/messages', [
                'content' => json_encode($summary, JSON_THROW_ON_ERROR), 'system_prompt' => self::PROMPT,
                'assistant_id' => config('backboard.assistant_id'),
                'llm_provider' => config('backboard.llm_provider'), 'model_name' => config('backboard.model_name'),
                'memory' => 'off', 'web_search' => 'off', 'tools' => [], 'stream' => false, 'json_output' => true,
                'image_generation' => 'off', 'video_generation' => 'off',
            ]);
            $diagnostics['http_status'] = $response->status();
            $diagnostics['failure_stage'] = 'http';
            $diagnostics['validation_reason'] = 'http_rejected';
            $diagnostics['advisor_status'] = match ($response->status()) {
                401, 403 => 'authentication', 402 => 'credit', 429 => 'rate_limit',
                default => $response->successful() ? 'invalid_response' : ($response->serverError() ? 'provider_error' : 'request_error'),
            };
            if (strlen($response->body()) > 32768) {
                $diagnostics['failure_stage'] = 'envelope';
                $diagnostics['validation_reason'] = 'response_too_large';
            } else {
                try {
                    $outer = json_decode($response->body(), true, 32, JSON_THROW_ON_ERROR);
                    $diagnostics['response_status'] = is_array($outer) ? ($outer['status'] ?? null) : null;
                    // Capture only a UUID for best-effort cleanup, even for rejected envelopes.
                    $candidate = is_array($outer) ? ($outer['thread_id'] ?? null) : null;
                    if (is_string($candidate) && Str::isUuid($candidate)) {
                        $thread = $candidate;
                    }
                    if ($response->successful()) {
                        $checked = $this->parseResponse($outer, $thread, $summary);
                        $result = $checked['advice'];
                        $diagnostics = array_replace($diagnostics, $checked['diagnostics']);
                    }
                } catch (JsonException) {
                    if ($response->successful()) {
                        $diagnostics = array_replace($diagnostics, ['advisor_status' => 'invalid_response',
                            'failure_stage' => 'envelope', 'validation_reason' => 'invalid_envelope_json']);
                    }
                }
            }
        } catch (ConnectionException $exception) {
            // Extract a finite curl code only, never the exception message or URL/headers.
            preg_match('/cURL error (\d+):/', $exception->getMessage(), $curl);
            $code = (int) ($curl[1] ?? 0);
            [$class, $reason] = match (true) {
                $code === 28 => ['timeout', 'timed_out'],
                in_array($code, [35, 51, 58, 60, 77, 83], true) => ['tls_error', 'tls_failed'],
                default => ['transport_error', 'connection_failed'],
            };
            $diagnostics = array_replace($diagnostics, ['advisor_status' => $class, 'failure_stage' => 'transport', 'validation_reason' => $reason]);
        } catch (Throwable) {
            $diagnostics = array_replace($diagnostics, ['advisor_status' => 'transport_error', 'failure_stage' => 'transport', 'validation_reason' => 'connection_failed']);
        } finally {
            if ($thread !== null) {
                try {
                    $deleted = $this->client(3)->delete(self::URL.'/'.$thread);
                    $diagnostics['cleanup_http_status'] = $deleted->status();
                    $cleanup = $deleted->successful();
                } catch (Throwable) {
                    $cleanup = false;
                }
            }
        }
        $diagnostics['cleanup_status'] = $cleanup ? 'confirmed' : ($thread === null ? 'no_thread' : 'failed');
        $diagnostics['elapsed_ms'] = (int) ((hrtime(true) - $started) / 1e6);
        $diagnostics = AdvisorDiagnostics::safe($diagnostics);
        Log::warning('Replay advisor', $diagnostics);
        // Compare-and-set: a slow old request cannot replace a newer expired lease.
        DB::table('advisor_explanations')->where('id', $reservation['reservation'])->where('status', 'PENDING')->update([
            'status' => $result ? 'COMPLETED' : 'FAILED', 'result_json' => $result ? json_encode($result, JSON_THROW_ON_ERROR) : null,
            'cleanup_confirmed' => $cleanup, 'updated_at' => now(),
        ]);

        return $base + ['status' => $result ? 'completed' : 'unavailable', 'advice' => $result, 'cleanup_confirmed' => $cleanup, 'diagnostics' => $diagnostics];
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

    private function parseResponse(mixed $outer, ?string $thread, array $summary): array
    {
        $reject = fn (string $stage, string $reason, string $class = 'invalid_response') => ['advice' => null,
            'diagnostics' => ['advisor_status' => $class, 'failure_stage' => $stage, 'validation_reason' => $reason]];
        if (! is_array($outer) || array_is_list($outer)) {
            return $reject('envelope', 'invalid_envelope');
        }
        if (! $thread) {
            return $reject('envelope', 'missing_thread');
        }
        $assistant = $outer['assistant_id'] ?? null;
        if (! is_string($assistant) || ! hash_equals(strtolower(config('backboard.assistant_id')), strtolower($assistant))) {
            return $reject('binding', 'assistant_mismatch');
        }
        if (($outer['status'] ?? null) !== 'COMPLETED') {
            return $reject('provider', ($outer['status'] ?? null) === 'FAILED' ? 'provider_failed' : 'provider_incomplete', 'provider_error');
        }
        if (! empty($outer['tool_calls'])) {
            return $reject('provider', 'tools_requested');
        }
        foreach (['model_provider' => 'llm_provider', 'model_name' => 'model_name'] as $field => $setting) {
            if (isset($outer[$field]) && $outer[$field] !== config('backboard.'.$setting)) {
                return $reject('provider', 'model_mismatch');
            }
        }
        if (! empty($outer['retrieved_memories']) || ! empty($outer['retrieved_files']) || ! empty($outer['retrieved_files_count'])) {
            return $reject('provider', 'unexpected_context');
        }
        $checked = app(ReplayAdviceOutput::class)->validate($outer['content'] ?? null, $summary);
        if ($checked['advice'] === null) {
            return $reject('output', $checked['reason']);
        }

        return ['advice' => $checked['advice'], 'diagnostics' => ['advisor_status' => 'completed', 'failure_stage' => 'none', 'validation_reason' => 'accepted']];
    }
}
