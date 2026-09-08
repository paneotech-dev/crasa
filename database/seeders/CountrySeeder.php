<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * The fourteen CRASA member states.
 *
 * Every value here is a verifiable fact, which is why this table carries no
 * is_sample flag. ISO 3166-1 alpha-2 codes are as given in
 * docs/DATA_MODEL.md section 2.
 *
 * joined_at is null throughout. Accession dates to CRASA are not in any
 * source available to this build, and a guessed date on a regulatory
 * platform is worse than no date. The Secretariat supplies them.
 *
 * official_languages lists the platform locales a member state is served in,
 * not the state's full language inventory.
 */
class CountrySeeder extends Seeder
{
    /** @var list<array{iso_code: string, name: string, official_languages: string}> */
    private const MEMBER_STATES = [
        ['iso_code' => 'ao', 'name' => 'Angola', 'official_languages' => 'pt'],
        ['iso_code' => 'bw', 'name' => 'Botswana', 'official_languages' => 'en'],
        ['iso_code' => 'km', 'name' => 'Comoros', 'official_languages' => 'fr'],
        ['iso_code' => 'cd', 'name' => 'DR Congo', 'official_languages' => 'fr'],
        ['iso_code' => 'sz', 'name' => 'Eswatini', 'official_languages' => 'en'],
        ['iso_code' => 'ls', 'name' => 'Lesotho', 'official_languages' => 'en'],
        ['iso_code' => 'mw', 'name' => 'Malawi', 'official_languages' => 'en'],
        ['iso_code' => 'mu', 'name' => 'Mauritius', 'official_languages' => 'en,fr'],
        ['iso_code' => 'mz', 'name' => 'Mozambique', 'official_languages' => 'pt'],
        ['iso_code' => 'na', 'name' => 'Namibia', 'official_languages' => 'en'],
        ['iso_code' => 'za', 'name' => 'South Africa', 'official_languages' => 'en'],
        ['iso_code' => 'tz', 'name' => 'Tanzania', 'official_languages' => 'en'],
        ['iso_code' => 'zm', 'name' => 'Zambia', 'official_languages' => 'en'],
        ['iso_code' => 'zw', 'name' => 'Zimbabwe', 'official_languages' => 'en'],
    ];

    public function run(): void
    {
        foreach (self::MEMBER_STATES as $state) {
            Country::updateOrCreate(['iso_code' => $state['iso_code']], $state);
        }
    }
}
