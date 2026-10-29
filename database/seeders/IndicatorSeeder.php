<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * The harmonised indicator taxonomy, RP-11.
 *
 * EVERY DEFINITION HERE IS TAKEN FROM A PUBLISHED SOURCE AND CITES IT.
 * Nothing is written from memory or inferred.
 *
 * This taxonomy goes to fourteen national regulators for confirmation at a
 * validation workshop. A wrong definition is worse than a missing one,
 * because a wrong one gets reported against for years before anyone notices
 * the fourteen returns are not comparable.
 *
 * Two of the six categories in DATA_MODEL section 4 have no indicator here:
 * spectrum and interconnection. No published definition for either was
 * available to this build, and inventing one is precisely what would make
 * the workshop worse rather than better. They are listed in
 * docs/QUESTIONS.md for the Secretariat to source, most likely from the
 * SADC guidelines rather than from ITU or UPU.
 *
 * Sources, both fetched and read rather than recalled:
 *
 *   ITU (2022) Core List of Indicators, March 2022 version, which reproduces
 *   the definitions from ITU (2020) Handbook for the Collection of
 *   Administrative Data on Telecommunications/ICT with page references.
 *   https://www.itu.int/en/ITU-D/Statistics/Documents/coreindicators/Core-List-of-Indicators_March2022.pdf
 *
 *   UPU Postal Statistics, technical notes, which define each heading.
 *   https://www.upu.int/UPU/media/upu/files/UPU/activities/researchAndPublications/descriptionPostalStatisticsTechnicalNotesEn.pdf
 */
class IndicatorSeeder extends Seeder
{
    private const ITU_HANDBOOK = 'ITU (2020) Handbook for the Collection of Administrative Data on Telecommunications/ICT';

    private const ITU_URL = 'https://www.itu.int/en/ITU-D/Statistics/Documents/coreindicators/Core-List-of-Indicators_March2022.pdf';

    private const UPU_NOTES = 'UPU Postal Statistics, technical notes';

    private const UPU_URL = 'https://www.upu.int/UPU/media/upu/files/UPU/activities/researchAndPublications/descriptionPostalStatisticsTechnicalNotesEn.pdf';

