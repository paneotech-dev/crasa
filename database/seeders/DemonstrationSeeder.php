<?php

namespace Database\Seeders;

use App\Enums\Clearance;
use App\Enums\InstrumentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Folder;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\Meeting;
use App\Models\Publication;
use App\Models\Sector;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\SubmissionFieldComment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Enough data to demonstrate every surface at the validation workshop,
 * 23 to 25 September.
 *
 * TWO RULES, AND THE SECOND IS THE HARDER ONE.
 *
 * Every row is flagged is_sample, so `php artisan content:strip-samples`
 * removes all of it in one action before go-live.
 *
 * And nothing here could be mistaken for a verified figure. The gaps are
 * deliberate and they are the point: a comparator that shows fourteen
 * complete rows demonstrates nothing about how it handles a member state
 * that has not reported, and that is the behaviour a regulator will ask
 * about first.
 *
 * IT CREATES NO ACCOUNTS. Every account on this platform is created through
 * `php artisan user:create`, which prompts for a password rather than
 * setting one, and that includes the accounts the workshop needs. Where a
 * submission has no representative to attach to, this seeder skips it and
 * prints the exact command to create the account, rather than inventing a
 * credential that would then exist on a live public URL.
 *
 * WHAT IS DELIBERATELY LEFT THIN. No meeting is invented for a date CRASA
 * has not given us, so the calendar carries two clearly-labelled
 * demonstration entries rather than a plausible AGM. The same reasoning as
 * everywhere else on this platform: a fabricated date on a regulator's
 * calendar is worse than an empty calendar.
 */
class DemonstrationSeeder extends Seeder
{
    public function run(): void
    {
        $this->supersessionChain();
        $this->publicationLineage();
        $this->indicatorGaps();
        $this->submissionsInEveryState();
        $this->meetingsAndFolders();
    }

    /**
     * A supersession chain three deep.
     *
     * The publications library draws a lineage spine for superseded
     * instruments, and a chain of two shows a line rather than a chain.
     * Three is the shortest length at which the middle of a chain looks
     * different from either end.
     */
    private function supersessionChain(): void
    {
        $country = Country::where('iso_code', 'bw')->first();
        $sector = Sector::where('slug', 'telecommunications')->first()
            ?? Sector::first();

        if ($country === null || $sector === null) {
            return;
        }

        $chain = [
            ['2009', 'Interconnection Guidelines, 2009', InstrumentStatus::Repealed],
            ['2016', 'Interconnection Guidelines, 2016 revision', InstrumentStatus::Repealed],
            ['2023', 'Interconnection and Facilities Leasing Guidelines, 2023', InstrumentStatus::InForce],
        ];

        $previous = null;

        foreach ($chain as [$year, $title, $status]) {
            $instrument = Instrument::updateOrCreate(
                ['slug' => 'bw-interconnection-'.$year],
                [
                    'country_id' => $country->id,
                    'sector_id' => $sector->id,
                    'instrument_type' => 'guideline',
                    'reference_number' => 'BOCRA/IC/'.$year,
                    'enacted_on' => $year.'-06-01',
                    'effective_from' => $year.'-09-01',
                    'status' => $status,
                    'is_sample' => true,
                ]
            );

            $instrument->setTranslations('en', [
                'title' => $title,
                'summary' => 'Demonstration record. Terms on which licensed operators interconnect, '
                    .'and the basis on which facilities are leased between them.',
            ]);

            // firstOrCreate, not updateOrCreate: a version is immutable and
            // updating one throws, which is the whole point of the model.
            $version = InstrumentVersion::firstOrCreate(
                ['instrument_id' => $instrument->id, 'version_number' => 1],
                [
                    'changed_by' => null,
                    'change_summary' => 'Demonstration record.',
                    'payload' => ['title' => $title, 'note' => 'Demonstration record.'],
                ]
            );

            $instrument->update(['current_version_id' => $version->id]);

            // Each one supersedes the one before it, so the spine has a
            // beginning, a middle and an end.
            if ($previous !== null) {
                $previous->update(['superseded_by_id' => $instrument->id]);
            }

            $previous = $instrument;
        }
    }

