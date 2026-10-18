<?php

namespace App\Models;

use LogicException;

class PolicyVersion extends ScopedModel
{
    protected function casts(): array
    {
        return ['conditions' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->getOriginal('state') === 'PUBLISHED') {
                throw new LogicException('Published policy snapshots are immutable.');
            }
        });
        static::deleting(function (self $version) {
            if ($version->state === 'PUBLISHED') {
                throw new LogicException('Published policy snapshots cannot be deleted.');
            }
        });
    }
}
