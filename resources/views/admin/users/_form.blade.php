<div class="row g-3">

    <div class="col-md-6">

        <label class="form-label">
            Full Name *
        </label>

        <input
            type="text"
            name="name"
            class="form-control"
            value="{{ old('name', $user->name ?? '') }}"
            required
        >

    </div>


    <div class="col-md-6">

        <label class="form-label">
            Username *
        </label>

        <input
            type="text"
            name="username"
            class="form-control"
            value="{{ old('username', $user->username ?? '') }}"
            required
        >

    </div>


    <div class="col-md-6">

        <label class="form-label">
            Email *
        </label>

        <input
            type="email"
            name="email"
            class="form-control"
            value="{{ old('email', $user->email ?? '') }}"
            required
        >

    </div>


    <div class="col-md-6">

        <label class="form-label">
            Contact Number
        </label>

        <input
            type="text"
            name="contact_number"
            class="form-control"
            value="{{
                old(
                    'contact_number',
                    $user->contact_number ?? ''
                )
            }}"
        >

    </div>


    <div class="col-md-6">

        <label class="form-label">
            Role *
        </label>

        <select
            name="role_id"
            class="form-select"
            required
            @disabled(
                isset($user) &&
                $user->id === auth()->id()
            )
        >

            <option value="">
                Select Role
            </option>

            @foreach($roles as $role)

                <option
                    value="{{ $role->id }}"
                    @selected(
                        old(
                            'role_id',
                            $user->role_id ?? ''
                        ) == $role->id
                    )
                >
                    {{ $role->name }}
                </option>

            @endforeach

        </select>


        @if(
            isset($user) &&
            $user->id === auth()->id()
        )

            <input
                type="hidden"
                name="role_id"
                value="{{ $user->role_id }}"
            >

            <div class="form-text">
                You cannot change your own role.
            </div>

        @endif

    </div>


    <div class="col-md-6">

        <label class="form-label">
            Account Status
        </label>

        <select
            name="is_active"
            class="form-select"
            @disabled(
                isset($user) &&
                $user->id === auth()->id()
            )
        >

            <option
                value="1"
                @selected(
                    old(
                        'is_active',
                        isset($user)
                            ? (int) $user->is_active
                            : 1
                    ) == 1
                )
            >
                Active
            </option>

            <option
                value="0"
                @selected(
                    old(
                        'is_active',
                        isset($user)
                            ? (int) $user->is_active
                            : 1
                    ) == 0
                )
            >
                Inactive
            </option>

        </select>


        @if(
            isset($user) &&
            $user->id === auth()->id()
        )

            <input
                type="hidden"
                name="is_active"
                value="1"
            >

        @endif

    </div>


    <div class="col-md-6">

        <label class="form-label">

            Password

            @if(!isset($user))
                *
            @endif

        </label>

        <input
            type="password"
            name="password"
            class="form-control"
            {{ !isset($user) ? 'required' : '' }}
        >

        @if(isset($user))

            <div class="form-text">
                Leave blank to keep the existing password.
            </div>

        @endif

    </div>


    <div class="col-md-6">

        <label class="form-label">
            Confirm Password
        </label>

        <input
            type="password"
            name="password_confirmation"
            class="form-control"
            {{ !isset($user) ? 'required' : '' }}
        >

    </div>

</div>