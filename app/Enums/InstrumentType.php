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

    public function labelKey(): string
    {
        return 'instruments.type_'.$this->value;
    }

    /**
     * The label, translated.
     *
     * These read as hard-coded English until 10 September 2026, which meant
     * the type of an instrument was the one thing on a French page that
     * could not be in French. The English values in lang/en/instruments.php
     * are the same strings, so nothing that read this changed.
     */
    public function label(): string
    {
        return __($this->labelKey());
    }
}
