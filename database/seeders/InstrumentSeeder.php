<?php

namespace Database\Seeders;

use App\Enums\InstrumentStatus;
use App\Enums\InstrumentType;
use App\Models\Country;
use App\Models\Instrument;
use App\Models\Sector;
use App\Services\InstrumentVersioning;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Thirty demonstration instruments across all fourteen member states and the
 * three sectors.
 *
 * EVERY ROW IS is_sample = true. None of these is a real citation. The
 * titles are shaped like the instruments a communications regulator issues
 * so the library, the filters and the document rows can be judged on real
 * proportions, but no reference number, enactment date or text is asserted
 * as fact. Reference numbers are deliberately null rather than invented:
 * a plausible-looking citation is the one field a reader would trust
 * without checking.
 *
 * Roughly a third carry French and Portuguese titles, so the English
 * fallback path is visible in the interface rather than theoretical. The
 * lusophone and francophone member states get their own language first,
 * which is where a reader would actually meet the fallback.
 */
class InstrumentSeeder extends Seeder
{
    /** @var list<array{sector: string, type: string, title: string}> */
    private const SUBJECTS = [
        ['sector' => 'telecommunications', 'type' => 'guideline', 'title' => 'Mobile termination rate determination'],
        ['sector' => 'telecommunications', 'type' => 'regulation', 'title' => 'Number portability regulations'],
        ['sector' => 'telecommunications', 'type' => 'licence_framework', 'title' => 'Unified licensing framework'],
        ['sector' => 'telecommunications', 'type' => 'guideline', 'title' => 'Quality of service monitoring guidelines'],
        ['sector' => 'telecommunications', 'type' => 'policy', 'title' => 'National spectrum allocation plan'],
        ['sector' => 'telecommunications', 'type' => 'directive', 'title' => 'SIM registration directive'],
        ['sector' => 'telecommunications', 'type' => 'regulation', 'title' => 'Interconnection and infrastructure sharing regulations'],
        ['sector' => 'telecommunications', 'type' => 'guideline', 'title' => 'International roaming tariff guidelines'],
        ['sector' => 'telecommunications', 'type' => 'act', 'title' => 'Electronic communications act'],
        ['sector' => 'telecommunications', 'type' => 'policy', 'title' => 'IMT 2020 deployment roadmap'],
        ['sector' => 'broadcasting', 'type' => 'licence_framework', 'title' => 'Broadcasting licence categories and conditions'],
        ['sector' => 'broadcasting', 'type' => 'regulation', 'title' => 'Local content quota regulations'],
        ['sector' => 'broadcasting', 'type' => 'guideline', 'title' => 'Digital terrestrial television migration guidelines'],
        ['sector' => 'broadcasting', 'type' => 'directive', 'title' => 'Broadcasting infrastructure sharing directive'],
        ['sector' => 'broadcasting', 'type' => 'policy', 'title' => 'Community broadcasting policy'],
        ['sector' => 'broadcasting', 'type' => 'regulation', 'title' => 'Advertising standards for broadcast media'],
        ['sector' => 'broadcasting', 'type' => 'act', 'title' => 'Broadcasting services act'],
        ['sector' => 'postal', 'type' => 'regulation', 'title' => 'Universal postal service obligation regulations'],
        ['sector' => 'postal', 'type' => 'guideline', 'title' => 'Postal quality of service standards'],
        ['sector' => 'postal', 'type' => 'licence_framework', 'title' => 'Courier and express operator licensing framework'],
        ['sector' => 'postal', 'type' => 'policy', 'title' => 'Postal financial inclusion policy'],
        ['sector' => 'postal', 'type' => 'directive', 'title' => 'Address infrastructure and postcode directive'],
        ['sector' => 'postal', 'type' => 'guideline', 'title' => 'Cross-border e-commerce parcel guidelines'],
        ['sector' => 'postal', 'type' => 'act', 'title' => 'Postal services act'],
        ['sector' => 'telecommunications', 'type' => 'guideline', 'title' => 'Consumer complaint handling guidelines'],
        ['sector' => 'telecommunications', 'type' => 'regulation', 'title' => 'Data protection and lawful interception regulations'],
        ['sector' => 'broadcasting', 'type' => 'guideline', 'title' => 'Accessibility requirements for broadcast services'],
        ['sector' => 'postal', 'type' => 'regulation', 'title' => 'Permanent postal outlet coverage regulations'],
        ['sector' => 'telecommunications', 'type' => 'policy', 'title' => 'Universal access and service fund policy'],
        ['sector' => 'broadcasting', 'type' => 'directive', 'title' => 'Spectrum release for digital dividend'],
    ];

