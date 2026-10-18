<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('organization_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 16)->default('OWNER');
            $t->timestamps();
            $t->unique(['organization_id', 'user_id']);
        });
        Schema::create('agents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->string('name', 100);
            $t->string('status', 16)->default('ACTIVE');
            $t->timestamps();
            $t->index(['organization_id', 'created_at']);
        });
        Schema::create('agent_api_keys', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->foreignUuid('agent_id')->constrained();
            $t->string('prefix', 16)->unique();
            $t->string('key_hash', 64);
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('policies', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->string('name', 100);
            $t->string('status', 16)->default('ACTIVE');
            $t->uuid('published_version_id')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'status']);
        });
        Schema::create('policy_versions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->foreignUuid('policy_id')->constrained();
            $t->unsignedInteger('version');
            $t->string('state', 16)->default('DRAFT');
            $t->json('conditions');
            $t->string('effect', 8);
            $t->string('reason_code', 64);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['policy_id', 'version']);
        });
        Schema::table('policies', fn (Blueprint $t) => $t->foreign('published_version_id')->references('id')->on('policy_versions'));
        Schema::create('action_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->foreignUuid('agent_id')->constrained();
            $t->string('idempotency_key', 128);
            $t->string('action_name', 128);
            $t->unsignedBigInteger('amount_minor')->nullable();
            $t->string('currency', 3)->nullable();
            $t->string('destination_domain', 253)->nullable();
            $t->json('normalized_request_json');
            $t->timestamps();
            $t->unique(['organization_id', 'agent_id', 'idempotency_key'], 'action_request_idempotency_unique');
        });
        Schema::create('policy_decisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->foreignUuid('agent_id')->constrained();
            $t->foreignUuid('action_request_id')->unique()->constrained();
            $t->foreignUuid('policy_version_id')->nullable()->constrained();
            $t->string('decision', 8);
            $t->string('reason_code', 64);
            $t->timestamp('evaluated_at');
            $t->timestamps();
            $t->index(['organization_id', 'evaluated_at']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained();
            $t->string('actor_type', 16);
            $t->string('actor_id')->nullable();
            $t->string('event_type', 64);
            $t->uuid('subject_id');
            $t->json('metadata_json');
            $t->timestamp('occurred_at');
            $t->index(['organization_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('policy_decisions');
        Schema::dropIfExists('action_requests');
        Schema::table('policies', fn (Blueprint $t) => $t->dropForeign(['published_version_id']));
        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('policies');
        Schema::dropIfExists('agent_api_keys');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');
    }
};
