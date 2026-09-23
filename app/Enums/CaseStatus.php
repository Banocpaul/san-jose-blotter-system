<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Pending = 'Pending';
    case UnderInvestigation = 'Under Investigation';
    case ForMediation = 'For Mediation';
    case Settled = 'Settled';
    case Resolved = 'Resolved';
    case Referred = 'Referred';
    case Dismissed = 'Dismissed';
}