    /** @return list<array<string, mixed>> */
    private function indicators(): array
    {
        return [
            // ── ITU, ICT infrastructure and access ────────────────────────
            [
                'slug' => 'fixed-telephone-subscriptions',
                'category' => 'market',
                'sector' => 'telecommunications',
                'name' => 'Fixed-telephone subscriptions per 100 inhabitants',
                'definition' => 'The sum of active analogue fixed-telephone lines, voice-over-IP (VoIP) subscriptions, fixed wireless local loop (WLL) subscriptions, ISDN voice-channel equivalents and fixed public payphones, divided by the population and multiplied by 100. Previously called main telephone lines in operation.',
                'unit' => 'subscriptions_per_100',
                'data_type' => 'numeric',
                'source' => self::ITU_HANDBOOK.', p.25 (ITU Core List A1)',
            ],
            [
                'slug' => 'mobile-cellular-subscriptions',
                'category' => 'market',
                'sector' => 'telecommunications',
                'name' => 'Mobile-cellular telephone subscriptions per 100 inhabitants',
                'definition' => 'Subscriptions to a public mobile-telephone service providing access to the PSTN using cellular technology, split into postpaid subscriptions and active prepaid accounts used in the last three months. Applies to all subscriptions offering voice communications. Excludes data cards and USB modems, public mobile data services, private trunked mobile radio, telepoint, radio paging, M2M and telemetry. Divided by the population and multiplied by 100.',
                'unit' => 'subscriptions_per_100',
                'data_type' => 'numeric',
                'source' => self::ITU_HANDBOOK.', p.43 (ITU Core List A2)',
                'may_exceed_hundred' => true,
            ],
            [
                'slug' => 'fixed-broadband-subscriptions',
                'category' => 'market',
                'sector' => 'telecommunications',
                'name' => 'Fixed-broadband subscriptions per 100 inhabitants',
                'definition' => 'Fixed subscriptions to high-speed access to the public Internet, a TCP/IP connection, at downstream speeds of 256 kbit/s or greater. Includes cable modem, DSL, fibre to the home or building, satellite broadband, terrestrial fixed wireless broadband and fixed WiMAX, both residential and organisational, irrespective of payment method. Excludes access to data communications via mobile-cellular networks. Divided by the population and multiplied by 100.',
                'unit' => 'subscriptions_per_100',
                'data_type' => 'numeric',
                'source' => self::ITU_HANDBOOK.', p.83 (ITU Core List A3)',
            ],
            [
                'slug' => 'active-mobile-broadband-subscriptions',
                'category' => 'market',
                'sector' => 'telecommunications',
                'name' => 'Active mobile-broadband subscriptions per 100 inhabitants',
                'definition' => 'The sum of active handset-based and computer-based mobile-broadband subscriptions to the public Internet. Covers actual subscribers, not potential ones. A subscription must include a recurring fee or have accessed the Internet in the last three months. Includes networks providing download speeds of at least 256 kbit/s; excludes subscriptions with access only to GPRS, EDGE or CDMA 1xRTT. Divided by the population and multiplied by 100.',
                'unit' => 'subscriptions_per_100',
                'data_type' => 'numeric',
                'source' => self::ITU_HANDBOOK.', p.48 (ITU Core List A4)',
                'may_exceed_hundred' => true,
            ],
            [
                'slug' => 'international-bandwidth-per-inhabitant',
                'category' => 'market',
                'sector' => 'telecommunications',
                'name' => 'International bandwidth usage per inhabitant',
                'definition' => 'Average usage of all international links, including optical fibre cables, radio links and traffic processed by satellite ground stations and teleports, averaged over the twelve months of the reference year. Where traffic is asymmetric, the higher of incoming and outgoing is used. All operator types are counted. Divided by the total population.',
                'unit' => 'bits_per_second_per_inhabitant',
                'data_type' => 'numeric',
                'source' => self::ITU_HANDBOOK.', p.74 (ITU Core List A5)',
            ],
            [
                'slug' => 'population-covered-3g',
                'category' => 'universal_access',
                'sector' => 'telecommunications',
                'name' => 'Percentage of the population covered by at least a 3G mobile network',
                'definition' => 'The percentage of inhabitants within range of at least a 3G mobile-cellular signal, whether or not they subscribe. Calculated by dividing the number of inhabitants covered by at least a 3G signal by the total population and multiplying by 100. Excludes people covered only by GPRS, EDGE or CDMA 1xRTT.',
                'unit' => 'percent',
                'data_type' => 'percentage',
                'source' => self::ITU_HANDBOOK.', p.57 (ITU Core List A6)',
            ],
            [
                'slug' => 'multichannel-tv-subscriptions',
                'category' => 'market',
                'sector' => 'broadcasting',
                'name' => 'Multichannel TV subscriptions per 100 inhabitants',
                'definition' => 'Services providing additional TV programming beyond free-to-air terrestrial channels: cable TV, direct-to-home satellite, Internet-protocol TV and digital terrestrial TV. Counted only where additional channels are available on payment of a fee. Divided by the population and multiplied by 100.',
                'unit' => 'subscriptions_per_100',
                'data_type' => 'numeric',
                'source' => self::ITU_HANDBOOK.' (ITU Core List A10)',
            ],

            // ── UPU, postal ──────────────────────────────────────────────
            [
                'slug' => 'licensed-postal-operators',
                'category' => 'licensing',
                'sector' => 'postal',
                'name' => 'Number of licensed, authorised or declared postal operators',
                'definition' => 'Operators providing letter, parcel and express delivery services, which are not necessarily responsible for providing universal services or fulfilling obligations arising from the UPU Acts.',
                'unit' => 'operators',
                'data_type' => 'numeric',
                'source' => self::UPU_NOTES.', heading 2.1',
            ],
            [
                'slug' => 'permanent-post-offices',
                'category' => 'universal_access',
                'sector' => 'postal',
                'name' => 'Number of permanent post offices',
                'definition' => 'All offices open to the public and operating on fixed premises, being the sum of offices staffed by designated operator officials and offices staffed by persons not connected with the designated operator. Excludes mobile post offices.',
                'unit' => 'offices',
                'data_type' => 'numeric',
                'source' => self::UPU_NOTES.', heading 4.1',
            ],
            [
                'slug' => 'letter-post-items-domestic',
                'category' => 'market',
                'sector' => 'postal',
                'name' => 'Letter-post items, domestic service',
                'definition' => 'The total volume of letter-post mail processed by the designated operator in its domestic service. Includes, in principle, both ordinary items and items given special treatment, being registered items, insured letters and newspapers, as well as advertising items and hybrid mail.',
                'unit' => 'items',
                'data_type' => 'numeric',
                'source' => self::UPU_NOTES.', heading 9.2',
            ],
            [
                'slug' => 'postal-parcels-domestic',
                'category' => 'market',
                'sector' => 'postal',
                'name' => 'Postal parcels, domestic service',
                'definition' => 'The number of both ordinary and insured postal parcels processed by the designated operator in its domestic service.',
                'unit' => 'items',
                'data_type' => 'numeric',
                'source' => self::UPU_NOTES.', heading 10.4',
            ],
            [
                'slug' => 'postal-staff-total',
                'category' => 'market',
                'sector' => 'postal',
                'name' => 'Total number of postal staff',
                'definition' => 'The sum of full-time and part-time staff, giving the number of physical persons employed by the designated operator rather than the number of posts filled in full-time equivalents. Excludes people employed by contractors and temporary staff taken on for holiday periods or occasional events.',
                'unit' => 'staff',
                'data_type' => 'numeric',
                'source' => self::UPU_NOTES.', heading 3.1',
            ],
            [
                'slug' => 'postal-transit-time-standard',
                'category' => 'quality',
                'sector' => 'postal',
                'name' => 'Expected end-to-end transit time for domestic priority letter-post',
                'definition' => 'The expected end-to-end transit time for domestic priority letter-post items, usually one, two or three days after posting. Where different standards apply to different routes within the country, the shortest is used. Where multiple standards apply to the same route, the shortest is used.',
                'unit' => 'days',
                'data_type' => 'numeric',
                'source' => self::UPU_NOTES.', heading 8.8',
            ],
            [
                'slug' => 'postal-items-within-standard',
                'category' => 'quality',
                'sector' => 'postal',
                'name' => 'Percentage of letter-post items delivered within the quality standard',
                'definition' => 'The percentage of items actually delivered within the transit-time standard, being the annual result of a domestic quality measurement for the year in question.',
                'unit' => 'percent',
                'data_type' => 'percentage',
                'source' => self::UPU_NOTES.', heading 8.10',
            ],
        ];
    }

    public function run(): void
    {
        $sectors = Sector::pluck('id', 'slug');

        foreach ($this->indicators() as $order => $spec) {
            Indicator::updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'category' => $spec['category'],
                    'name' => $spec['name'],
                    'definition' => $spec['definition'],
                    'unit' => $spec['unit'],
                    'data_type' => $spec['data_type'],
                    'periodicity' => 'annual',
                    'sector_id' => $sectors[$spec['sector']] ?? null,
                    'source_standard' => $spec['source'],
                    'source_url' => str_contains($spec['source'], 'UPU') ? self::UPU_URL : self::ITU_URL,
                    'display_order' => $order,
                    'is_active' => true,
                    'may_exceed_hundred' => $spec['may_exceed_hundred'] ?? false,
                ]
            );
        }
    }
}
