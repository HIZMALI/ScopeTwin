<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisor_explanations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->char('cache_key', 64);
            $table->string('status', 16);
            $table->json('result_json')->nullable();
            $table->boolean('cleanup_confirmed')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['organization_id', 'cache_key']);
        });
        Schema::create('advisor_quotas', function (Blueprint $table) {
            $table->char('key', 64)->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('calls')->default(0);
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_quotas');
        Schema::dropIfExists('advisor_explanations');
    }
};
