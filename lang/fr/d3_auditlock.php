<?php

// Day 3: auditlock. The retention sentence on the audit log page.
return [
    'retention' => 'Les entrées du journal d\'audit restent consultables en ligne pendant :months mois, puis sont archivées pendant :years. Elles ne peuvent être ni modifiées ni supprimées.',
    'years' => ['1' => 'un an', '2' => 'deux ans', '3' => 'trois ans', '4' => 'quatre ans', '5' => 'cinq ans', '6' => 'six ans', '7' => 'sept ans', '8' => 'huit ans', '9' => 'neuf ans', '10' => 'dix ans'],
    'years_n' => ':n ans',
];
