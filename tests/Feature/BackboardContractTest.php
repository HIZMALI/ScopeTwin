<?php

namespace Tests\Feature;

use App\Logging\RedactSecrets;
use App\Models\ReplayRun;
use App\Models\User;
use App\Services\ReplayAdvisor;
use App\Support\AdvisorDiagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackboardContractTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private ReplayRun $run;

    private string $assistant;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['demo.enabled' => true, 'demo.allow_setup' => true]);
        $this->artisan('scope:judge-demo', ['--confirm-disposable' => true])->assertSuccessful();
        $this->actor = User::firstOrFail();
        $this->run = ReplayRun::firstOrFail();
        $this->assistant = (string) Str::uuid();
        config(['backboard.enabled' => true, 'backboard.api_key' => Str::random(48), 'backboard.assistant_id' => $this->assistant,
            'backboard.llm_provider' => 'mock-provider', 'backboard.model_name' => 'mock-model']);
    }

    private function envelope(mixed $content): array
    {
        // Shape of the documented non-streaming Send Message response, including metadata.
        return ['status' => 'COMPLETED', 'thread_id' => (string) Str::uuid(), 'assistant_id' => $this->assistant,
            'message_id' => (string) Str::uuid(), 'role' => 'assistant', 'content' => $content,
            'model_provider' => 'mock-provider', 'model_name' => 'mock-model', 'tool_calls' => [],
            'retrieved_memories' => [], 'retrieved_files' => [], 'retrieved_files_count' => 0,
            'input_tokens' => 350, 'output_tokens' => 65, 'total_tokens' => 415, 'context_usage' => ['used_tokens' => 415]];
    }

    public static function legitimateOutputs(): array
    {
        return [
            ['2 of 3 sampled decisions changed; 2 newly denied outcomes are simulated.', ['What explains the “changed” outcomes?'], false],
            ['There are 2 changes. The candidate’s simulated results differ from the baseline—no refunds were executed.', ['Which comparisons need review'], false],
            ['The frozen comparison is a simulation (i.e., historical inputs only).', [], true],
            ['The candidate changed historical outcomes.', [], false],
        ];
    }

    #[DataProvider('legitimateOutputs')]
    public function test_documented_content_json_and_normal_prose_are_accepted(string $explanation, array $questions, bool $encoded): void
    {
        $advice = ['explanation' => $explanation, 'questions' => $questions];
        $json = json_encode($advice + ['harmless_extra_field' => 'ignored'], JSON_THROW_ON_ERROR);
        Http::fake(['*/threads/messages' => Http::response($this->envelope($encoded ? json_encode($json) : $json)), '*/threads/*' => Http::response('', 204)]);
        $result = app(ReplayAdvisor::class)->advise($this->actor, $this->run->id);
        $this->assertSame('completed', $result['status']);
        $this->assertSame($advice, $result['advice']);
        $this->assertSame('accepted', $result['diagnostics']['validation_reason']);
        $this->assertSame(200, $result['diagnostics']['http_status']);
        $this->assertTrue($result['cleanup_confirmed']);
        Http::assertSentCount(2);
    }

    public static function failures(): array
    {
        return [
            ['http_400', 'http', 'http_rejected'], ['http_401', 'http', 'http_rejected'], ['http_402', 'http', 'http_rejected'],
            ['http_429', 'http', 'http_rejected'], ['http_500', 'http', 'http_rejected'],
            ['failed', 'provider', 'provider_failed'], ['incomplete', 'provider', 'provider_incomplete'],
            ['tools', 'provider', 'tools_requested'], ['model', 'provider', 'model_mismatch'],
            ['assistant', 'binding', 'assistant_mismatch'], ['missing_assistant', 'binding', 'assistant_mismatch'],
            ['outer_json', 'envelope', 'invalid_envelope_json'], ['content_json', 'output', 'invalid_content_json'],
            ['schema', 'output', 'content_schema'], ['context', 'provider', 'unexpected_context'],
            ['timeout', 'transport', 'timed_out'], ['tls', 'transport', 'tls_failed'],
        ];
    }

    #[DataProvider('failures')]
    public function test_failure_stage_and_http_status_are_finite_and_never_retried(string $scenario, string $stage, string $reason): void
    {
        Log::spy();
        $outer = $this->envelope(json_encode(['explanation' => 'This is a frozen simulation.', 'questions' => []]));
        match ($scenario) {
            'failed' => $outer['status'] = 'FAILED',
            'incomplete' => $outer['status'] = 'IN_PROGRESS',
            'tools' => $outer['tool_calls'] = [['function' => ['name' => 'untrusted']]],
            'model' => $outer['model_name'] = 'wrong-model',
            'assistant' => $outer['assistant_id'] = (string) Str::uuid(),
            'missing_assistant' => $outer['assistant_id'] = null,
            'content_json' => $outer['content'] = '{broken',
            'schema' => $outer['content'] = '{"explanation": 9, "questions": []}',
            'context' => $outer['retrieved_files_count'] = 1,
            default => null,
        };
        $status = str_starts_with($scenario, 'http_') ? (int) substr($scenario, 5) : 200;
        Http::fake(function ($request) use ($scenario, $outer, $status) {
            if ($request->method() === 'DELETE') {
                return Http::response('', 204);
            }
            if (in_array($scenario, ['timeout', 'tls'], true)) {
                throw new ConnectionException('cURL error '.($scenario === 'tls' ? '60' : '28').': PRIVATE UPSTREAM DETAILS');
            }

            return Http::response($scenario === 'outer_json' ? '{broken' : $outer, $status);
        });
        $result = app(ReplayAdvisor::class)->advise($this->actor, $this->run->id);
        $this->assertSame('unavailable', $result['status']);
        $this->assertSame($stage, $result['diagnostics']['failure_stage']);
        $this->assertSame($reason, $result['diagnostics']['validation_reason']);
        $this->assertSame(in_array($scenario, ['timeout', 'tls']) ? null : $status, $result['diagnostics']['http_status']);
        $this->assertSame(! in_array($scenario, ['timeout', 'tls', 'outer_json']), $result['cleanup_confirmed']);
        $calls = count(Http::recorded());
        $this->assertTrue(app(ReplayAdvisor::class)->advise($this->actor, $this->run->id)['cached']);
        Http::assertSentCount($calls);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $message === 'Replay advisor'
            && $context === AdvisorDiagnostics::safe($context) && ! str_contains(json_encode($context), 'PRIVATE'));
    }

    public static function unsafeOutputs(): array
    {
        return [
            ['There are 3 changes.', 'aggregate_mismatch'],
            ['3 of 3 sampled decisions changed.', 'aggregate_mismatch'],
            ['This prevented losses and guarantees compliance.', 'unsupported_claim'],
            ['You should approve the refund.', 'unsupported_claim'],
            ['Disable CSRF security checks.', 'unsupported_claim'],
            ['Contact customer@example.test for details.', 'unsafe_content'],
            ['<b>Execute this</b>', 'unsafe_content'],
        ];
    }

    #[DataProvider('unsafeOutputs')]
    public function test_false_counts_privilege_instructions_and_sensitive_content_remain_rejected(string $text, string $reason): void
    {
        Http::fake(['*/threads/messages' => Http::response($this->envelope(json_encode(['explanation' => $text, 'questions' => []]))),
            '*/threads/*' => Http::response('', 204)]);
        $result = app(ReplayAdvisor::class)->advise($this->actor, $this->run->id);
        $this->assertSame($reason, $result['diagnostics']['validation_reason']);
        $this->assertNull($result['advice']);
        $this->assertTrue($result['cleanup_confirmed']);
    }

    public function test_production_log_processor_preserves_only_whitelisted_diagnostic_fields(): void
    {
        $this->app->instance('env', 'production');
        $handler = new TestHandler;
        $logger = new Logger(new Monolog('safe', [$handler]));
        (new RedactSecrets)($logger);
        $logger->warning('RAW PRIVATE RESPONSE', ['advisor_status' => 'invalid_response', 'http_status' => 200,
            'failure_stage' => 'output', 'validation_reason' => 'invalid_content_json', 'elapsed_ms' => 23,
            'cleanup_status' => 'confirmed', 'cleanup_http_status' => 204, 'generation_attempted' => true,
            'api_key' => config('backboard.api_key'), 'assistant_id' => $this->assistant, 'content' => 'SENSITIVE']);
        $record = $handler->getRecords()[0];
        $this->assertSame('Application event', $record->message);
        $this->assertSame('replay_advisor', $record->context['event']);
        $this->assertSame(200, $record->context['http_status']);
        $this->assertSame('invalid_content_json', $record->context['validation_reason']);
        $serialized = json_encode($record->toArray());
        foreach (['SENSITIVE', 'RAW PRIVATE RESPONSE', $this->assistant, config('backboard.api_key')] as $private) {
            $this->assertStringNotContainsString($private, $serialized);
        }
        $this->assertNull(AdvisorDiagnostics::safe(['validation_reason' => 'PRIVATE'])['validation_reason']);
    }

    public static function smokeOutcomes(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('smokeOutcomes')]
    public function test_smoke_reports_real_attempt_diagnostics_and_domain_preservation_without_secrets(bool $success): void
    {
        // Change only the guard's configured label after the disposable PDO connection exists.
        config(['database.connections.'.config('database.default').'.database' => 'scopetwin_contract_rehearsal']);
        $outer = $this->envelope(json_encode(['explanation' => 'This is a frozen simulation.', 'questions' => []]));
        $outer['status'] = $success ? 'COMPLETED' : 'FAILED';
        Http::fake(['*/threads/messages' => Http::response($outer), '*/threads/*' => Http::response('', 200)]);
        $exit = Artisan::call('scope:backboard-smoke', ['--live-backboard-smoke' => true, '--confirm-charge-credits' => true]);
        $output = Artisan::output();
        $this->assertSame($success ? 0 : 1, $exit);
        $this->assertStringContainsString('"http_status":200', $output);
        $this->assertStringContainsString('"deterministic_unchanged":true', $output);
        $this->assertStringContainsString(($success ? 'SUCCESS' : 'FAIL').': cleanup confirmed', $output);
        foreach ([$this->assistant, config('backboard.api_key'), 'frozen simulation'] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
        Http::assertSentCount(2);
    }
}
