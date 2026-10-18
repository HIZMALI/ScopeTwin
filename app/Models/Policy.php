<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Policy extends ScopedModel
{
    public function versions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class)->orderByDesc('version');
    }
}
