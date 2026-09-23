<?php

namespace App\Http\Requests;

use App\Enums\CaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlotterCaseRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function authorize(): bool
    {
        return auth()->check();
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Input
    |--------------------------------------------------------------------------
    |
    | An unchecked checkbox is normally not submitted by HTML.
    | We convert both "Not from Barangay San Jose" fields into true/false
    | values before validation.
    |
    */

    protected function prepareForValidation(): void
    {
        $this->merge([
            'complainant_not_san_jose' =>
                $this->boolean(
                    'complainant_not_san_jose'
                ),

            'respondent_not_san_jose' =>
                $this->boolean(
                    'respondent_not_san_jose'
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    public function rules(): array
    {
        $sitios = [
            'Sitio 1',
            'Sitio 2',
            'Sitio 3',
            'Sitio 4',
        ];

        /*
        |--------------------------------------------------------------------------
        | Main Blotter Case
        |--------------------------------------------------------------------------
        */

        $baseRules = [
            'incident_type_id' => [
                'required',
                'exists:incident_types,id',
            ],

            'incident_date' => [
                'required',
                'date',
            ],

            'incident_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'narrative' => [
                'required',
                'string',
            ],

            'initial_action' => [
                'nullable',
                'string',
            ],

            'remarks' => [
                'nullable',
                'string',
            ],

            'status' => [
                'nullable',
                Rule::enum(
                    CaseStatus::class
                ),
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | During Update
        |--------------------------------------------------------------------------
        |
        | The existing edit page changes only the main blotter case fields.
        | Complainant/respondent information is still handled during creation.
        |
        */

        if (
            $this->isMethod('PUT')
            ||
            $this->isMethod('PATCH')
        ) {
            return $baseRules;
        }

        /*
        |--------------------------------------------------------------------------
        | During Create
        |--------------------------------------------------------------------------
        */

        return array_merge(
            $baseRules,
            [

                /*
                |--------------------------------------------------------------------------
                | Complainant - Identity
                |--------------------------------------------------------------------------
                */

                'complainant_resident_id' => [
                    'nullable',
                    'exists:residents,id',
                ],

                'complainant_first_name' => [
                    'required_without:complainant_resident_id',
                    'nullable',
                    'string',
                    'max:100',
                ],

                'complainant_middle_name' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'complainant_last_name' => [
                    'required_without:complainant_resident_id',
                    'nullable',
                    'string',
                    'max:100',
                ],

                'complainant_suffix' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'complainant_contact_number' => [
                    'nullable',
                    'string',
                    'max:30',
                ],

                /*
                |--------------------------------------------------------------------------
                | Complainant - Address Type
                |--------------------------------------------------------------------------
                */

                'complainant_not_san_jose' => [
                    'required',
                    'boolean',
                ],

                /*
                |--------------------------------------------------------------------------
                | Complainant - Barangay San Jose Address
                |--------------------------------------------------------------------------
                |
                | These are required only when:
                |
                | 1. The user is manually entering the complainant, and
                | 2. "Not from Barangay San Jose" is NOT checked.
                |
                */

                'complainant_house_number' => [
                    Rule::requiredIf(
                        fn () =>
                            ! $this->filled(
                                'complainant_resident_id'
                            )
                            &&
                            ! $this->boolean(
                                'complainant_not_san_jose'
                            )
                    ),

                    'nullable',
                    'string',
                    'max:50',
                ],

                'complainant_sitio' => [
                    Rule::requiredIf(
                        fn () =>
                            ! $this->filled(
                                'complainant_resident_id'
                            )
                            &&
                            ! $this->boolean(
                                'complainant_not_san_jose'
                            )
                    ),

                    'nullable',

                    Rule::in(
                        $sitios
                    ),
                ],

                /*
                |--------------------------------------------------------------------------
                | Complainant - Non-San Jose Address
                |--------------------------------------------------------------------------
                |
                | Complete address is required only if:
                |
                | 1. No existing resident was selected, and
                | 2. "Not from Barangay San Jose" is checked.
                |
                */

                'complainant_address' => [
                    Rule::requiredIf(
                        fn () =>
                            ! $this->filled(
                                'complainant_resident_id'
                            )
                            &&
                            $this->boolean(
                                'complainant_not_san_jose'
                            )
                    ),

                    'nullable',
                    'string',
                    'max:1000',
                ],


                /*
                |--------------------------------------------------------------------------
                | Respondent - Identity
                |--------------------------------------------------------------------------
                */

                'respondent_resident_id' => [
                    'nullable',
                    'exists:residents,id',
                ],

                'respondent_first_name' => [
                    'required_without:respondent_resident_id',
                    'nullable',
                    'string',
                    'max:100',
                ],

                'respondent_middle_name' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'respondent_last_name' => [
                    'required_without:respondent_resident_id',
                    'nullable',
                    'string',
                    'max:100',
                ],

                'respondent_suffix' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'respondent_contact_number' => [
                    'nullable',
                    'string',
                    'max:30',
                ],

                /*
                |--------------------------------------------------------------------------
                | Respondent - Address Type
                |--------------------------------------------------------------------------
                */

                'respondent_not_san_jose' => [
                    'required',
                    'boolean',
                ],

                /*
                |--------------------------------------------------------------------------
                | Respondent - Barangay San Jose Address
                |--------------------------------------------------------------------------
                */

                'respondent_house_number' => [
                    Rule::requiredIf(
                        fn () =>
                            ! $this->filled(
                                'respondent_resident_id'
                            )
                            &&
                            ! $this->boolean(
                                'respondent_not_san_jose'
                            )
                    ),

                    'nullable',
                    'string',
                    'max:50',
                ],

                'respondent_sitio' => [
                    Rule::requiredIf(
                        fn () =>
                            ! $this->filled(
                                'respondent_resident_id'
                            )
                            &&
                            ! $this->boolean(
                                'respondent_not_san_jose'
                            )
                    ),

                    'nullable',

                    Rule::in(
                        $sitios
                    ),
                ],

                /*
                |--------------------------------------------------------------------------
                | Respondent - Non-San Jose Address
                |--------------------------------------------------------------------------
                */

                'respondent_address' => [
                    Rule::requiredIf(
                        fn () =>
                            ! $this->filled(
                                'respondent_resident_id'
                            )
                            &&
                            $this->boolean(
                                'respondent_not_san_jose'
                            )
                    ),

                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages
    |--------------------------------------------------------------------------
    */

    public function messages(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Complainant
            |--------------------------------------------------------------------------
            */

            'complainant_house_number.required' =>
                'The complainant house number is required.',

            'complainant_sitio.required' =>
                'Please select the complainant sitio.',

            'complainant_sitio.in' =>
                'The complainant sitio must be Sitio 1, Sitio 2, Sitio 3, or Sitio 4.',

            'complainant_address.required' =>
                'Please enter the complainant complete address.',

            /*
            |--------------------------------------------------------------------------
            | Respondent
            |--------------------------------------------------------------------------
            */

            'respondent_house_number.required' =>
                'The respondent house number is required.',

            'respondent_sitio.required' =>
                'Please select the respondent sitio.',

            'respondent_sitio.in' =>
                'The respondent sitio must be Sitio 1, Sitio 2, Sitio 3, or Sitio 4.',

            'respondent_address.required' =>
                'Please enter the respondent complete address.',
        ];
    }
}