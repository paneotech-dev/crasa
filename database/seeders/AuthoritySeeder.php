<?php

namespace Database\Seeders;

use App\Models\Authority;
use App\Models\Country;
use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * The fourteen national regulatory authorities.
 *
 * EVERY ROW IS FLAGGED is_sample = true.
 *
 * The names and acronyms below are the regulators this build believes are
 * correct, but none has been checked against the CRASA member register or a
 * live source, and nothing here has been confirmed by the Secretariat. On a
 * platform that will be cited in ministerial briefs, believed-correct is not
 * verified, so the whole set is demonstration data and the go-live purge
 * removes it. The flag comes off a row when the Secretariat confirms it.
 *
 * established_year is null throughout. Founding dates were the least reliable
 * field of the set and a wrong year is worse than a blank one.
 *
 * website is null for Comoros and Eswatini, where the domain is least certain.
 *
 * mandate_sectors from the specification is modelled as the authority_sector
 * pivot. See that migration for why. The mandates below are the least
 * verifiable field of all: several member states split broadcasting or postal
 * regulation across separate bodies, and this build cannot tell which. They
 * are seeded to exercise the relationship, not to assert a fact.
 */
class AuthoritySeeder extends Seeder
{
    private const TEL = Sector::TELECOMMUNICATIONS;

    private const BRO = Sector::BROADCASTING;

    private const POS = Sector::POSTAL;

    /** @var array<string, array{name: string, acronym: string, website: string|null, sectors: list<string>}> */
    private const AUTHORITIES = [
        'ao' => [
            'name' => 'Instituto Angolano das Comunicações',
            'acronym' => 'INACOM',
            'website' => 'https://www.inacom.gov.ao',
            'sectors' => [self::TEL, self::POS],
        ],
        'bw' => [
            'name' => 'Botswana Communications Regulatory Authority',
            'acronym' => 'BOCRA',
            'website' => 'https://www.bocra.org.bw',
            'sectors' => [self::TEL, self::BRO, self::POS],
        ],
        'km' => [
            'name' => "Autorité Nationale de Régulation des Technologies de l'Information et de la Communication",
            'acronym' => 'ANRTIC',
            'website' => null,
            'sectors' => [self::TEL, self::POS],
        ],
        'cd' => [
            'name' => 'Autorité de Régulation de la Poste et des Télécommunications du Congo',
            'acronym' => 'ARPTC',
            'website' => 'https://www.arptc.gouv.cd',
            'sectors' => [self::TEL, self::POS],
        ],
        'sz' => [
            'name' => 'Eswatini Communications Commission',
            'acronym' => 'ESCCOM',
            'website' => null,
            'sectors' => [self::TEL, self::BRO, self::POS],
        ],
        'ls' => [
            'name' => 'Lesotho Communications Authority',
            'acronym' => 'LCA',
            'website' => 'https://www.lca.org.ls',
            'sectors' => [self::TEL, self::BRO, self::POS],
        ],
        'mw' => [
            'name' => 'Malawi Communications Regulatory Authority',
            'acronym' => 'MACRA',
            'website' => 'https://www.macra.mw',
            'sectors' => [self::TEL, self::BRO, self::POS],
        ],
        'mu' => [
            'name' => 'Information and Communication Technologies Authority',
            'acronym' => 'ICTA',
            'website' => 'https://www.icta.mu',
            'sectors' => [self::TEL],
        ],
        'mz' => [
            'name' => 'Instituto Nacional das Comunicações de Moçambique',
            'acronym' => 'INCM',
            'website' => 'https://www.incm.gov.mz',
            'sectors' => [self::TEL, self::POS],
        ],
        'na' => [
            'name' => 'Communications Regulatory Authority of Namibia',
            'acronym' => 'CRAN',
            'website' => 'https://www.cran.na',
            'sectors' => [self::TEL, self::BRO, self::POS],
        ],
        'za' => [
            'name' => 'Independent Communications Authority of South Africa',
            'acronym' => 'ICASA',
            'website' => 'https://www.icasa.org.za',
            'sectors' => [self::TEL, self::BRO, self::POS],
        ],
        'tz' => [
            'name' => 'Tanzania Communications Regulatory Authority',
            'acronym' => 'TCRA',
            'website' => 'https://www.tcra.go.tz',
            'sectors' => [self::TEL, self::BRO, self::POS],
        ],
        'zm' => [
            'name' => 'Zambia Information and Communications Technology Authority',
            'acronym' => 'ZICTA',
            'website' => 'https://www.zicta.zm',
            'sectors' => [self::TEL, self::POS],
        ],
        'zw' => [
            'name' => 'Postal and Telecommunications Regulatory Authority of Zimbabwe',
            'acronym' => 'POTRAZ',
            'website' => 'https://www.potraz.gov.zw',
            'sectors' => [self::TEL, self::POS],
        ],
    ];

    public function run(): void
    {
        $countries = Country::pluck('id', 'iso_code');
        $sectors = Sector::pluck('id', 'slug');

        foreach (self::AUTHORITIES as $isoCode => $details) {
            if (! isset($countries[$isoCode])) {
                $this->command?->warn("No country seeded for {$isoCode}. Skipping its authority.");

                continue;
            }

            $authority = Authority::updateOrCreate(
                ['country_id' => $countries[$isoCode], 'acronym' => $details['acronym']],
                [
                    'name' => $details['name'],
                    'website' => $details['website'],
                    'established_year' => null,
                    'is_sample' => true,
                ]
            );

            $authority->sectors()->sync(
                collect($details['sectors'])->map(fn (string $slug) => $sectors[$slug])->all()
            );
        }
    }
}
