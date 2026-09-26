<?php

namespace App\Services;

use App\Models\BlotterCase;
use App\Models\Resident;
use Illuminate\Validation\ValidationException;

class CasePartyService
{
    /**
     * Attach the complainant and respondent to a case using People Directory
     * records. Both people are loaded in one query and copied into case-party
     * snapshots so later edits to the directory do not rewrite case history.
     */
    public function attachFromDirectory(
        BlotterCase $case,
        int $complainantId,
        int $respondentId
    ): void {
        $people = Resident::active()
            ->whereIn('id', [$complainantId, $respondentId])
            ->get([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'contact_number',
                'house_number',
                'street',
                'purok',
                'address_details',
                'classification',
            ])
            ->keyBy('id');

        $complainant = $people->get($complainantId);
        $respondent = $people->get($respondentId);

        if (! $complainant || ! $respondent) {
            $errors = [];

            if (! $complainant) {
                $errors['complainant_resident_id'] =
                    'The selected complainant is no longer active. Search the People Directory again.';
            }

            if (! $respondent) {
                $errors['respondent_resident_id'] =
                    'The selected respondent is no longer active. Search the People Directory again.';
            }

            throw ValidationException::withMessages($errors);
        }

        $case->complainants()->create(
            $this->snapshot($complainant)
        );

        $case->respondents()->create(
            $this->snapshot($respondent)
        );
    }

    private function snapshot(Resident $person): array
    {
        // Legacy records may have a NULL classification. Treat them as
        // residents unless they are explicitly marked Non-Resident.
        $isResident = $person->classification !== 'Non-Resident';

        return [
            'resident_id' => $person->id,
            'is_san_jose_resident' => $isResident,
            'house_number' => $isResident ? $person->house_number : null,
            'sitio' => $isResident ? $person->purok : null,
            'first_name' => $person->first_name,
            'middle_name' => $person->middle_name,
            'last_name' => $person->last_name,
            'suffix' => $person->suffix,
            'contact_number' => $person->contact_number,
            'address' => $this->address($person, $isResident),
        ];
    }

    private function address(Resident $person, bool $isResident): string
    {
        $parts = collect([
            $person->house_number,
            $person->street,
            $person->purok,
            $person->address_details,
        ])->filter(fn ($value) => filled($value));

        if ($isResident) {
            $parts->push('Barangay San Jose');
        }

        return $parts->unique()->join(', ');
    }
}
