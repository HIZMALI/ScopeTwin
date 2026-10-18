<?php

namespace App\Http\Controllers;

use App\Domain\ActionSchema;
use App\Services\EvaluateAction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class EvaluateActionController extends Controller
{
    public function __invoke(Request $request, EvaluateAction $service)
    {
        $key = $request->header('Idempotency-Key', '');
        if (! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => ['Provide a 1–128 character Idempotency-Key using letters, numbers, _, ., :, or -.']]);
        }
        if (! $request->isJson() || strlen($request->getContent()) > 8192) {
            throw ValidationException::withMessages(['body' => ['Send a JSON object up to 8 KB.']]);
        }
        $decoded = json_decode($request->getContent());
        if (! is_object($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages(['body' => ['Send a valid JSON object.']]);
        }
        $action = ActionSchema::normalize((array) $decoded);

        return response()->json($service->handle($request->attributes->get('agent_key'), $key, $action));
    }
}
