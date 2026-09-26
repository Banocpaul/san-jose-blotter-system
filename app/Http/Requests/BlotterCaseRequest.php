<?php

namespace App\Http\Requests;

use App\Enums\CaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlotterCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
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
                Rule::enum(CaseStatus::class),
            ],
        ];

        // The edit page updates only the main case fields. Party identity is
        // intentionally fixed after creation so historical records stay stable.
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return $baseRules;
        }

        $activePerson = Rule::exists('residents', 'id')
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('deleted_at'));

        return array_merge($baseRules, [
            'complainant_resident_id' => [
                'required',
                'integer',
                $activePerson,
            ],
            'respondent_resident_id' => [
                'required',
                'integer',
                'different:complainant_resident_id',
                Rule::exists('residents', 'id')
                    ->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at')),
            ],
        ]);
    }

    public function messages(): array
    {
        return [
            'complainant_resident_id.required' =>
                'Select the complainant from the People Directory.',
            'complainant_resident_id.exists' =>
                'The selected complainant is not an active People Directory record.',
            'respondent_resident_id.required' =>
                'Select the respondent from the People Directory.',
            'respondent_resident_id.exists' =>
                'The selected respondent is not an active People Directory record.',
            'respondent_resident_id.different' =>
                'The complainant and respondent must be different people.',
        ];
    }
}
