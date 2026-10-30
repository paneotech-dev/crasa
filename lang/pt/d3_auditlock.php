<?php

// Day 3: auditlock. The retention sentence on the audit log page.
return [
    'retention' => 'As entradas do registo de auditoria são mantidas em linha durante :months meses e depois arquivadas durante :years. Não podem ser alteradas nem eliminadas.',
    'years' => ['1' => 'um ano', '2' => 'dois anos', '3' => 'três anos', '4' => 'quatro anos', '5' => 'cinco anos', '6' => 'seis anos', '7' => 'sete anos', '8' => 'oito anos', '9' => 'nove anos', '10' => 'dez anos'],
    'years_n' => ':n anos',
];
