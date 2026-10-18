<?php

namespace App\Models;

class ActionRequest extends ScopedModel
{
    protected function casts(): array
    {
        return ['normalized_request_json' => 'array', 'amount_minor' => 'integer'];
    }
}
