<?php

namespace App\Models;

class ReplayResult extends ScopedModel
{
    protected function casts(): array
    {
        return ['input_snapshot' => 'array', 'original_outcome' => 'array', 'baseline_outcome' => 'array', 'candidate_outcome' => 'array', 'changed' => 'boolean'];
    }
}
