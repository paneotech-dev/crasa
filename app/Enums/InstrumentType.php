<?php

namespace App\Enums;

enum InstrumentType: string
{
    case Act = 'act';
    case Regulation = 'regulation';
    case LicenceFramework = 'licence_framework';
    case Guideline = 'guideline';
    case Directive = 'directive';
    case Policy = 'policy';

    public function label(): string
    {
        return match ($this) {
            self::Act => 'Act',
            self::Regulation => 'Regulation',
            self::LicenceFramework => 'Licence framework',
            self::Guideline => 'Guideline',
            self::Directive => 'Directive',
            self::Policy => 'Policy',
        };
    }
}
