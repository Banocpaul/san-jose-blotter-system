<?php

namespace App\Enums;

enum CaseStage: string
{
    case New = 'New';
    case UnderAssessment = 'Under Assessment';
    case ForMediation = 'For Mediation';
    case ForPangkatConciliation = 'For Pangkat/Conciliation';
    case ForFurtherActionCfa = 'For Further Action/CFA';
    case SettledResolved = 'Settled/Resolved';
    case Closed = 'Closed';

    public static function fromStatus(
        CaseStatus $status,
        ?self $currentStage = null
    ): self {
        // The legacy CaseStatus enum has no separate Pangkat value.
        // case_stage therefore carries the more precise Lupon workflow stage
        // while the operational status remains For Mediation.
        if (
            $status === CaseStatus::ForMediation
            && $currentStage === self::ForPangkatConciliation
        ) {
            return self::ForPangkatConciliation;
        }

        return match ($status) {
            CaseStatus::Pending => self::New,
            CaseStatus::UnderInvestigation => self::UnderAssessment,
            CaseStatus::ForMediation => self::ForMediation,
            CaseStatus::Settled,
            CaseStatus::Resolved => self::SettledResolved,
            CaseStatus::Referred => self::ForFurtherActionCfa,
            CaseStatus::Dismissed => self::Closed,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'text-bg-secondary',
            self::UnderAssessment => 'text-bg-primary',
            self::ForMediation => 'text-bg-info',
            self::ForPangkatConciliation => 'text-bg-warning',
            self::ForFurtherActionCfa => 'text-bg-dark',
            self::SettledResolved => 'text-bg-success',
            self::Closed => 'text-bg-secondary',
        };
    }

    public static function kpiStages(): array
    {
        return [
            self::New,
            self::UnderAssessment,
            self::ForMediation,
            self::ForPangkatConciliation,
            self::ForFurtherActionCfa,
        ];
    }
}
