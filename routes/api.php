<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\EvaluateActionController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

Route::post('v1/agent/actions/evaluate', EvaluateActionController::class)
    ->middleware(['throttle:agent-ingress', AuthenticateAgent::class]);
Route::get('v1/agent/decisions/{id}/approval', [ApprovalController::class, 'agentStatus'])
    ->whereUuid('id')->middleware(['throttle:agent-ingress', AuthenticateAgent::class]);
