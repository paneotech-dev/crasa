<?php

namespace Database\Seeders;

use App\Enums\Clearance;
use App\Enums\PublicationType;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Publication;
use App\Models\Sector;
use App\Support\SampleDocumentPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demonstration publications. EVERY ROW IS is_sample = true.
 *
 * Shaped like the regional instruments a regulator association publishes so
 * the library's facets, rows and supersession lineage can be judged on real
 * proportions. No adoption date, decision or text is asserted as fact.
 *
 * One deliberate supersession chain of three, so the lineage view has
 * something to show. A superseded document that is a dead end is the failure
 * this feature exists to prevent.
 */
class PublicationSeeder extends Seeder
{
    /** @var list<array{slug: string, type: string, sector: ?string, year: int, en: string, fr: ?string, pt: ?string}> */
    private const PUBLICATIONS = [
        ['slug' => 'sadc-roaming-guidelines-2019', 'type' => 'guideline', 'sector' => 'telecommunications', 'year' => 2019,
            'en' => 'SADC roaming guidelines, first edition', 'fr' => 'Lignes directrices de la SADC sur l\'itinérance, première édition', 'pt' => 'Diretrizes de roaming da SADC, primeira edição'],
        ['slug' => 'sadc-roaming-guidelines-2022', 'type' => 'guideline', 'sector' => 'telecommunications', 'year' => 2022,
            'en' => 'SADC roaming guidelines, second edition', 'fr' => 'Lignes directrices de la SADC sur l\'itinérance, deuxième édition', 'pt' => null],
        ['slug' => 'sadc-roaming-guidelines-2025', 'type' => 'guideline', 'sector' => 'telecommunications', 'year' => 2025,
            'en' => 'SADC roaming guidelines, third edition', 'fr' => null, 'pt' => null],
        ['slug' => 'spectrum-policy-model-framework', 'type' => 'guideline', 'sector' => 'telecommunications', 'year' => 2021,
            'en' => 'SADC radio frequency spectrum policy model framework', 'fr' => 'Cadre modèle de politique du spectre radioélectrique de la SADC', 'pt' => null],
        ['slug' => 'postal-service-access-guidelines', 'type' => 'guideline', 'sector' => 'postal', 'year' => 2016,
            'en' => 'Postal service and access guidelines', 'fr' => null, 'pt' => 'Diretrizes de serviço e acesso postal'],
        ['slug' => 'broadcasting-infrastructure-sharing', 'type' => 'guideline', 'sector' => 'broadcasting', 'year' => 2020,
            'en' => 'Broadcasting infrastructure sharing guidelines', 'fr' => null, 'pt' => null],
        ['slug' => 'annual-report-2024', 'type' => 'annual_report', 'sector' => null, 'year' => 2024,
            'en' => 'Annual report', 'fr' => 'Rapport annuel', 'pt' => 'Relatório anual'],
        ['slug' => 'consumer-protection-position-paper', 'type' => 'position_paper', 'sector' => null, 'year' => 2023,
            'en' => 'Consumer protection in converged markets', 'fr' => null, 'pt' => null],
        ['slug' => 'universal-access-study-2022', 'type' => 'study', 'sector' => null, 'year' => 2022,
            'en' => 'Universal access and service funds in the region', 'fr' => null, 'pt' => null],
        ['slug' => 'agm-communique-2025', 'type' => 'communique', 'sector' => null, 'year' => 2025,
            'en' => 'Communique of the Annual General Meeting', 'fr' => 'Communiqué de l\'Assemblée générale annuelle', 'pt' => null],
    ];

    private const DEMO_SUMMARY = [
        'en' => 'Adopted by the member regulators of CRASA. The full document is attached.',
        'fr' => 'Adopté par les régulateurs membres de la CRASA. Le document complet est joint.',
        'pt' => 'Adotado pelos reguladores membros da CRASA. O documento completo está anexado.',
    ];

    public function run(): void
    {
        $sectors = Sector::pluck('id', 'slug');
        $countries = Country::orderBy('id')->get();
        $committees = Committee::orderBy('id')->get();
        $created = [];

        foreach (self::PUBLICATIONS as $index => $spec) {
            $publication = Publication::updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'publication_type' => PublicationType::from($spec['type']),
                    'sector_id' => $spec['sector'] === null ? null : ($sectors[$spec['sector']] ?? null),
                    // Regional unless it belongs to one member state. Most
                    // regional instruments are exactly that.
                    'country_id' => $index % 4 === 3 && $countries->isNotEmpty()
                        ? $countries[$index % $countries->count()]->id
                        : null,
                    'committee_id' => $committees->isNotEmpty() ? $committees[$index % $committees->count()]->id : null,
                    'published_on' => $spec['year'].'-06-01',
                    'is_public' => true,
                    'is_sample' => true,
                ]
            );

            foreach (['en', 'fr', 'pt'] as $locale) {
                if ($spec[$locale] !== null) {
                    $publication->setTranslations($locale, [
                        'title' => $spec[$locale],
                        'summary' => self::DEMO_SUMMARY[$locale],
                    ]);
                }
            }

            $this->attachDocument($publication);
            $created[$spec['slug']] = $publication;
        }

        // The supersession chain: 2019 replaced by 2022, replaced by 2025.
        $created['sadc-roaming-guidelines-2022']->update([
            'supersedes_id' => $created['sadc-roaming-guidelines-2019']->id,
        ]);
        $created['sadc-roaming-guidelines-2025']->update([
            'supersedes_id' => $created['sadc-roaming-guidelines-2022']->id,
        ]);
    }

    private function attachDocument(Publication $publication): void
    {
        $filename = $publication->slug.'.pdf';
        $path = 'documents/'.$filename;

        /*
         * The file first, and whether or not the row exists.
         *
         * A restore rehearsal on 9 September 2026 found this: restoring the
         * database brings the document rows back and does not bring the
         * files back, so every download 404s while every page looks
         * healthy. Re-running this seeder could not heal it either, because
         * it returned early on finding the row.
         *
         * That is the exact failure docs/BACKUP_SETUP.md tells the reader to
         * check for by downloading a file after a restore. A demonstration
         * document whose row survived without its file is a repairable
         * state, so it repairs.
         */
        // A multi-page sample (14 October 2026), written when the file is
        // missing or is still the one-line placeholder of earlier builds.
        $existing = Storage::disk('local')->exists($path) ? Storage::disk('local')->get($path) : null;
        if (SampleDocumentPdf::isPlaceholder($existing)) {
            Storage::disk('local')->put($path, SampleDocumentPdf::forPublication(
                (string) $publication->translated('title', $publication->slug, 'en'),
                $publication->publication_type->value,
                (int) $publication->published_on?->year ?: (int) now()->year,
            ));
        }

        if ($publication->documents()->exists()) {
            return;
        }

        Document::create([
            'documentable_type' => Publication::class,
            'documentable_id' => $publication->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'size_bytes' => Storage::disk('local')->size($path),
            'locale' => 'en',
            'clearance' => Clearance::Public,
            'is_sample' => true,
        ]);
    }
}
