<?php

namespace App\Services;

use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;

/**
 * Writes instrument text as versions.
 *
 * Translatable text is never updated in place. Every edit snapshots the full
 * set of translatable fields in every language into a new version row and
 * points the instrument at it. The text as it stood on any date stays
 * retrievable, which is what a regulator citing an instrument needs.
 */
class InstrumentVersioning
{
    /**
     * Record a new version from the instrument's current translations.
     *
     * @param  array<string, array<string, string|null>>|null  $newText  locale => field => value
     */
    public function record(
        Instrument $instrument,
        string $changeSummary,
        ?array $newText = null,
        ?int $changedBy = null,
    ): InstrumentVersion {
        return DB::transaction(function () use ($instrument, $changeSummary, $newText, $changedBy) {
            if ($newText !== null) {
                foreach ($newText as $locale => $fields) {
                    $instrument->setTranslations($locale, $fields, $changedBy);
                }

                $instrument->unsetRelation('translations');
            }

            $version = InstrumentVersion::create([
                'instrument_id' => $instrument->getKey(),
                'version_number' => $this->nextVersionNumber($instrument),
                'changed_by' => $changedBy ?? auth()->id(),
                'change_summary' => $changeSummary,
                'payload' => $this->snapshot($instrument),
            ]);

            $instrument->forceFill(['current_version_id' => $version->getKey()])->saveQuietly();

            return $version;
        });
    }

    private function nextVersionNumber(Instrument $instrument): int
    {
        return (int) InstrumentVersion::where('instrument_id', $instrument->getKey())
            ->max('version_number') + 1;
    }

    /**
     * A full snapshot of every translatable field in every language.
     *
     * @return array<string, array<string, string|null>>
     */
    public function snapshot(Instrument $instrument): array
    {
        $payload = [];

        foreach (Locales::SUPPORTED as $locale) {
            foreach ($instrument->translatableFields() as $field) {
                $value = $instrument->translationValuesFor($field)[$locale] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $payload[$locale][$field] = $value;
                }
            }
        }

        return $payload;
    }
}
