<?php

namespace Database\Seeders;

use App\Enums\Clearance;
use App\Enums\InstrumentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
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
use App\Support\SampleDocumentPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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
        $this->rehearsalContent();
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
                'summary' => 'Terms on which licensed operators interconnect, '
                    .'and the basis on which facilities are leased between them.',
            ]);

            // firstOrCreate, not updateOrCreate: a version is immutable and
            // updating one throws, which is the whole point of the model.
            $version = InstrumentVersion::firstOrCreate(
                ['instrument_id' => $instrument->id, 'version_number' => 1],
                [
                    'changed_by' => null,
                    'change_summary' => 'Version adopted.',
                    'payload' => ['title' => $title, 'note' => 'Version adopted.'],
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
                'summary' => 'The regional framework for roaming: tariff principles, '
                    .'transparency for the consumer, and reporting by the member states.',
            ]);

            $publication->setTranslations('fr', [
                'title' => str_replace(['SADC', 'revision'], ['SADC', 'revision'], $title),
                'summary' => 'Le cadre régional de l\'itinérance : principes tarifaires, transparence '
                    .'pour le consommateur et rapports des États membres.',
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
                        ? 'Two figures need supporting data before this can be validated.'
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
                        'source_note' => strtoupper($code).' annual report 2026.',
                    ]
                );
            }

            // The returned one carries comments against specific fields,
            // which is what screen 06 is careful about.
            if ($status === SubmissionStatus::Returned) {
                SubmissionFieldComment::updateOrCreate(
                    ['submission_id' => $submission->id, 'indicator_id' => $indicators->first()->id],
                    [
                        'comment' => 'This figure is materially above the 2025 return. '
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
                'note' => null,
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
            ['slug' => 'regional-workshop'],
            [
                'committee_id' => $committee?->id,
                'title' => 'Regional workshop of member regulators',
                'kind' => 'workshop',
                'starts_on' => now()->addMonths(2)->startOfMonth()->addDays(9),
                'ends_on' => now()->addMonths(2)->startOfMonth()->addDays(11),
                'location' => 'Gaborone, Botswana',
                'agenda' => "Opening by the chair of the Executive Committee.\n\nSessions of the standing committees on their work programmes, the annual regulatory return and the regional comparator.\n\nConclusions of the workshop and closing.",
                'clearance' => Clearance::Public,
                'is_sample' => true,
            ]
        );

        Meeting::updateOrCreate(
            ['slug' => 'closed-committee-session'],
            [
                'committee_id' => $committee?->id,
                'title' => 'Closed committee session',
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
     * What the demonstration accounts need so that no page they open is empty.
     *
     * Added after the presentation of 14 October 2026, where the South Africa
     * (ICASA) account opened an empty submissions list, empty folders and an
     * empty announcements page in front of the room. Every row here is
     * is_sample and labelled as a demonstration record, so
     * content:strip-samples removes it.
     *
     * South Africa gets two validated returns, for the two years before the
     * current one: the current year is left open, so the full submission
     * workflow can still be shown from a blank return. Each country, committee
     * and Secretariat folder gets demonstration papers, and three notices are
     * published in English, French and Portuguese.
     */
    private function rehearsalContent(): void
    {
        $za = Country::where('iso_code', 'za')->first();
        $year = (int) now()->year;

        if ($za !== null && ($author = $this->representativeFor($za)) !== null) {
            // Demonstration figures, in the range of South Africa's published
            // ITU series, not its actual returns.
            $figures = [
                'fixed-telephone-subscriptions' => [1.4, 1.2],
                'mobile-cellular-subscriptions' => [168.9, 172.4],
                'fixed-broadband-subscriptions' => [2.6, 3.1],
                'active-mobile-broadband-subscriptions' => [109.5, 114.2],
            ];

            foreach ([$year - 2, $year - 1] as $i => $period) {
                $submission = Submission::withoutGlobalScopes()->updateOrCreate(
                    ['country_id' => $za->id, 'period' => (string) $period],
                    [
                        'submitted_by' => $author->id,
                        'status' => SubmissionStatus::Validated,
                        'submitted_at' => now()->subMonths(12 - ($i * 6))->subDays(9),
                        'reviewed_at' => now()->subMonths(12 - ($i * 6))->subDays(3),
                        'is_sample' => true,
                    ]
                );

                foreach ($figures as $slug => $values) {
                    $indicator = Indicator::where('slug', $slug)->first();
                    if ($indicator === null) {
                        continue;
                    }
                    $value = $submission->values()->updateOrCreate(
                        ['indicator_id' => $indicator->id],
                        ['value_numeric' => $values[$i], 'source_note' => 'ICASA annual report '.$period.'.']
                    );
                    IndicatorValue::updateOrCreate(
                        ['indicator_id' => $indicator->id, 'country_id' => $za->id, 'period' => (string) $period],
                        ['value_numeric' => $value->value_numeric, 'source_note' => $value->source_note, 'submission_id' => $submission->id, 'confidence' => 'validated', 'is_sample' => true]
                    );
                }

                $this->trailFor($submission, SubmissionStatus::Validated, $author);
            }
        }

        // South Africa's folders, like Botswana's and Mozambique's.
        if ($za !== null) {
            $root = Folder::withoutGlobalScopes()->updateOrCreate(
                ['parent_id' => null, 'slug' => 'za'],
                ['country_id' => $za->id, 'name' => $za->rawName(), 'clearance' => Clearance::Member, 'is_sample' => true]
            );
            Folder::withoutGlobalScopes()->updateOrCreate(
                ['parent_id' => $root->id, 'slug' => $year.'-cycle'],
                ['country_id' => $za->id, 'name' => $year.' cycle', 'clearance' => Clearance::Member, 'is_sample' => true]
            );
        }

        // Papers in every folder, so none opens empty, each matching its
        // folder: a country's returns and market review, the cycle's guidance,
        // a committee's working papers, the Secretariat's notes. Multi-page
        // sample PDFs (App\Support\SampleDocumentPdf), all is_sample.
        foreach (Folder::withoutGlobalScopes()->where('is_sample', true)->get() as $folder) {
            $papers = $this->papersFor($folder);
            $keep = [];
            foreach ($papers as $slug => [$title, $subtitle, $sections]) {
                $path = 'extranet/'.$folder->id.'/'.$slug.'.pdf';
                $keep[] = $path;
                Storage::disk('local')->put($path, SampleDocumentPdf::make($title, $subtitle, now()->isoFormat('D MMMM YYYY'), $sections));
                Document::withoutGlobalScopes()->updateOrCreate(
                    ['folder_id' => $folder->id, 'file_path' => $path],
                    [
                        'documentable_type' => Folder::class,
                        'documentable_id' => $folder->id,
                        'country_id' => $folder->country_id,
                        'committee_id' => $folder->committee_id,
                        'original_filename' => $slug.'.pdf',
                        'mime_type' => 'application/pdf',
                        'size_bytes' => Storage::disk('local')->size($path),
                        'thumbnail_path' => null,
                        'locale' => 'en',
                        'clearance' => $folder->clearance,
                        'is_sample' => true,
                    ]
                );
            }
            // Sample papers this seeder wrote before and no longer writes.
            foreach (Document::withoutGlobalScopes()->where('folder_id', $folder->id)->where('is_sample', true)->whereNotIn('file_path', $keep)->where('file_path', 'like', 'extranet/'.$folder->id.'/sample-%')->get() as $stale) {
                Storage::disk('local')->delete($stale->file_path);
                $stale->forceDelete();
            }
        }

        $notices = [
            'annual-returns-window-open' => [
                'committee' => null, 'days' => 2,
                'en' => ['Annual returns window open', 'The window for the annual indicator returns is open. Representatives can file their return from the Submissions page, save it as a draft and submit it when every figure has a source.'],
                'fr' => ['Ouverture de la période des déclarations annuelles', "La période de dépôt des déclarations annuelles d'indicateurs est ouverte. Les représentants peuvent déposer leur déclaration depuis la page Déclarations, l'enregistrer comme brouillon et la soumettre lorsque chaque chiffre est accompagné de sa source."],
                'pt' => ['Período das declarações anuais aberto', 'Está aberto o período das declarações anuais de indicadores. Os representantes podem apresentar a sua declaração na página Declarações, guardá-la como rascunho e submetê-la quando cada valor tiver uma fonte.'],
            ],
            'workshop-papers-in-folders' => [
                'committee' => null, 'days' => 5,
                'en' => ['Workshop papers in the document folders', 'The papers for the regional workshop are in the document folders. Each member state sees its own folder; committee papers are visible to the members of that committee.'],
                'fr' => ['Documents de l\'atelier dans les dossiers', "Les documents de l'atelier régional se trouvent dans les dossiers de documents. Chaque État membre voit son propre dossier ; les documents d'un comité sont visibles par les membres de ce comité."],
                'pt' => ['Documentos do seminário nas pastas', 'Os documentos do seminário regional estão nas pastas de documentos. Cada Estado-Membro vê a sua própria pasta; os documentos de um comité são visíveis para os membros desse comité.'],
            ],
            'spectrum-committee-working-papers' => [
                'committee' => Committee::where('slug', 'spectrum-and-frequency-management')->value('id'), 'days' => 1,
                'en' => ['Spectrum committee working papers', "The working papers for the next session of the Spectrum and Frequency Management Committee are in the committee's folder."],
                'fr' => ['Documents de travail du comité du spectre', 'Les documents de travail de la prochaine session du Comité de gestion du spectre et des fréquences se trouvent dans le dossier du comité.'],
                'pt' => ['Documentos de trabalho do comité do espetro', 'Os documentos de trabalho da próxima sessão do Comité de Gestão do Espetro e das Frequências estão na pasta do comité.'],
            ],
        ];
        foreach ($notices as $slug => $notice) {
            $announcement = Announcement::withoutGlobalScopes()->updateOrCreate(
                ['slug' => $slug],
                ['committee_id' => $notice['committee'], 'status' => 'published', 'published_at' => now()->subDays($notice['days']), 'is_sample' => true]
            );
            foreach (['en', 'fr', 'pt'] as $locale) {
                $announcement->setTranslation('title', $locale, $notice[$locale][0]);
                $announcement->setTranslation('body', $locale, $notice[$locale][1]);
            }
        }
    }

    /**
     * The two sample papers for one folder, matching what the folder is for.
     *
     * @return array<string, array{0: string, 1: string, 2: list<array{heading: string, paragraphs: list<string>}>}>
     */
    private function papersFor(Folder $folder): array
    {
        $year = (int) now()->year;

        if ($folder->country_id !== null) {
            $country = Country::find($folder->country_id);
            $scope = (string) $country?->rawName();
            $common = $this->countryPapers($scope);

            // The cycle folder holds this year's guidance and checklist; the
            // country's own folder its returns and the market review.
            if ($folder->parent_id !== null) {
                return [
                    'sample-annual-return-guidance' => $common['sample-annual-return-guidance'],
                    'sample-return-checklist' => $this->returnChecklist($scope, $year),
                ];
            }

            return [
                'sample-validated-returns' => $this->returnsSummary($country),
                'sample-market-review-summary' => $common['sample-market-review-summary'],
            ];
        }

        if ($folder->committee_id !== null) {
            $committee = (string) Committee::find($folder->committee_id)?->rawName();

            return [
                'sample-committee-working-paper' => $this->committeeWorkingPaper($committee, $year),
                'sample-committee-agenda' => $this->committeeAgenda($committee, $year),
            ];
        }

        return [
            'sample-secretariat-note-workshop' => $this->secretariatWorkshopNote($year),
            'sample-secretariat-note-platform' => $this->secretariatPlatformNote($year),
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: list<array{heading: string, paragraphs: list<string>}>}> */
    private function countryPapers(string $scope): array
    {
        $year = now()->year;

        return [
            'sample-annual-return-guidance' => [
                "Annual regulatory return {$year}: guidance",
                "Guidance note for {$scope}",
                [
                    ['heading' => '1. Purpose of this note', 'paragraphs' => [
                        "This note explains how {$scope} completes the annual regulatory return for {$year} on the CRASA platform. The return collects a small set of harmonised indicators from every member regulator, so that the region can be compared on the same definitions, for the same period, from figures each regulator has verified.",
                        'The return replaces the spreadsheet exchange used in earlier cycles. Each figure is entered once, carries its source, and moves through the same review steps for every member state: draft, submitted, returned for correction where needed, and validated. Only validated figures are published in the regional comparator.',
                    ]],
                    ['heading' => '2. The indicators requested', 'paragraphs' => [
                        'Fixed-telephone subscriptions per 100 inhabitants. Count active analogue lines, voice-over-IP subscriptions, fixed wireless local loop subscriptions, ISDN voice-channel equivalents and fixed public payphones, divided by the population and multiplied by 100.',
                        'Mobile-cellular subscriptions per 100 inhabitants. Count postpaid subscriptions and prepaid accounts active in the last three months that offer voice communications. Exclude data cards, USB modems, M2M and telemetry subscriptions.',
                        'Fixed-broadband subscriptions per 100 inhabitants. Count fixed subscriptions to the public Internet at downstream speeds of 256 kbit/s or more, whatever the technology: DSL, cable, fibre, satellite, fixed wireless and fixed WiMAX.',
                        'Active mobile-broadband subscriptions per 100 inhabitants. Count handset-based and computer-based subscriptions that include a recurring fee or have been used to reach the Internet in the last three months.',
                    ]],
                    ['heading' => '3. Sources and evidence', 'paragraphs' => [
                        'Every figure needs a source note: the operator returns, licensee reports or national statistics it was compiled from, and the date of extraction. Where a figure has been estimated, say how. Where it differs materially from the previous year, explain the change in one or two sentences; the reviewer will otherwise return it with a question.',
                        'Population figures should come from the national statistics office and match the year of the return. Where the office has revised its estimates, use the revised series for both years and note the revision, so that the change in the indicator reflects the market and not the denominator.',
                    ]],
                    ['heading' => '4. Timetable and review', 'paragraphs' => [
                        "The return for {$year} opens with this note. A draft can be saved at any point and completed over several sessions. When every figure has a source, the representative submits the return, and the Secretariat is notified.",
                        'The Secretariat reviews each return against the definitions above and the previous year. A return may be validated as it stands or returned with comments against specific figures. A returned return reopens for editing, keeps the comments beside the figures they concern, and is submitted again when corrected.',
                        'Validated figures are published in the comparator and the indicator pages with the source note shown beside them. Figures that are submitted but not yet validated are visible to the Secretariat and the submitting regulator only.',
                    ]],
                    ['heading' => '5. Contacts', 'paragraphs' => [
                        'Questions on definitions, sources or the review should go to the CRASA Secretariat through the contact form on the platform. Questions on access to the platform, or on who in the regulator holds the representative account, should go to the same address and will be answered by the platform administrator.',
                    ]],
                ],
            ],
            'sample-market-review-summary' => [
                "Electronic communications market review {$year}",
                "Summary paper for {$scope}",
                [
                    ['heading' => '1. Summary', 'paragraphs' => [
                        "This paper summarises the main developments in the electronic communications and postal markets relevant to {$scope} over the past twelve months, as background for the regional discussion. It draws on the indicators reported to CRASA, on published regulator decisions and on operator announcements. It makes no recommendations of its own.",
                        'Three themes recur across the region: the continued growth of mobile broadband, now the main route to the Internet for most users; pressure on wholesale prices for international connectivity as new submarine and terrestrial capacity comes into service; and the reform of postal universal service obligations as letter volumes fall.',
                    ]],
                    ['heading' => '2. Mobile and broadband', 'paragraphs' => [
                        'Mobile-cellular subscriptions per 100 inhabitants are above 100 in most member states, which reflects multiple SIM ownership rather than universal individual access. Mobile-broadband subscriptions continue to grow faster than voice subscriptions, driven by smartphone adoption and lower data prices on prepaid bundles.',
                        'Fixed broadband remains concentrated in urban areas and among businesses. Fibre deployment is extending from the main cities along existing infrastructure corridors, and several regulators are consulting on access obligations for passive infrastructure such as ducts and poles, to reduce the cost of duplicating networks.',
                        '4G population coverage is high where spectrum in the sub-1 GHz bands has been assigned, and lower where rural coverage depends on higher bands alone. The assignment of the 700 MHz and 800 MHz bands, and the conditions attached to it, remains one of the main levers for rural coverage.',
                    ]],
                    ['heading' => '3. Spectrum and cross-border coordination', 'paragraphs' => [
                        'Harmful interference along borders continues to be reported in the broadcasting and mobile bands. The regional frequency allocation plan provides the common framework, and bilateral coordination agreements set out how stations near borders are planned. Regulators are encouraged to share planned assignments near borders before licences are issued.',
                        'The preparation for the next World Radiocommunication Conference is under way, and common regional positions will be discussed in the relevant committee. Member regulators are asked to circulate their national positions in good time so that areas of agreement can be identified early.',
                    ]],
                    ['heading' => '4. Consumers and quality of service', 'paragraphs' => [
                        'Complaints to regulators concern mainly billing, the expiry of prepaid bundles, unsolicited messages and the quality of mobile data services. Several regulators now publish quality-of-service measurements by operator and by area, which gives consumers a basis for comparison and operators an incentive to improve.',
                        'Roaming within the region remains a priority. Regional roaming arrangements have reduced retail prices for voice and data in participating states, and the remaining member states are invited to report the barriers they face in joining them.',
                    ]],
                    ['heading' => '5. Postal services', 'paragraphs' => [
                        'Letter-post volumes continue to decline while parcel volumes grow with electronic commerce. Designated operators are reviewing their networks of permanent post offices, and several regulators are revising the scope and funding of the universal service obligation to reflect this shift.',
                        'Transit-time standards for domestic priority mail are being reviewed against measured performance, so that the published standards match what users actually receive and can be monitored on a common basis across the region.',
                    ]],
                    ['heading' => '6. Points for discussion', 'paragraphs' => [
                        'Members may wish to discuss the sharing of border assignment plans before licensing, the common reporting of quality-of-service measurements, and the timetable for national positions ahead of the next World Radiocommunication Conference.',
                    ]],
                ],
            ],
        ];
    }

    /** The validated returns of one member state, with the figures as filed. */
    private function returnsSummary(?Country $country): array
    {
        $name = (string) $country?->rawName();
        $authority = $country?->authorities()->first()?->acronym ?? 'the national regulator';
        $rows = [];
        foreach (Submission::withoutGlobalScopes()->where('country_id', $country?->id)->where('status', SubmissionStatus::Validated)->orderBy('period')->get() as $submission) {
            $lines = [];
            foreach ($submission->values()->with('indicator')->get() as $value) {
                $lines[] = $value->indicator?->rawName().': '.rtrim(rtrim(number_format((float) $value->value_numeric, 1, '.', ' '), '0'), '.').'. Source: '.$value->source_note;
            }
            $rows[] = ['heading' => 'Return for '.$submission->period.' (validated)', 'paragraphs' => array_merge(
                ['Filed by '.$authority.' and validated by the Secretariat'.($submission->reviewed_at ? ' on '.$submission->reviewed_at->isoFormat('D MMMM YYYY') : '').'. The figures below are those published in the regional comparator.'],
                $lines ?: ['No figures were filed for this period.']
            )];
        }

        return [
            "{$name}: validated annual returns",
            "Figures filed by {$authority} through the CRASA platform",
            array_merge([
                ['heading' => '1. About this paper', 'paragraphs' => [
                    "This paper sets out the annual regulatory returns that {$authority} has filed for {$name} on the CRASA platform and that the Secretariat has validated. Each figure is shown as filed, with its source note. Validated figures are the ones the regional comparator publishes.",
                    'Definitions follow the indicator definitions on the platform, which are those of the ITU for telecommunications and broadcasting and of the UPU for postal services. Any revision of a published figure is made by filing a corrected return, so that the history of the figure stays visible.',
                ]],
            ], $rows ?: [['heading' => 'Returns', 'paragraphs' => ['No return has been validated yet for this member state.']]], [
                ['heading' => 'How to read these figures', 'paragraphs' => [
                    'Subscriptions per 100 inhabitants are counts of subscriptions divided by the population and multiplied by 100. They can exceed 100 where people hold more than one subscription, which is common for mobile services, and they say nothing on their own about how many individuals have access.',
                    'Year-on-year changes reflect the market, but also changes in how operators count active subscriptions, in particular the period of inactivity after which a prepaid account is no longer counted. Where an operator has changed its method, the source note says so, and the change should be read with that in mind.',
                    'Comparisons with other member states are most reliable for the indicators whose definitions have been applied in the same way for several years. The regional comparator shows, for each figure, the period it refers to and whether it has been validated.',
                ]],
                ['heading' => 'The indicators', 'paragraphs' => [
                    'Fixed-telephone subscriptions per 100 inhabitants: active analogue lines, voice-over-IP subscriptions, fixed wireless local loop subscriptions, ISDN voice-channel equivalents and fixed public payphones, divided by the population and multiplied by 100.',
                    'Mobile-cellular subscriptions per 100 inhabitants: postpaid subscriptions and prepaid accounts active in the last three months that offer voice communications, excluding data cards, USB modems, M2M and telemetry subscriptions.',
                    'Fixed-broadband subscriptions per 100 inhabitants: fixed subscriptions to the public Internet at downstream speeds of 256 kbit/s or more, whatever the technology.',
                    'Active mobile-broadband subscriptions per 100 inhabitants: handset-based and computer-based subscriptions with a recurring fee, or used to reach the Internet in the last three months.',
                ]],
                ['heading' => 'How a return is reviewed', 'paragraphs' => [
                    'The Secretariat checks each submitted figure against its definition, its source note and the previous year. A figure may be accepted as filed, or the return may be sent back with a comment against that figure. Comments stay beside the figure they concern until they are resolved.',
                    'Once every figure is accepted, the return is validated and its figures are published. The review history, including who filed and who validated the return and when, is kept with the return on the platform.',
                ]],
                ['heading' => 'Next steps', 'paragraphs' => [
                    'The return for the current year is open on the platform. Figures that change materially from the previous year should carry a short explanation in their source note, which shortens the review.',
                ]],
            ]),
        ];
    }

    private function returnChecklist(string $scope, int $year): array
    {
        return [
            "Annual return {$year}: checklist",
            "Before submitting, for {$scope}",
            [
                ['heading' => '1. Before you start', 'paragraphs' => [
                    'Confirm who in the regulator holds the representative account, and that the person has signed in to the platform at least once. Gather the operator returns, licensee reports and national statistics the figures will come from, with their dates of extraction.',
                    'Check that the population estimate you will use matches the reporting year and comes from the national statistics office. If the office has revised its series, use the revised series for both this year and last year.',
                ]],
                ['heading' => '2. While filling in the return', 'paragraphs' => [
                    'Enter each figure with its source note: what it was compiled from, and when. A figure without a source will be returned by the reviewer. The return can be saved as a draft at any point and completed over several sessions.',
                    'Where a figure differs from last year by more than about ten per cent, add one or two sentences explaining why: a new operator, a change of definition, a data cleaning exercise. The reviewer will otherwise ask.',
                    'Where a figure is estimated, say how. An estimate with a stated method is more useful to the region than a blank.',
                ]],
                ['heading' => '3. Before submitting', 'paragraphs' => [
                    'Read the return through once on screen. Check the units: subscriptions per 100 inhabitants are not counts, and percentages are not fractions. Check that every figure has a source note.',
                    'Submit the return. The Secretariat is notified, and the return is locked until it is reviewed. If it is returned, the comments appear beside the figures they concern, and the return reopens for correction.',
                ]],
                ['heading' => '4. After validation', 'paragraphs' => [
                    'Validated figures are published in the regional comparator and on the indicator pages with their source notes. A correction after validation is made by filing a corrected return, not by editing the published figure.',
                ]],
                ['heading' => '5. Common reasons a return is sent back', 'paragraphs' => [
                    'A figure has no source note, or the note names a source without saying when the data was extracted. The reviewer needs both to compare the figure with other years and other member states.',
                    'A per-100 indicator has been filed as a count, or a count as a per-100 figure. The units are shown beside each field; a mobile subscription figure in the millions is almost always a count entered in the wrong field.',
                    'A large change from the previous year is not explained. A new operator, a change in how prepaid accounts are counted, or a revised population series are all good explanations, but they need to be stated.',
                    'The population used does not match the reporting year, or differs from the one used by the national statistics office. Use the office series, and the same series for both years being compared.',
                    'An estimate is given without its method. Estimates are welcome where no measured figure exists; the method is what makes them usable.',
                ]],
                ['heading' => '6. Help', 'paragraphs' => [
                    'Questions about definitions or sources go to the CRASA Secretariat through the contact form on the platform. The indicator pages on the platform give the full definition of each indicator and the standard it follows.',
                ]],
            ],
        ];
    }

    private function committeeWorkingPaper(string $committee, int $year): array
    {
        return [
            "{$committee} Committee: working paper",
            "Regional priorities for {$year} and ".($year + 1),
            [
                ['heading' => '1. Purpose', 'paragraphs' => [
                    "This working paper sets out, for discussion at the next session of the {$committee} Committee, the regional priorities proposed for {$year} and ".($year + 1).'. It summarises the work completed since the last session, the questions on which members are asked to agree a common position, and the deliverables proposed for the coming period.',
                ]],
                ['heading' => '1a. Background', 'paragraphs' => [
                    'Member regulators regulate markets that are increasingly regional: operators hold licences in several member states, services cross borders, and consumers expect the same protections wherever they are. Regional instruments give regulators a common starting point, while national law decides how each one is applied.',
                    'The committee agreed at its last session to review how the existing regional instrument has been implemented, to identify where national practice has diverged, and to propose revisions where the divergence makes cross-border services harder or leaves consumers with uneven protection.',
                    'The review has drawn on the annual regulatory returns filed on the CRASA platform, on a survey of national practice, and on written comments from member regulators on the first draft of the revised guidelines.',
                ]],
                ['heading' => '2. Work completed since the last session', 'paragraphs' => [
                    'The draft guidelines circulated after the last session received comments from most member regulators. The comments have been consolidated, and a revised draft reflecting them is annexed to the session papers. Points on which comments diverged are listed in section 3.',
                    'The survey of national practice agreed at the last session has been completed. Its results, by member state, are available in the committee folder. Most member regulators have adopted the core provisions of the existing regional instrument, with differences mainly in reporting periods and in the treatment of smaller operators.',
                ]],
                ['heading' => '3. Questions for the committee', 'paragraphs' => [
                    'Whether the revised guidelines should set a common reporting period for all member regulators, or allow national periods with a common annual summary.',
                    'Whether smaller operators should be exempt from some obligations below a threshold, and if so whether the threshold should be set regionally or nationally.',
                    'How the committee should coordinate with the other standing committees where the subject overlaps, in particular on consumer protection and on spectrum matters.',
                ]],
                ['heading' => '4. Proposed deliverables', 'paragraphs' => [
                    'A final draft of the revised guidelines for adoption at the next Annual General Meeting. A short regional report on national implementation, compiled from the annual returns. A capacity-building session for member regulator staff, subject to funding.',
                ]],
                ['heading' => '5. Decision requested', 'paragraphs' => [
                    'The committee is invited to discuss the questions in section 3, to agree the deliverables in section 4, and to mandate the Secretariat to prepare the final draft accordingly.',
                ]],
                ['heading' => 'Annex: summary of comments received', 'paragraphs' => [
                    'Most comments supported the structure of the revised draft and asked for clearer definitions of the terms used in the reporting obligations, so that figures from different member states can be compared without adjustment.',
                    'Several member regulators asked for a transition period before any new reporting obligation applies, to allow operators to adapt their systems. A period of twelve months from adoption was the most common proposal.',
                    'Some comments asked that obligations be proportionate to the size of the operator. Others warned that thresholds can be avoided by structuring a business across several licences. The committee is asked to weigh both concerns (section 3).',
                    'A few comments raised matters outside the scope of the guidelines, such as taxation of services and spectrum fees. These have been noted and passed to the relevant committees.',
                ]],
            ],
        ];
    }

    private function committeeAgenda(string $committee, int $year): array
    {
        return [
            "{$committee} Committee: draft agenda",
            "Next ordinary session, {$year}",
            [
                ['heading' => 'Draft agenda', 'paragraphs' => [
                    '1. Opening and welcome by the chair.',
                    '2. Adoption of the agenda.',
                    '3. Adoption of the record of the previous session.',
                    '4. Matters arising from the record.',
                    '5. Report of the Secretariat on work completed since the previous session.',
                    '6. Working paper: regional priorities for the coming period (for discussion and decision).',
                    '7. Reports from member regulators on national developments (short oral updates).',
                    '8. Coordination with the other standing committees.',
                    '9. Work programme and deliverables for the coming period.',
                    '10. Date and venue of the next session.',
                    '11. Any other business.',
                    '12. Closing.',
                ]],
                ['heading' => 'Annotations', 'paragraphs' => [
                    'Item 3. The record of the previous session was circulated after that session. Members who have corrections are asked to send them to the Secretariat before the session so that the record can be adopted without discussion.',
                    'Item 5. The Secretariat will report on the review of national implementation, the survey of national practice, and the consolidation of comments on the draft guidelines. The written report is in the committee folder.',
                    'Item 6. The working paper sets out the questions on which the committee is asked to agree a common position. The chair will take the questions in the order of the paper and invite a decision on each.',
                    'Item 7. Each member regulator is invited to give a short update, of no more than five minutes, on national developments relevant to the committee. Written updates may be sent to the Secretariat for the record instead.',
                    'Item 8. The chairs of the other standing committees have been invited to identify subjects on which joint work would avoid duplication. Their replies will be summarised at the session.',
                    'Item 9. The work programme will be updated to reflect the decisions taken under item 6 and submitted to the Executive Committee.',
                ]],
                ['heading' => 'Practical arrangements', 'paragraphs' => [
                    'The session papers are in the committee folder on the CRASA platform, which is open to the members of the committee. Members are asked to send any comments on the working paper to the Secretariat at least one week before the session, so that they can be consolidated.',
                    'Members who will attend in person are asked to register for the meeting on the platform. Remote participation will be available; the connection details will be sent to registered participants.',
                ]],
                ['heading' => 'Documents for the session', 'paragraphs' => [
                    'Working paper on regional priorities. Record of the previous session. Report of the Secretariat. Results of the survey of national practice.',
                ]],
            ],
        ];
    }

    private function secretariatWorkshopNote(int $year): array
    {
        return [
            'Secretariat note: regional workshop',
            "Preparations and logistics, {$year}",
            [
                ['heading' => '1. Purpose', 'paragraphs' => [
                    'This note records the preparations for the regional workshop and the decisions still needed from the Executive Committee. It is for the Secretariat and the Executive Committee only.',
                ]],
                ['heading' => '2. Programme', 'paragraphs' => [
                    'The draft programme runs over three days: an opening session with the heads of member regulators; two days of technical sessions organised by the standing committees; and a closing session adopting the workshop conclusions. Each committee has been asked to confirm its session leads and speakers.',
                    'A demonstration of the CRASA platform is scheduled on the first day, covering the public site, the members area, the annual return and the regional comparator. Each member regulator is asked to confirm who holds its representative account before the workshop.',
                ]],
                ['heading' => '3. Participation', 'paragraphs' => [
                    'Delegates register for the workshop on the platform. Registrations are confirmed by the Secretariat, and a confirmation is shown on the meeting page. The Secretariat follows up with member regulators that have not registered a delegate two weeks before the opening.',
                    'Delegates will share the venue connection. The platform has been checked for a room of delegates signing in from one address at the same time.',
                ]],
                ['heading' => '4. Decisions needed', 'paragraphs' => [
                    'Confirmation of the venue and dates. Approval of the draft programme. Confirmation of the budget for interpretation into French and Portuguese.',
                ]],
                ['heading' => '5. Budget and logistics', 'paragraphs' => [
                    'The main costs are the venue, interpretation into French and Portuguese, and travel support for delegates from member regulators that have requested it. Quotations for the venue and interpretation have been received and are summarised for the Executive Committee.',
                    'The host regulator has offered support with local transport and with the reception on the first evening. The Secretariat will confirm the arrangements in writing once the dates are fixed.',
                    'Session papers will be published in the committee folders on the platform rather than printed, with a limited number of printed copies available at the venue.',
                ]],
                ['heading' => '6. Risks', 'paragraphs' => [
                    'Late registration by some member regulators, which makes interpretation and room planning harder. Mitigation: reminders at four and two weeks before the opening, and a registration deadline published on the meeting page.',
                    'Connectivity at the venue for the platform demonstration. Mitigation: a tested connection, a mobile hotspot as fallback, and the demonstration rehearsed in the room on the day before.',
                ]],
            ],
        ];
    }

    private function secretariatPlatformNote(int $year): array
    {
        return [
            'Secretariat note: the CRASA platform',
            "Status and next steps, {$year}",
            [
                ['heading' => '1. Status', 'paragraphs' => [
                    'The platform is in its pilot phase. The public site, the members area, the annual return, the committee workspaces and the regional comparator are in place and have been tested with test accounts.',
                ]],
                ['heading' => '2. Before go-live', 'paragraphs' => [
                    'Representative accounts for each member regulator, created by the Secretariat and enrolled by their holders. Two-factor sign-in for administrators. Delivery of platform email (password resets, notifications and enquiries) through an email service. Review of the French and Portuguese translations by native speakers. Removal of the test content.',
                ]],
                ['heading' => '3. Roles', 'paragraphs' => [
                    'Administrators manage accounts and settings. Editors maintain the public content. Member state representatives file their returns and contributions. Committee members read and contribute to their committee workspace. Viewers read the members area without filing anything.',
                ]],
                ['heading' => '4. Support', 'paragraphs' => [
                    'Questions from member regulators about access or use of the platform are received through the contact form and answered by the Secretariat, with the platform administrator where needed.',
                ]],
                ['heading' => '5. Security and data', 'paragraphs' => [
                    'Access to the members area depends on the role of each account and, for representatives, on their member state: a regulator sees its own returns and folders, and the papers of the committees it sits on. Confidential and Secretariat papers are visible only to those cleared for them.',
                    'Every sign-in, failed sign-in and change to content is recorded in an audit trail that administrators can read. Repeated failed sign-ins pause the account concerned for thirty minutes.',
                    'The platform is backed up every night, and the backups are encrypted so that only the platform owner can read them.',
                ]],
                ['heading' => '6. Training', 'paragraphs' => [
                    'A short guided session for representatives is proposed at the regional workshop, covering signing in, the annual return, the folders and the committee workspaces. A written guide in English, French and Portuguese will be published on the platform.',
                    'Editors at the Secretariat will receive a separate session on the public content, the translations and the publication of news and documents.',
                ]],
                ['heading' => '7. Timeline', 'paragraphs' => [
                    'Pilot phase: the platform is shown to member regulators, and their comments are collected through the Secretariat.',
                    'Preparation for go-live: representative accounts are created and enrolled, email delivery is configured, the translations are reviewed by native speakers, and two-factor sign-in is switched on for administrators.',
                    'Go-live: the test content is removed, the first real annual returns are opened, and the public site replaces the previous website.',
                    'After go-live: a review after the first reporting cycle, covering what representatives found difficult, the quality of the returns, and the changes needed for the second cycle.',
                ]],
            ],
        ];
    }

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
