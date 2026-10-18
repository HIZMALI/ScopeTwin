<?php

namespace App\Services;

use App\Models\AuditEvent;

final class Audit
{
    public static function record(string $org, string $event, string $subject, string $actorType, string $actorId, array $metadata = []): void
    {
        AuditEvent::create([
            'organization_id' => $org, 'event_type' => $event, 'subject_id' => $subject,
            'actor_type' => $actorType, 'actor_id' => $actorId, 'metadata_json' => $metadata, 'occurred_at' => now(),
        ]);
    }
}
