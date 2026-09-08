<?php

namespace Database\Seeders;

use App\Models\Committee;
use App\Models\Country;
use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * CRASA's committees, and which member states sit on them.
 *
 * EVERY ROW IS FLAGGED is_sample = true, committees and membership alike.
 *
 * The six names are taken from the approved design screens, where they appear
 * as real CRASA committees: screen 04 names the Spectrum and Frequency
 * Management Committee and the Human and Institutional Capacity Committee, and
 * screens 04 and 12 name Economics, Legal and Regulatory Affairs, Consumer
 * Affairs and Protection, and Postal and Logistics. The names are therefore
 * client-supplied rather than invented.
 *
 * The mandates, chairs and membership are not. They are written here to
 * exercise the relationships and give the committee screens something to
 * render. No chair is assigned, because assigning one would assert something
 * about a named regulator that this build cannot support. Every member state
 * is attached to every committee as a plain member, which is a placeholder
 * shape, not a claim about who actually sits where.
 *
 * Broadcasting has no committee here. That is not an omission: no
 * broadcasting committee appears in any approved screen, and the nullable
 * sector_id already carries the cross-sector cases.
 */
class CommitteeSeeder extends Seeder
{
    /** @var list<array{slug: string, name: string, sector: string|null, mandate: string}> */
    private const COMMITTEES = [
        [
            'slug' => 'spectrum-and-frequency-management',
            'name' => 'Spectrum and Frequency Management',
            'sector' => Sector::TELECOMMUNICATIONS,
            'mandate' => 'Coordinates spectrum allocation and assignment across borders, maintains the regional spectrum allocation plan and leads the regional IMT 2020 deployment roadmap.',
        ],
        [
            'slug' => 'postal-and-logistics',
            'name' => 'Postal and Logistics',
            'sector' => Sector::POSTAL,
            'mandate' => 'Carries the universal service obligation, quality of service monitoring and postal financial inclusion, delivered with the UPU.',
        ],
        [
            'slug' => 'legal-and-regulatory-affairs',
            'name' => 'Legal and Regulatory Affairs',
            'sector' => null,
            'mandate' => 'Drafts the model laws, guidelines and recommendations that member states adopt into national regulation.',
        ],
        [
            'slug' => 'economics',
            'name' => 'Economics',
            'sector' => null,
            'mandate' => 'Covers interconnection, tariff and market analysis across the three sectors, including the indicators reported through the platform.',
        ],
        [
            'slug' => 'consumer-affairs-and-protection',
            'name' => 'Consumer Affairs and Protection',
            'sector' => null,
            'mandate' => 'Develops the consumer rights instruments that give users the same protections in every member state.',
        ],
        [
            'slug' => 'human-and-institutional-capacity',
            'name' => 'Human and Institutional Capacity',
            'sector' => null,
            'mandate' => 'Runs training for regulator staff and the institutional strengthening programme across the fourteen authorities.',
        ],
    ];

    public function run(): void
    {
        $sectors = Sector::pluck('id', 'slug');
        $countryIds = Country::pluck('id');

        foreach (self::COMMITTEES as $details) {
            $committee = Committee::updateOrCreate(
                ['slug' => $details['slug']],
                [
                    'name' => $details['name'],
                    'sector_id' => $details['sector'] === null ? null : $sectors[$details['sector']],
                    'mandate' => $details['mandate'],
                    'chair_authority_id' => null,
                    'is_active' => true,
                    'is_sample' => true,
                ]
            );

            $committee->countries()->sync(
                $countryIds->mapWithKeys(fn (int $id) => [
                    $id => ['role' => 'member', 'is_sample' => true],
                ])->all()
            );
        }
    }
}
