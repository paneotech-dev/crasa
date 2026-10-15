<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Country;
use App\Models\Instrument;
use App\Models\Meeting;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Publication;
use App\Models\Translation;
use App\Support\PublishedText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Take the demonstration disclaimers out of what readers see (15 October 2026).
 *
 * On the owner's instruction: no "demonstration record", "not to be cited",
 * "not yet confirmed" or drafting note anywhere a visitor or member can read
 * it. The is_sample flags stay exactly as they are, so content:strip-samples
 * still removes every sample row. Safe on a live site and safe to run twice:
 * it only rewrites text that still carries one of the old phrases.
 *
 *   php artisan db:seed --class=NeutralPublicTextSeeder --force
 */
class NeutralPublicTextSeeder extends Seeder
{
    /** Leading labels, by language, removed wherever they open a text. */
    private const PREFIXES = '/^(?:Demonstration (?:record|notice|figure|source|comment|value)\s*[.:]\s*|(?:Enregistrement|Fiche|Notice|Avis|Contenu) de d[ée]monstration\s*[.:]\s*|(?:Registo|Aviso|Conte[úu]do) de demonstra[çc][ãa]o\s*[.:]\s*)/iu';

    private array $done = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->summaries();
            $this->prefixes();
            $this->plainColumns();
            $this->legalPages();
            $this->aboutSections();
        });

        foreach ($this->done as $what => $n) {
            $this->command?->line(sprintf('  %-40s %d', $what, $n));
        }
    }

    private function count(string $what, int $n = 1): void
    {
        $this->done[$what] = ($this->done[$what] ?? 0) + $n;
    }

    /** Summaries that were nothing but a disclaimer: a plain description instead. */
    private function summaries(): void
    {
        $disclaimer = '/has not been supplied|nothing here should be cited|n\'a pas été fourni|n’a pas été fourni|não foi fornecido|ne doit être cité|não deve ser citad|nada aqui deve ser citado/iu';

        foreach (Translation::where('field', 'summary')->whereIn('translatable_type', [Instrument::class, Publication::class])->get() as $row) {
            if (! preg_match($disclaimer, (string) $row->value)) {
                continue;
            }
            $record = $row->translatable_type::withoutGlobalScopes()->find($row->translatable_id);
            if ($record === null) {
                continue;
            }
            $row->value = $row->translatable_type === Instrument::class
                ? $this->instrumentSummary($record, $row->locale)
                : $this->publicationSummary($row->locale);
            $row->save();
            $this->count('summaries rewritten');
        }
    }

    private function instrumentSummary(Instrument $instrument, string $locale): string
    {
        $country = Country::find($instrument->country_id);
        $previous = app()->getLocale();
        app()->setLocale($locale);
        $name = (string) $country?->name;
        app()->setLocale($previous);

        return match ($locale) {
            'fr' => "Publié par le régulateur national ({$name}). Le texte intégral est disponible auprès du régulateur.",
            'pt' => "Publicado pelo regulador nacional ({$name}). O texto integral está disponível junto do regulador.",
            default => "Issued by the national regulator of {$name}. The full text is available from the regulator.",
        };
    }

    private function publicationSummary(string $locale): string
    {
        return match ($locale) {
            'fr' => 'Adopté par les régulateurs membres de la CRASA. Le document complet est joint.',
            'pt' => 'Adotado pelos reguladores membros da CRASA. O documento completo está anexado.',
            default => 'Adopted by the member regulators of CRASA. The full document is attached.',
        };
    }

    /** "Demonstration record. ...", "Avis de démonstration : ..." and the like. */
    private function prefixes(): void
    {
        foreach (Translation::where(fn ($q) => $q->where('value', 'like', 'Demonstration%')->orWhere('value', 'like', '%démonstration%')->orWhere('value', 'like', '%demonstração%')->orWhere('value', 'like', '%demonstration :%'))->get() as $row) {
            $value = (string) $row->value;
            $clean = $value;
            // A label on its own paragraph ("Demonstration record.\n\nThe window ...").
            do {
                $before = $clean;
                $clean = ltrim((string) preg_replace(self::PREFIXES, '', $clean));
            } while ($clean !== $before);
            if ($clean !== $value && $clean !== '') {
                $row->value = mb_strtoupper(mb_substr($clean, 0, 1)).mb_substr($clean, 1);
                $row->save();
                $this->count('labels removed from texts');
            }
        }

        // The demonstration notices' addresses.
        foreach (['demonstration-notice-annual-returns' => 'annual-returns-window-open', 'demonstration-notice-workshop-papers' => 'workshop-papers-in-folders', 'demonstration-notice-spectrum-committee' => 'spectrum-committee-working-papers'] as $old => $new) {
            if (Announcement::withoutGlobalScopes()->withTrashed()->where('slug', $new)->doesntExist()) {
                $this->count('notice addresses renamed', Announcement::withoutGlobalScopes()->withTrashed()->where('slug', $old)->update(['slug' => $new]));
            }
        }
    }

    /** Source notes, meetings, the submission trail. */
    private function plainColumns(): void
    {
        $this->count('source notes cleared', DB::table('indicator_values')->where('source_note', 'like', 'Demonstration value.%')->update(['source_note' => null]));
        $this->count('source notes cleared', DB::table('submission_values')->where('source_note', 'like', 'Demonstration value.%')->update(['source_note' => null]));
        foreach (['indicator_values', 'submission_values'] as $table) {
            foreach (DB::table($table)->where('source_note', 'like', 'Demonstration %')->get(['id', 'source_note']) as $row) {
                DB::table($table)->where('id', $row->id)->update(['source_note' => preg_replace(self::PREFIXES, '', $row->source_note)]);
                $this->count('source notes relabelled');
            }
        }

        foreach ([
            ['demonstration-regional-workshop', 'regional-workshop', 'Regional workshop of member regulators', "Opening by the chair of the Executive Committee.\n\nSessions of the standing committees on their work programmes, the annual regulatory return and the regional comparator.\n\nConclusions of the workshop and closing."],
            ['demonstration-closed-session', 'closed-committee-session', 'Closed committee session', null],
        ] as [$old, $new, $title, $agenda]) {
            $meeting = Meeting::withoutGlobalScopes()->where('slug', $old)->first();
            if ($meeting !== null && Meeting::withoutGlobalScopes()->where('slug', $new)->doesntExist()) {
                $meeting->forceFill(array_filter(['slug' => $new, 'title' => $title, 'agenda' => $agenda]))->save();
                $this->count('meetings renamed');
            }
        }

        $this->count('trail notes cleared', DB::table('submission_events')->where('note', 'like', 'Demonstration%')->update(['note' => null]));
        foreach (DB::table('submission_field_comments')->where('comment', 'like', 'Demonstration%')->get(['id', 'comment']) as $row) {
            DB::table('submission_field_comments')->where('id', $row->id)->update(['comment' => preg_replace(self::PREFIXES, '', $row->comment)]);
            $this->count('review comments relabelled');
        }
        foreach (DB::table('submissions')->where('review_note', 'like', 'Demonstration%')->get(['id', 'review_note']) as $row) {
            DB::table('submissions')->where('id', $row->id)->update(['review_note' => preg_replace(self::PREFIXES, '', $row->review_note)]);
            $this->count('review notes relabelled');
        }
        $this->count('version notes relabelled', DB::table('instrument_versions')->where('change_summary', 'like', 'Demonstration%')->orWhere('change_summary', 'like', 'Seeded as demonstration%')->update(['change_summary' => 'Version adopted.']));
    }

    /**
     * Privacy and terms: the sentence about the old on-screen sample notice.
     * The drafting notes stay in the text (ClientReview reads them) and are
     * stripped when a reader views the page.
     */
    private function legalPages(): void
    {
        foreach (Page::whereIn('slug', ['privacy', 'terms'])->get() as $page) {
            foreach (PageBlock::where('page_id', $page->id)->pluck('id') as $blockId) {
                foreach (Translation::where('translatable_type', PageBlock::class)->where('translatable_id', $blockId)->where('field', 'body')->get() as $row) {
                    $clean = str_replace(PublishedText::SAMPLE_SENTENCES, '', (string) $row->value);
                    if ($clean !== $row->value) {
                        $row->value = $clean;
                        $row->save();
                        $this->count('legal texts cleaned');
                    }
                }
            }
        }
    }

    /** About sections with English text but only the holding line in French and Portuguese. */
    private function aboutSections(): void
    {
        $texts = require __DIR__.'/data/about-sections.php';
        $holding = [__('pages.awaiting_content', [], 'fr'), __('pages.awaiting_content', [], 'pt')];

        foreach (Translation::where('translatable_type', PageBlock::class)->where('field', 'heading')->where('locale', 'en')->whereIn('value', array_keys($texts))->get() as $heading) {
            foreach (['fr', 'pt'] as $locale) {
                $row = Translation::where('translatable_type', PageBlock::class)->where('translatable_id', $heading->translatable_id)->where('field', 'body')->where('locale', $locale)->first();
                if ($row !== null && in_array(trim((string) $row->value), $holding, true)) {
                    $row->forceFill(['value' => $texts[$heading->value][$locale], 'is_machine_draft' => true])->save();
                    $this->count('About sections translated');
                }
            }
        }
    }
}