    /**
     * A publication superseded twice, so the lineage spine has a chain to
     * draw.
     *
     * The publication detail page draws the lineage as its one bold moment,
     * and with two editions it draws a line rather than a chain. Three is
     * the shortest length at which the middle of a chain looks different
     * from either end, which is exactly what a regulator is reading it for.
     *
     * Separate from the instrument chain above: an instrument is a national
     * law with versions, a publication is a regional document that
     * supersedes an earlier one, and the platform treats them differently
     * on purpose.
     */
    private function publicationLineage(): void
    {
        $sector = Sector::where('slug', 'telecommunications')->first()
            ?? Sector::first();

        if ($sector === null) {
            return;
        }

        $editions = [
            ['2015', 'SADC Roaming Guidelines, 2015'],
            ['2019', 'SADC Roaming Guidelines, 2019 revision'],
            ['2024', 'SADC Roaming Guidelines, 2024 revision'],
        ];

        $previous = null;

        foreach ($editions as [$year, $title]) {
            $publication = Publication::updateOrCreate(
                ['slug' => 'sadc-roaming-guidelines-'.$year],
                [
                    'publication_type' => 'guideline',
                    'sector_id' => $sector->id,
                    'published_on' => $year.'-04-15',
                    'is_public' => true,
                    'supersedes_id' => $previous?->id,
                    'is_sample' => true,
                ]
            );

            $publication->setTranslations('en', [
                'title' => $title,
                'summary' => 'Demonstration record. The regional framework for roaming: tariff principles, '
                    .'transparency for the consumer, and reporting by the member states.',
            ]);

            $publication->setTranslations('fr', [
                'title' => str_replace(['SADC', 'revision'], ['SADC', 'revision'], $title),
                'summary' => 'Enregistrement de demonstration. Le cadre regional de l\'itinerance : principes '
                    .'tarifaires, transparence pour le consommateur et rapports des Etats membres.',
            ]);

            $previous = $publication;
        }
    }

    /**
     * Deliberate gaps in the indicator values.
     *
     * Comoros reports nothing at all, which is the live example screen 03
     * names. Two other member states report part of a category. Without
     * gaps the comparator's careful handling of a missing figure has
     * nothing to demonstrate, and that handling is the thing a regulator
     * will test first.
     */
    private function indicatorGaps(): void
    {
        $comoros = Country::where('iso_code', 'km')->first();

        if ($comoros !== null) {
            IndicatorValue::where('country_id', $comoros->id)->delete();
        }

        // Lesotho and Eswatini keep only half of what they had, so the
        // completeness dashboard shows partial rows rather than a wall of
        // full ones and a single empty one.
        foreach (['ls', 'sz'] as $code) {
            $country = Country::where('iso_code', $code)->first();

            if ($country === null) {
                continue;
            }

            IndicatorValue::where('country_id', $country->id)
                ->orderByDesc('id')
                ->take((int) ceil(IndicatorValue::where('country_id', $country->id)->count() / 2))
                ->get()
                ->each->delete();
        }
    }

