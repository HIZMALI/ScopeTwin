<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Policy;
use App\Models\PolicyVersion;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoPolicies extends Command
{
    protected $signature = 'scope:demo-policies {--organization= : Existing workspace UUID}';

    protected $description = 'Install explicit development-only sample policies; never creates accounts or keys';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo policies are disabled outside local/testing.');

            return self::FAILURE;
        }
        $org = Organization::find($this->option('organization'));
        if (! $org) {
            $this->error('Specify an existing --organization UUID. Register an account first.');

            return self::FAILURE;
        }
        $rules = [
            ['Customer lookup', 'ALLOW', 'CUSTOMER_READ_ALLOWED', ['action_name' => ['eq' => 'customer.read']]],
            ['Standard refunds', 'ALLOW', 'REFUND_WITHIN_LIMIT', ['action_name' => ['eq' => 'refund.issue'], 'amount_minor' => ['lte' => 50000], 'currency' => ['eq' => 'USD']]],
            ['Large refund review', 'REVIEW', 'REFUND_REQUIRES_APPROVAL', ['action_name' => ['eq' => 'refund.issue'], 'amount_minor' => ['gt' => 50000], 'currency' => ['eq' => 'USD']]],
            ['Block unapproved export', 'DENY', 'EXPORT_DOMAIN_BLOCKED', ['action_name' => ['eq' => 'data.export'], 'destination_domain' => ['in' => ['blocked.example', 'untrusted.example']]]],
        ];
        DB::transaction(function () use ($org, $rules) {
            foreach ($rules as [$name, $effect, $reason, $conditions]) {
                $policy = Policy::firstOrCreate(['organization_id' => $org->id, 'name' => $name]);
                if ($policy->published_version_id) {
                    continue;
                }
                $version = PolicyVersion::create(['organization_id' => $org->id, 'policy_id' => $policy->id, 'version' => ($policy->versions()->max('version') ?? 0) + 1, 'state' => 'PUBLISHED', 'effect' => $effect, 'reason_code' => $reason, 'conditions' => $conditions, 'published_at' => now()]);
                $policy->update(['published_version_id' => $version->id]);
                Audit::record($org->id, 'demo.policy_published', $version->id, 'SYSTEM', 'demo');
            }
        });
        $this->info('Development sample policies installed. No business actions or accounts created.');

        return self::SUCCESS;
    }
}
