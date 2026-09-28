<?php

namespace App\Http\Requests;

use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $textFields = [
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'civil_status',
            'classification',
            'contact_number',
            'email',
            'house_number',
            'street',
            'purok',
            'address_details',
            'vulnerable_sector',
            'emergency_contact_name',
            'emergency_contact_number',
            'government_id_type',
            'government_id_number',
        ];

        $normalized = [];

        foreach ($textFields as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = preg_replace(
                '/\s+/u',
                ' ',
                trim($value)
            );

            $normalized[$field] =
                $value === ''
                    ? null
                    : $value;
        }

        if (
            isset($normalized['email'])
            && $normalized['email'] !== null
        ) {
            $normalized['email'] =
                strtolower(
                    $normalized['email']
                );
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'suffix' => [
                'nullable',
                'string',
                'max:20',
            ],

            'sex' => [
                'nullable',
                Rule::in([
                    'Male',
                    'Female',
                ]),
            ],

            'birth_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'civil_status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'classification' => [
                'required',
                Rule::in([
                    'Resident',
                    'Non-Resident',
                ]),
            ],

            'contact_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'house_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'street' => [
                'nullable',
                'string',
                'max:255',
            ],

            'purok' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address_details' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'is_registered_voter' => [
                'nullable',
                'boolean',
            ],

            'vulnerable_sector' => [
                'nullable',
                'string',
                'max:100',
            ],

            'emergency_contact_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'emergency_contact_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'government_id_type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'government_id_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $firstName =
                    $this->string('first_name')
                        ->toString();

                $lastName =
                    $this->string('last_name')
                        ->toString();

                if (
                    $firstName === ''
                    || $lastName === ''
                ) {
                    return;
                }

                $query = Resident::query()
                    ->where(
                        'first_name',
                        $firstName
                    )
                    ->where(
                        'last_name',
                        $lastName
                    );

                $middleName =
                    $this->input('middle_name');

                $suffix =
                    $this->input('suffix');

                $query->where(
                    function ($nameQuery) use (
                        $middleName
                    ) {
                        if ($middleName === null) {
                            $nameQuery
                                ->whereNull(
                                    'middle_name'
                                )
                                ->orWhere(
                                    'middle_name',
                                    ''
                                );

                            return;
                        }

                        $nameQuery->where(
                            'middle_name',
                            $middleName
                        );
                    }
                );

                $query->where(
                    function ($nameQuery) use (
                        $suffix
                    ) {
                        if ($suffix === null) {
                            $nameQuery
                                ->whereNull(
                                    'suffix'
                                )
                                ->orWhere(
                                    'suffix',
                                    ''
                                );

                            return;
                        }

                        $nameQuery->where(
                            'suffix',
                            $suffix
                        );
                    }
                );

                $currentResident =
                    $this->route('resident');

                if (
                    $currentResident
                    instanceof Resident
                ) {
                    $query->whereKeyNot(
                        $currentResident->getKey()
                    );
                }

                $identityChecks = [];

                if ($this->filled('birth_date')) {
                    $identityChecks['birth_date'] =
                        $this->input('birth_date');
                }

                if ($this->filled('contact_number')) {
                    $identityChecks['contact_number'] =
                        $this->input('contact_number');
                }

                if ($this->filled('email')) {
                    $identityChecks['email'] =
                        $this->input('email');
                }

                if (
                    $this->filled(
                        'government_id_number'
                    )
                ) {
                    $identityChecks[
                        'government_id_number'
                    ] =
                        $this->input(
                            'government_id_number'
                        );
                }

                $addressChecks =
                    collect([
                        'house_number' =>
                            $this->input(
                                'house_number'
                            ),

                        'street' =>
                            $this->input(
                                'street'
                            ),

                        'purok' =>
                            $this->input(
                                'purok'
                            ),

                        'address_details' =>
                            $this->input(
                                'address_details'
                            ),
                    ])
                        ->filter(
                            fn ($value) =>
                                $value !== null
                                && $value !== ''
                        )
                        ->all();

                if (
                    $identityChecks === []
                    && $addressChecks === []
                ) {
                    /*
                     * A name alone is not enough to safely reject a person:
                     * two different people can have the same full name.
                     */
                    return;
                }

                $query->where(
                    function ($identityQuery) use (
                        $identityChecks,
                        $addressChecks
                    ) {
                        foreach (
                            $identityChecks
                            as $field => $value
                        ) {
                            if (
                                $field ===
                                'birth_date'
                            ) {
                                $identityQuery
                                    ->orWhereDate(
                                        $field,
                                        $value
                                    );

                                continue;
                            }

                            $identityQuery
                                ->orWhere(
                                    $field,
                                    $value
                                );
                        }

                        if (
                            $addressChecks !== []
                        ) {
                            $identityQuery
                                ->orWhere(
                                    function (
                                        $addressQuery
                                    ) use (
                                        $addressChecks
                                    ) {
                                        foreach (
                                            $addressChecks
                                            as $field => $value
                                        ) {
                                            $addressQuery
                                                ->where(
                                                    $field,
                                                    $value
                                                );
                                        }
                                    }
                                );
                        }
                    }
                );

                $duplicate =
                    $query
                        ->select([
                            'id',
                            'resident_code',
                            'first_name',
                            'middle_name',
                            'last_name',
                            'suffix',
                        ])
                        ->first();

                if (! $duplicate) {
                    return;
                }

                $validator
                    ->errors()
                    ->add(
                        'first_name',
                        "Possible duplicate person found ({$duplicate->resident_code}: {$duplicate->full_name}). Search the People Directory and update the existing record instead of creating another one."
                    );
            },
        ];
    }
}
