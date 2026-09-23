<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
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
                'in:Male,Female',
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

            'classification' => [
                'nullable',
                'string',
                'max:100',
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
}