<?php

namespace App\Services;

use App\Models\BlotterCase;
use Illuminate\Validation\ValidationException;

class CaseJurisdictionService
{
    public function fingerprint(BlotterCase $case): string
    {
        $facts = $case->only(['incident_type_id', 'incident_date', 'location', 'narrative']);
        foreach (['complainants', 'respondents'] as $relation) {
            $facts[$relation] = $case->{$relation}()->orderBy('id')
                ->get(['id', 'first_name', 'last_name', 'address', 'is_san_jose_resident'])->toArray();
        }

        return hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR));
    }

    public function isApproved(BlotterCase $case): bool
    {
        $assessment = $case->jurisdictionAssessment;

        return $assessment?->decision === 'within_authority'
            && hash_equals($assessment->facts_fingerprint, $this->fingerprint($case));
    }

    public function requireApproval(BlotterCase $case): void
    {
        if (! $this->isApproved($case)) {
            throw ValidationException::withMessages([
                'jurisdiction' => 'The Captain or Secretary must confirm barangay settlement authority and proper venue for the current case facts before mediation.',
            ]);
        }
    }
}
