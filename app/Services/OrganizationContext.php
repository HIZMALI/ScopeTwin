<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class OrganizationContext
{
    public static function membership(User $user): object
    {
        // Build 01 has one active workspace: first membership, never a submitted tenant ID.
        return DB::table('organization_memberships')->where('user_id', $user->id)->orderBy('id')->first()
            ?? abort(403, 'Organization membership required.');
    }

    public static function id(): string
    {
        return self::membership(request()->user())->organization_id;
    }

    public static function authorizeWrite(): void
    {
        abort_unless(in_array(self::membership(request()->user())->role, ['OWNER', 'ADMIN'], true), 403);
    }
}
