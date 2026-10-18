<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ReplayController;
use App\Http\Controllers\UsageController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
Route::middleware(['auth'])->where(['id' => $uuid, 'keyId' => $uuid, 'resultId' => $uuid])->group(function () {
    Route::get('replays', [ReplayController::class, 'index'])->name('replays.index');
    Route::post('replays', [ReplayController::class, 'store'])->middleware('throttle:replays')->name('replays.store');
    Route::get('replays/{id}', [ReplayController::class, 'show'])->name('replays.show');
    Route::get('replays/{id}/results/{resultId}', [ReplayController::class, 'result'])->name('replays.result');
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('approvals/{id}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('approvals/{id}/resolve', [ApprovalController::class, 'resolve'])->middleware('throttle:30,1')->name('approvals.resolve');
    Route::get('usage', UsageController::class)->name('usage');
    Route::get('dashboard', [WorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
    Route::post('agents', [AgentController::class, 'store'])->middleware('throttle:20,1')->name('agents.store');
    Route::get('agents/{id}', [AgentController::class, 'show'])->name('agents.show');
    Route::patch('agents/{id}', [AgentController::class, 'update'])->name('agents.update');
    Route::post('agents/{id}/keys', [AgentController::class, 'key'])->middleware('throttle:20,1')->name('agents.keys');
    Route::delete('agents/{id}/keys/{keyId}', [AgentController::class, 'revoke'])->name('agents.revoke');
    Route::get('policies', [PolicyController::class, 'index'])->name('policies.index');
    Route::get('policies/create', [PolicyController::class, 'create'])->name('policies.create');
    Route::post('policies', [PolicyController::class, 'store'])->name('policies.store');
    Route::get('policies/{id}', [PolicyController::class, 'show'])->name('policies.show');
    Route::put('policies/{id}', [PolicyController::class, 'update'])->name('policies.update');
    Route::post('policies/{id}/publish', [PolicyController::class, 'publish'])->name('policies.publish');
    Route::delete('policies/{id}', [PolicyController::class, 'disable'])->name('policies.disable');
    Route::get('decisions', [WorkspaceController::class, 'decisions'])->name('decisions.index');
    Route::get('decisions/{id}', [WorkspaceController::class, 'decision'])->name('decisions.show');
    Route::get('audit-events/{id}', [WorkspaceController::class, 'audit'])->name('audit.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
