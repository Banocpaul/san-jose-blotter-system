<?php

namespace App\Policies;

use App\Models\MediationSession;
use App\Models\User;

class MediationSessionPolicy
{
    public function manage(
        User $user,
        MediationSession $session
    ): bool {
        $role = $user->role?->slug;

        /*
         * Barangay Captain and Secretary
         * may manage any mediation hearing.
         */
        if (
            in_array(
                $role,
                [
                    'barangay_captain',
                    'secretary',
                ],
                true
            )
        ) {
            return true;
        }

        /*
         * Lupon members may only manage
         * hearings assigned specifically to them.
         */
        if ($role !== 'lupon') {
            return false;
        }

        return (int) $session->lupon_member_id
            === (int) $user->id;
    }
}