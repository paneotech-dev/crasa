<?php

// Day 3: auditlock. The retention sentence on the audit log page.
return [
    'retention' => 'Audit entries are kept online for :months months, then archived for :years. They cannot be changed or deleted.',
    'years' => ['1' => 'one year', '2' => 'two years', '3' => 'three years', '4' => 'four years', '5' => 'five years', '6' => 'six years', '7' => 'seven years', '8' => 'eight years', '9' => 'nine years', '10' => 'ten years'],
    'years_n' => ':n years',
];
