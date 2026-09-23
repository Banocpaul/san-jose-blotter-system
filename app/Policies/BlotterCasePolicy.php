<?php

namespace App\Policies;

use App\Models\BlotterCase;
use App\Models\User;

class BlotterCasePolicy
{
    /*
    |--------------------------------------------------------------------------
    | View Case List
    |--------------------------------------------------------------------------
    */

    public function viewAny(User $user): bool
    {
        $role = $user->role?->slug;

        return in_array(
            $role,
            [
                'barangay_captain',
                'secretary',
                'staff',
                'councilor',
                'lupon',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | View Individual Blotter Case
    |--------------------------------------------------------------------------
    */

    public function view(
        User $user,
        BlotterCase $case
    ): bool {
        $role = $user->role?->slug;

        /*
         * Captain, Secretary and Staff
         * may view all blotter cases.
         */
        if (
            in_array(
                $role,
                [
                    'barangay_captain',
                    'secretary',
                    'staff',
                ],
                true
            )
        ) {
            return true;
        }

        /*
         * Councilor may only view cases
         * currently assigned to them.
         */
        if ($role === 'councilor') {
            return $case
                ->assignments()
                ->where(
                    'assigned_to',
                    $user->id
                )
                ->whereNull(
                    'completed_at'
                )
                ->exists();
        }

        /*
         * Lupon member may only view cases
         * where a mediation hearing is
         * assigned to them.
         */
        if ($role === 'lupon') {
            return $case
                ->mediationSessions()
                ->where(
                    'lupon_member_id',
                    $user->id
                )
                ->exists();
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Create Blotter Case
    |--------------------------------------------------------------------------
    */

    public function create(User $user): bool
    {
        $role = $user->role?->slug;

        return in_array(
            $role,
            [
                'barangay_captain',
                'secretary',
                'staff',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Blotter Case
    |--------------------------------------------------------------------------
    */

    public function update(
        User $user,
        BlotterCase $case
    ): bool {
        $role = $user->role?->slug;

        return in_array(
            $role,
            [
                'barangay_captain',
                'secretary',
                'staff',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Archive / Delete Blotter Case
    |--------------------------------------------------------------------------
    */

    public function delete(
        User $user,
        BlotterCase $case
    ): bool {
        $role = $user->role?->slug;

        return in_array(
            $role,
            [
                'barangay_captain',
                'secretary',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Restore Archived Blotter Case
    |--------------------------------------------------------------------------
    */

    public function restore(
        User $user,
        BlotterCase $case
    ): bool {
        $role = $user->role?->slug;

        return in_array(
            $role,
            [
                'barangay_captain',
                'secretary',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Permanently Delete Blotter Case
    |--------------------------------------------------------------------------
    */

    public function forceDelete(
        User $user,
        BlotterCase $case
    ): bool {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Assign Case To Councilor
    |--------------------------------------------------------------------------
    */

    public function assign(
        User $user,
        BlotterCase $case
    ): bool {
        $role = $user->role?->slug;

        return in_array(
            $role,
            [
                'barangay_captain',
                'secretary',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Investigation
    |--------------------------------------------------------------------------
    |
    | Captain and Secretary may investigate any case.
    |
    | Councilor may only investigate a case currently
    | assigned to their own account.
    |
    */

    public function investigate(
        User $user,
        BlotterCase $case
    ): bool {
        $role = $user->role?->slug;

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

        if ($role !== 'councilor') {
            return false;
        }

        return $case
            ->assignments()
            ->where(
                'assigned_to',
                $user->id
            )
            ->whereNull(
                'completed_at'
            )
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Manage Witnesses
    |--------------------------------------------------------------------------
    |
    | Captain and Secretary may manage witnesses
    | for any active case.
    |
    | Councilor may manage witnesses only when
    | the case is currently assigned to them.
    |
    | Staff and Lupon may view witnesses but
    | cannot add, edit or remove them.
    |
    */

    public function manageWitnesses(
        User $user,
        BlotterCase $case
    ): bool {
        $role = $user->role?->slug;

        /*
         * Captain and Secretary
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
         * Only Councilors can continue
         * beyond this point.
         */
        if ($role !== 'councilor') {
            return false;
        }

        /*
         * Councilor must currently
         * be assigned to the case.
         */
        return $case
            ->assignments()
            ->where(
                'assigned_to',
                $user->id
            )
            ->whereNull(
                'completed_at'
            )
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Refer Case To Mediation
    |--------------------------------------------------------------------------
    */

    public function referToMediation(
        User $user,
        BlotterCase $case
    ): bool {
        return $this->investigate(
            $user,
            $case
        );
    }
}