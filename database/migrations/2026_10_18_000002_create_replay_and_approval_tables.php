<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replay_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('candidate_policy_id')->constrained('policies');
            $t->string('candidate_policy_name', 100);
            $t->string('candidate_source', 8)->default('DRAFT');
            $t->json('baseline_manifest');
            $t->string('baseline_fingerprint', 64);
            $t->json('candidate_snapshot');
            $t->string('candidate_fingerprint', 64);
            $t->json('sample_manifest');
            $t->json('filters');
            $t->unsignedInteger('sample_count');
            $t->json('summary');
            $t->string('status', 16)->default('COMPLETED');
            $t->timestamp('completed_at');
            $t->timestamps();
            $t->index(['organization_id', 'created_at']);
        });
        Schema::create('replay_results', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->foreignUuid('replay_run_id')->constrained();
            $t->foreignUuid('historical_action_request_id')->constrained('action_requests');
            $t->foreignUuid('historical_policy_decision_id')->constrained('policy_decisions');
            $t->json('input_snapshot');
            $t->string('original_effect', 8);
            $t->string('baseline_effect', 8);
            $t->string('candidate_effect', 8);
            $t->json('original_outcome');
            $t->json('baseline_outcome');
            $t->json('candidate_outcome');
            $t->boolean('changed');
            $t->timestamps();
            $t->unique(['replay_run_id', 'historical_action_request_id'], 'replay_result_sample_unique');
            $t->index(['organization_id', 'replay_run_id', 'changed']);
        });
        Schema::create('approval_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->foreignUuid('agent_id')->constrained();
            $t->foreignUuid('agent_api_key_id')->constrained();
            $t->foreignUuid('action_request_id')->constrained();
            $t->foreignUuid('policy_decision_id')->unique()->constrained();
            $t->string('status', 16)->default('PENDING');
            $t->json('action_summary');
            $t->json('policy_manifest');
            $t->string('policy_fingerprint', 64);
            $t->timestamp('expires_at');
            $t->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('resolution_reason', 32)->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'status', 'expires_at']);
            $t->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('replay_results');
        Schema::dropIfExists('replay_runs');
    }
};
