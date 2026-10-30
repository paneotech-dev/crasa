<?php

namespace App\Services\MachineTranslation;

/**
 * A driver that can say which model answered, so the audit entry of the
 * drafts records it (for example "rafiki:mistral-small").
 */
interface ReportsModel
{
    /** @return list<string> the models that answered so far */
    public function models(): array;

    /** The fallback the service reported, if any. */
    public function fallback(): ?string;
}
