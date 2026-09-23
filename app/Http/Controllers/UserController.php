<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | User List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = User::with('role');

        if ($request->filled('search')) {
            $search = trim(
                $request->search
            );

            $query->where(
                function ($q) use ($search) {

                    $q->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'username',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($request->filled('role_id')) {
            $query->where(
                'role_id',
                $request->role_id
            );
        }

        if (
            $request->filled('status') &&
            in_array(
                $request->status,
                ['active', 'inactive'],
                true
            )
        ) {
            $query->where(
                'is_active',
                $request->status === 'active'
            );
        }

        $users = $query
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::where(
            'is_active',
            true
        )
            ->orderBy('name')
            ->get();

        return view(
            'admin.users.index',
            compact(
                'users',
                'roles'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create User Form
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $roles = Role::where(
            'is_active',
            true
        )
            ->orderBy('name')
            ->get();

        return view(
            'admin.users.create',
            compact('roles')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store User
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'username' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:users,username',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'contact_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'role_id' => [
                'required',
                'exists:roles,id',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $role = Role::whereKey(
            $data['role_id']
        )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $role) {
            return back()
                ->withInput()
                ->withErrors([
                    'role_id' =>
                        'The selected role is not active.',
                ]);
        }

        User::create([
            'name' =>
                $data['name'],

            'username' =>
                $data['username'],

            'email' =>
                $data['email'],

            'contact_number' =>
                $data['contact_number']
                ?? null,

            'role_id' =>
                $data['role_id'],

            'password' =>
                $data['password'],

            'is_active' =>
                $data['is_active'],
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with(
                'success',
                'User account created successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit User
    |--------------------------------------------------------------------------
    */

    public function edit(User $user)
    {
        $user->load('role');

        $roles = Role::where(
            'is_active',
            true
        )
            ->orderBy('name')
            ->get();

        return view(
            'admin.users.edit',
            compact(
                'user',
                'roles'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update User
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        User $user
    ) {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'username' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',

                Rule::unique(
                    'users',
                    'username'
                )->ignore($user->id),
            ],

            'email' => [
                'required',
                'email',
                'max:255',

                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
            ],

            'contact_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'role_id' => [
                'required',
                'exists:roles,id',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        /*
         * Prevent an administrator from accidentally
         * disabling or demoting their own account.
         */
        if (
            $user->id ===
            auth()->id()
        ) {
            $data['role_id'] =
                $user->role_id;

            $data['is_active'] =
                true;
        }

        $role = Role::whereKey(
            $data['role_id']
        )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $role) {
            return back()
                ->withInput()
                ->withErrors([
                    'role_id' =>
                        'The selected role is not active.',
                ]);
        }

        $updates = [
            'name' =>
                $data['name'],

            'username' =>
                $data['username'],

            'email' =>
                $data['email'],

            'contact_number' =>
                $data['contact_number']
                ?? null,

            'role_id' =>
                $data['role_id'],

            'is_active' =>
                $data['is_active'],
        ];

        if (
            ! empty(
                $data['password']
            )
        ) {
            $updates['password'] =
                $data['password'];
        }

        $user->update(
            $updates
        );

        return redirect()
            ->route('admin.users.index')
            ->with(
                'success',
                'User account updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Activate / Deactivate User
    |--------------------------------------------------------------------------
    */

    public function toggleStatus(User $user)
    {
        if (
            $user->id ===
            auth()->id()
        ) {
            return back()
                ->withErrors([
                    'user' =>
                        'You cannot deactivate your own account.',
                ]);
        }

        $user->update([
            'is_active' =>
                ! $user->is_active,
        ]);

        return back()->with(
            'success',
            $user->is_active
                ? 'User account activated successfully.'
                : 'User account deactivated successfully.'
        );
    }
}