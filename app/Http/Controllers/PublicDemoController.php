<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\ReplayResult;
use App\Models\ReplayRun;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class PublicDemoController
{
    public function landing(Request $request)
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location(url('/'));
        }

        return view('public', ['payload' => ['page' => 'landing']]);
    }

    public function show()
    {
        $data = null;
        if (config('demo.enabled')) {
            $org = Organization::whereKey(config('demo.organization_id'))->where('slug', config('demo.slug'))->first();
            $run = $org ? ReplayRun::forOrganization($org->id)->oldest('created_at')->first() : null;
            if ($run) {
                // Explicit allowlist: no users, keys, IDs, IPs, idempotency keys or free-text labels.
                $rows = ReplayResult::forOrganization($org->id)->where('replay_run_id', $run->id)->orderBy('id')->limit(3)->get();
                $approval = ApprovalRequest::forOrganization($org->id)->where('status', 'APPROVED')->first();
                $data = [
                    'sampleCount' => $run->sample_count, 'changed' => $run->summary['changed'], 'newlyDenied' => $run->summary['newly_denied'],
                    'baselineFingerprint' => $run->baseline_fingerprint, 'candidateFingerprint' => $run->candidate_fingerprint,
                    'rows' => $rows->map(fn ($row) => [
                        'action' => $row->input_snapshot['action_name'], 'amount' => $row->input_snapshot['amount_minor'] ?? null,
                        'original' => $row->original_effect, 'baseline' => $row->baseline_effect, 'candidate' => $row->candidate_effect,
                        'changed' => $row->changed, 'originalReason' => $row->original_outcome['reason_code'], 'candidateReason' => $row->candidate_outcome['reason_code'],
                    ]),
                    'approval' => $approval ? ['status' => $approval->status, 'decision' => 'REVIEW', 'executionPerformed' => false] : null,
                ];
            }
        }

        return view('public', ['payload' => ['page' => 'demo', 'demo' => $data]]);
    }
}
