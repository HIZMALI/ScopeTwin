<?php

namespace App\Models;

class AuditEvent extends ScopedModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['metadata_json' => 'array', 'occurred_at' => 'datetime'];
    }
}