    /**
     * One submission in each of the four workflow states.
     *
     * Screen 06 draws all four, and a demonstration that can only show a
     * draft cannot show what a returned submission looks like, which is the
     * state that carries the field comments and the state a member state
     * most needs to understand.
     */
    private function submissionsInEveryState(): void
    {
        $indicators = Indicator::active()->orderBy('display_order')->take(4)->get();

        if ($indicators->isEmpty()) {
            return;
        }

        $states = [
            'na' => SubmissionStatus::Draft,
            'zm' => SubmissionStatus::Submitted,
            'bw' => SubmissionStatus::Validated,
            'mw' => SubmissionStatus::Returned,
        ];

        $missing = [];

        foreach ($states as $code => $status) {
            $country = Country::where('iso_code', $code)->first();

            if ($country === null) {
                continue;
            }

            $author = $this->representativeFor($country);

            /*
             * No account, no submission. A return with no author is not a
             * return: the workflow records who filed it and who reviewed it,
             * and a row with nulls in both demonstrates the opposite of what
             * the workflow is for.
             *
             * This seeder creates no accounts. It says which to create.
             */
            if ($author === null) {
                $missing[$country->name] = $code;

                continue;
            }

            $submission = Submission::withoutGlobalScopes()->updateOrCreate(
                ['country_id' => $country->id, 'period' => '2026'],
                [
                    'submitted_by' => $author?->id,
                    'status' => $status,
                    'submitted_at' => $status === SubmissionStatus::Draft ? null : now()->subDays(9),
                    'reviewed_at' => $status->isReviewDecision() ? now()->subDays(3) : null,
                    'review_note' => $status === SubmissionStatus::Returned
                        ? 'Demonstration record. Two figures need supporting data before this can be validated.'
                        : null,
                    'is_sample' => true,
                ]
            );

            foreach ($indicators as $index => $indicator) {
                // The draft is deliberately half filled, so the progress
                // panel beside the form has something to show.
                if ($status === SubmissionStatus::Draft && $index > 1) {
                    continue;
                }

                $submission->values()->updateOrCreate(
                    ['indicator_id' => $indicator->id],
                    [
                        'value_numeric' => round(20 + ($index * 17) + (crc32($code) % 40), 1),
                        'source_note' => 'Demonstration source. '.strtoupper($code).' annual report 2026.',
                    ]
                );
            }

            // The returned one carries comments against specific fields,
            // which is what screen 06 is careful about.
            if ($status === SubmissionStatus::Returned) {
                SubmissionFieldComment::updateOrCreate(
                    ['submission_id' => $submission->id, 'indicator_id' => $indicators->first()->id],
                    [
                        'comment' => 'Demonstration comment. This figure is materially above the 2025 return. '
                            .'Please attach the operator data behind the revision, or correct the figure.',
                        'resolved' => false,
                    ]
                );
            }

            $this->trailFor($submission, $status, $author);

            /*
             * A validated return has published its figures. Seeding the
             * state without the consequence would show a demonstration in
             * which validation apparently does nothing, which is the one
             * thing the workshop needs it to do visibly.
             */
            if ($status === SubmissionStatus::Validated) {
                foreach ($submission->values()->with('indicator')->get() as $value) {
                    IndicatorValue::updateOrCreate(
                        [
                            'indicator_id' => $value->indicator_id,
                            'country_id' => $submission->country_id,
                            'period' => $submission->period,
                        ],
                        [
                            'value_numeric' => $value->value_numeric,
                            'source_note' => $value->source_note,
                            'submission_id' => $submission->id,
                            'confidence' => 'validated',
                            'is_sample' => true,
                        ]
                    );
                }
            }
        }

        $this->reportMissingAccounts($missing);
    }

    /**
     * What the console says when demonstration data is skipped for want of
     * an account.
     *
     * Naming the exact command matters more than the warning does: an
     * operator who is told "some data was skipped" goes looking, and an
     * operator who is handed the command runs it.
     *
     * @param  array<string, string>  $missing  member state name => ISO code
     */
    private function reportMissingAccounts(array $missing): void
    {
        if ($missing === [] || $this->command === null) {
            return;
        }

        $this->command->warn('Submissions were not seeded for '.count($missing).' member state(s).');
        $this->command->line('  This seeder creates no accounts. Create these, then run it again:');
        $this->command->line('');

        foreach ($missing as $name => $code) {
            $this->command->line(
                '    php artisan user:create --role=member_state_rep --country='.$code.'   # '.$name
            );
        }

        $this->command->line('');
        $this->command->line('  The command prompts for the password. See docs/DEMO_SCRIPT.md.');
    }

    /** The events the transitions would have written. */
    private function trailFor(Submission $submission, SubmissionStatus $status, ?User $author): void
    {
        if ($submission->events()->exists() || $status === SubmissionStatus::Draft) {
            return;
        }

        $path = match ($status) {
            SubmissionStatus::Submitted => [['draft', 'submitted']],
            SubmissionStatus::Validated => [['draft', 'submitted'], ['submitted', 'validated']],
            SubmissionStatus::Returned => [['draft', 'submitted'], ['submitted', 'returned']],
            default => [],
        };

        foreach ($path as $i => [$from, $to]) {
            SubmissionEvent::create([
                'submission_id' => $submission->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $author?->id,
                'note' => 'Demonstration record.',
                'created_at' => now()->subDays(9 - ($i * 3)),
            ]);
        }
    }

