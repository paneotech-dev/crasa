<?php

namespace App\Enums;

enum PublicationType: string
{
    case AnnualReport = 'annual_report';
    case Guideline = 'guideline';
    case Study = 'study';
    case PositionPaper = 'position_paper';
    case Communique = 'communique';
    case Minutes = 'minutes';

    public function labelKey(): string
    {
        return 'publications.type_'.$this->value;
    }
}
