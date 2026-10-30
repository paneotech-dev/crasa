<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Day 3: auditdeny. A 404 a controller raises for a record that exists but
 * that the reader may not open (another member state's, or above their
 * clearance), so the answer does not confirm the record exists. The reader
 * sees the ordinary not-found page; App\Support\AccessRefusals writes the
 * attempt to the audit log with the reason.
 */
class RecordHidden extends NotFoundHttpException
{
    public function __construct(
        public readonly Model $subject,
        public readonly string $reason,
        public readonly ?string $ability = null,
    ) {
        parent::__construct();
    }
}