    /**
     * Titles in the other two languages, for the third of the set that is
     * translated. Keyed by the English title.
     *
     * @var array<string, array{fr?: string, pt?: string}>
     */
    private const TRANSLATED = [
        'Mobile termination rate determination' => [
            'fr' => 'Détermination des tarifs de terminaison mobile',
            'pt' => 'Determinação das tarifas de terminação móvel',
        ],
        'Number portability regulations' => [
            'fr' => 'Règlement sur la portabilité des numéros',
            'pt' => 'Regulamento sobre a portabilidade de números',
        ],
        'Unified licensing framework' => [
            'fr' => 'Cadre de licence unifié',
            'pt' => 'Quadro de licenciamento unificado',
        ],
        'National spectrum allocation plan' => [
            'fr' => 'Plan national d\'attribution des fréquences',
            'pt' => 'Plano nacional de atribuição de espectro',
        ],
        'Quality of service monitoring guidelines' => [
            'fr' => 'Lignes directrices sur le suivi de la qualité de service',
        ],
        'International roaming tariff guidelines' => [
            'pt' => 'Diretrizes tarifárias de roaming internacional',
        ],
        'Universal postal service obligation regulations' => [
            'fr' => 'Règlement sur l\'obligation de service postal universel',
            'pt' => 'Regulamento da obrigação de serviço postal universal',
        ],
        'Postal quality of service standards' => [
            'pt' => 'Normas de qualidade do serviço postal',
        ],
        'Digital terrestrial television migration guidelines' => [
            'fr' => 'Lignes directrices sur la migration vers la télévision numérique terrestre',
        ],
        'Local content quota regulations' => [
            'pt' => 'Regulamento de quotas de conteúdo local',
        ],
        'Postal financial inclusion policy' => [
            'fr' => 'Politique d\'inclusion financière postale',
            'pt' => 'Política de inclusão financeira postal',
        ],
    ];

    public function run(): void
    {
        $countries = Country::orderBy('id')->get();
        $sectors = Sector::pluck('id', 'slug');
        $versioning = app(InstrumentVersioning::class);

        if ($countries->isEmpty() || $sectors->isEmpty()) {
            $this->command?->warn('Reference data is not seeded. Run ReferenceDataSeeder first.');

            return;
        }

        foreach (self::SUBJECTS as $index => $subject) {
            // Spread across the fourteen member states rather than clustering
            // on one, so the library filters have something to filter.
            $country = $countries[$index % $countries->count()];

            $instrument = Instrument::updateOrCreate(
                ['slug' => Str::slug($country->iso_code.'-'.$subject['title'])],
                [
                    'country_id' => $country->id,
                    'sector_id' => $sectors[$subject['sector']],
                    'instrument_type' => InstrumentType::from($subject['type']),
                    // Never invented. A plausible citation is the one field a
                    // reader would trust without checking.
                    'reference_number' => null,
                    'enacted_on' => null,
                    'effective_from' => null,
                    'status' => $index % 9 === 0 ? InstrumentStatus::Draft : InstrumentStatus::InForce,
                    'is_sample' => true,
                ]
            );

            $text = ['en' => [
                'title' => $subject['title'],
                'summary' => 'Issued by the national regulator of '.$country->rawName().'. The full text is available from the regulator.',
            ]];

            foreach (self::TRANSLATED[$subject['title']] ?? [] as $locale => $title) {
                $text[$locale] = ['title' => $title];
            }

            $versioning->record($instrument, 'Version adopted.', $text);
        }
    }
}
