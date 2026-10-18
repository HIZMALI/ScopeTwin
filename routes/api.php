<?php

use App\Http\Controllers\EvaluateActionController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

Route::post('v1/agent/actions/evaluate', EvaluateActionController::class)
    ->middleware(['throttle:agent-ingress', AuthenticateAgent::class]);