    /**
     * Meetings and folders across the clearance levels.
     *
     * Two meetings, both plainly labelled as demonstration records, because
     * a fabricated Annual General Meeting date on a regulator's calendar is
     * worse than an empty calendar. Folders at three clearance levels, so
     * the extranet can be shown refusing something.
     */
    private function meetingsAndFolders(): void
    {
        $committee = Committee::first();

        $public = Meeting::updateOrCreate(
            ['slug' => 'demonstration-regional-workshop'],
            [
                'committee_id' => $committee?->id,
                'title' => 'Demonstration record: regional workshop',
                'kind' => 'workshop',
                'starts_on' => now()->addMonths(2)->startOfMonth()->addDays(9),
                'ends_on' => now()->addMonths(2)->startOfMonth()->addDays(11),
                'location' => 'Gaborone, Botswana',
                'agenda' => 'Demonstration record. This entry exists so the calendar and the events section '
                    ."have something to show.\n\nReal meeting dates come from the Secretariat.",
                'clearance' => Clearance::Public,
                'is_sample' => true,
            ]
        );

        Meeting::updateOrCreate(
            ['slug' => 'demonstration-closed-session'],
            [
                'committee_id' => $committee?->id,
                'title' => 'Demonstration record: closed committee session',
                'kind' => 'committee',
                'starts_on' => now()->addMonth()->startOfMonth()->addDays(15),
                'location' => 'Online',
                'clearance' => Clearance::Confidential,
                'is_sample' => true,
            ]
        );

        // A country folder, a committee folder and a Secretariat folder, so
        // the access boundary can be demonstrated by being refused.
        foreach (Country::whereIn('iso_code', ['bw', 'mz'])->get() as $country) {
            $root = Folder::withoutGlobalScopes()->updateOrCreate(
                ['parent_id' => null, 'slug' => strtolower($country->iso_code)],
                [
                    'country_id' => $country->id,
                    'name' => $country->name,
                    'clearance' => Clearance::Member,
                    'is_sample' => true,
                ]
            );

            Folder::withoutGlobalScopes()->updateOrCreate(
                ['parent_id' => $root->id, 'slug' => '2026-cycle'],
                [
                    'country_id' => $country->id,
                    'name' => '2026 cycle',
                    'clearance' => Clearance::Member,
                    'is_sample' => true,
                ]
            );
        }

        if ($committee !== null) {
            Folder::withoutGlobalScopes()->updateOrCreate(
                ['parent_id' => null, 'slug' => 'committee-working-papers'],
                [
                    'committee_id' => $committee->id,
                    'name' => 'Working papers',
                    'clearance' => Clearance::Confidential,
                    'is_sample' => true,
                ]
            );
        }

        Folder::withoutGlobalScopes()->updateOrCreate(
            ['parent_id' => null, 'slug' => 'secretariat-papers'],
            ['name' => 'Secretariat papers', 'clearance' => Clearance::Secretariat, 'is_sample' => true]
        );
    }

    /**
     * The representative a demonstration submission is attributed to, if
     * one already exists.
     *
     * NO SEEDER CREATES AN ACCOUNT ON THIS PLATFORM, and there is a test
     * that enforces it. A seeded credential on a public development URL is
     * how a demonstration password reached a circulated PDF on this
     * project. So a demonstration submission is attributed to a real
     * representative if the Secretariat has invited one, and to nobody if
     * they have not. An unattributed return is a smaller problem than an
     * account nobody created on purpose.
     */
    /**
     * An existing representative for this member state, or nobody.
     *
     * DELIBERATELY A LOOKUP AND NEVER A CREATE. A seeded credential on a
     * public development URL is how a demonstration password reached a
     * circulated PDF on this project once already, and there is a test that
     * greps every seeder to make sure it does not happen again.
     *
     * If the Secretariat has invited accounts before this runs, the
     * demonstration submissions are attributed to the right people. If not,
     * they are attributed to nobody and every screen already renders that
     * as "System", because a null author was always a case the platform had
     * to handle.
     */
    /**
     * An existing representative for this member state, or null.
     *
     * THIS SEEDER CREATES NO ACCOUNTS. Every account on this platform is
     * created through `php artisan user:create`, which prompts for the
     * password rather than setting one, and that includes the accounts the
     * workshop needs.
     *
     * A seeded credential is a credential that exists on a live public URL
     * without anyone having decided it should. On this project one has
     * already reached a circulated PDF. The guard in AuthenticationTest
     * exists for that reason and it caught an earlier version of this
     * method.
     */
    private function representativeFor(Country $country): ?User
    {
        return User::query()
            ->where('country_id', $country->id)
            ->where('role', Role::MemberStateRep)
            ->first();
    }
}
