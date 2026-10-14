<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\DelegateRegistration;
use App\Models\Document;
use App\Models\Folder;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\MediaAsset;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\PageBlock;
use App\Models\Publication;
use App\Models\Report;
use App\Models\Submission;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Remove every demonstration row before go-live.
 *
 * CLAUDE.md: "Sample and demonstration data must be flagged with a boolean
 * and strippable in one admin action before go-live." This is that action.
 *
 * THREE PROPERTIES THIS COMMAND HAS TO HAVE, and all three are tested.
 *
 * It removes everything flagged. Not most of it: a platform that keeps
 * three demonstration figures after a strip is worse than one that keeps
 * all of them, because nobody is looking any more.
 *
 * It leaves the platform working. Every page still resolves, the navigation
 * still works, and pages that lose their content show their designed empty
 * state rather than an error. Emptiness is a state this platform renders on
 * purpose, which is what makes stripping safe.
 *
 * It leaves the reference data alone. The fourteen member states, the three
 * sectors and the indicator taxonomy are not demonstration data even where
 * an authority record attached to one is unverified. Deleting a member state
 * would orphan everything pointing at it.
 *
 * It runs in one transaction, so a failure halfway leaves the platform as it
 * was rather than half stripped.
 */
class StripSampleData extends Command
{
    protected $signature = 'content:strip-samples
                            {--dry-run : Count what would be removed without removing it}
                            {--force : Skip the confirmation}
                            {--only= : Comma-separated record types, e.g. NewsItem,Meeting}';

    protected $description = 'Remove every row flagged as demonstration data, before go-live';

    /**
     * The order matters. Children before parents, so a foreign key never
     * blocks a delete and nothing is orphaned on the way.
     *
     * Public because App\Support\SampleData counts the same tables for the
     * admin dashboard. One list, so a model added here is a model the
     * dashboard starts counting on the same commit.
     *
     * @var list<class-string<Model>>
     */
    public const ORDER = [
        // Deepest first.
        DelegateRegistration::class,
        Submission::class,
        // Announcements are is_sample too (the demonstration notices of 14 October 2026).
        Announcement::class,
        IndicatorValue::class,
        Document::class,
        Report::class,
        PageBlock::class,
        Publication::class,
        NewsItem::class,
        Instrument::class,
        Meeting::class,
        Folder::class,
        MediaAsset::class,
        Tag::class,

        /*
         * Authorities and committees carry is_sample because their details
         * are unverified, not because the organisations are invented. They
         * are stripped last and the operator is warned: removing them
         * leaves fourteen member states with no named regulator.
         */
        Authority::class,
        Committee::class,

        /*
         * Accounts last, because everything that points at one has already
         * gone by here: a demonstration submission, its events and its
         * field comments are stripped several steps above, and the audit
         * log, the version tables and the translations table all null the
         * reference rather than block the delete.
         *
         * handle() refuses to run at all if the strip would take the last
         * administrator who can still sign in. A platform with no way in is
         * a worse outcome than a platform with one demonstration account
         * left in it.
         */
        User::class,
    ];

    public function handle(): int
    {
        $counts = $this->counts();
        $total = array_sum($counts);
        $orphans = $this->orphanedTranslationCount();

        if ($total === 0 && $orphans === 0) {
            $this->info('No demonstration data. Nothing to remove.');

            return self::SUCCESS;
        }

        if ($total === 0) {
            // Nothing flagged, but earlier deletions left translations
            // behind. Worth clearing on its own: an orphan becomes the
            // translation of the next record to reuse its id.
            $this->info('No demonstration data, but '.$orphans.' orphaned translation rows remain.');
        }

        $this->table(
            ['Record type', 'Rows flagged'],
            collect($counts)->filter()->map(fn ($n, $class) => [class_basename($class), $n])->values()->all()
        );

        if ($this->option('dry-run')) {
            $this->line('');

            if ($orphans > 0) {
                $this->line($orphans.' orphaned translation rows would also be swept.');
            }

            $this->info(($total + $orphans).' rows would be removed. Nothing has been changed.');

            return self::SUCCESS;
        }

        if ($this->wouldLockEverybodyOut()) {
            $this->error('Every administrator account on this platform is flagged as demonstration data.');
            $this->error('Create a real administrator with `user:create` first. Nothing has been changed.');

            return self::FAILURE;
        }

        $accounts = $counts[User::class] ?? 0;

        if ($accounts > 0) {
            $this->warn(
                'This includes '.$accounts.' account'.($accounts === 1 ? '' : 's').'. Anyone still signed in '
                .'with one is signed out, and the credentials file for them should be deleted too.'
            );
        }

        $authorities = $counts[Authority::class] ?? 0;
        $committees = $counts[Committee::class] ?? 0;

        // Named separately: once the member states have confirmed their
        // regulators on the confirmation screen, only the committees are
        // left, and a warning about regulators would then be false.
        if ($authorities > 0) {
            $this->warn(
                'This includes '.$authorities.' unconfirmed authorit'.($authorities === 1 ? 'y' : 'ies').'. Removing '
                .($authorities === 1 ? 'it leaves its member state' : 'them leaves those member states')
                .' with no named regulator until CRASA confirms the details on the confirmation screen.'
            );
        }

        if ($committees > 0) {
            $this->warn(
                'This includes '.$committees.' committee'.($committees === 1 ? '' : 's').'. No screen confirms a '
                .'committee yet, so every committee still flagged is removed, with its seats.'
            );
        }

        if (! $this->option('force') && ! $this->confirm('Remove all '.($total + $orphans).' rows?')) {
            $this->line('Nothing has been changed.');

            return self::SUCCESS;
        }

        // One transaction. A failure halfway leaves the platform as it was
        // rather than half stripped.
        $removed = DB::transaction(function () {
            $removed = 0;

            foreach ($this->order() as $class) {
                $removed += $this->strip($class);
            }

            $removed += $this->sweepOrphanedTranslations();

            return $removed;
        });

        $this->info($removed.' demonstration rows removed.');
        $this->line('Run `php artisan content:placeholders` to see what CRASA still has to supply.');

        return self::SUCCESS;
    }

    /**
     * Translations whose record no longer exists.
     *
     * HasTranslations takes a record's translations with it now, so nothing
     * new is orphaned. This sweeps what earlier deletions left behind — a
     * rehearsal of this command on 9 September found 337, from records
     * deleted before the trait knew to clean up and from every reseed that
     * dropped and rebuilt a page's blocks.
     *
     * It matters more than dead rows. A translation is addressed by
     * translatable_type and translatable_id, and ids are reused: an orphan
     * for publication 5 becomes the French title of the next publication to
     * be created as id 5. On a platform whose claim is that its content is
     * sourced, that is a demonstration string appearing under a real
     * document.
     */
    private function sweepOrphanedTranslations(): int
    {
        $removed = $this->eachOrphanedType(
            fn (Builder $query) => $query->delete()
        );

        if ($removed > 0) {
            $this->line($removed.' orphaned translation rows swept.');
        }

        return $removed;
    }

    private function orphanedTranslationCount(): int
    {
        return $this->eachOrphanedType(
            fn (Builder $query) => $query->count()
        );
    }

    /**
     * Run one action over the orphaned rows of each translatable type.
     *
     * @param  \Closure(Builder): int  $action
     */
    private function eachOrphanedType(\Closure $action): int
    {
        $total = 0;

        $types = DB::table('translations')->select('translatable_type')->distinct()->pluck('translatable_type');

        foreach ($types as $type) {
            $query = DB::table('translations')->where('translatable_type', $type);

            if (class_exists($type)) {
                $table = (new $type)->getTable();
                $query->whereNotIn('translatable_id', fn ($q) => $q->select('id')->from($table));
            }
            // A type whose class no longer exists is an orphan in whole:
            // every row for it addresses a model the codebase dropped.

            $total += $action($query);
        }

        return $total;
    }

    /**
     * Whether the strip would leave nobody able to administer the platform.
     *
     * Counted rather than assumed: a suspended administrator cannot sign
     * in, so it is not one, and an account flagged as demonstration data is
     * about to be deleted, so it is not one either.
     */
    private function wouldLockEverybodyOut(): bool
    {
        /*
         * Only when this run would actually take accounts. A --only run
         * that names news and meetings deletes no user, so refusing it
         * for want of a real administrator would block a safe operation
         * on the strength of an unrelated one.
         */
        if (! in_array(User::class, $this->order(), true)) {
            return false;
        }

        $survivors = User::query()
            ->where('role', Role::Administrator)
            ->where('status', UserStatus::Active)
            ->where('is_sample', false)
            ->count();

        $going = User::query()
            ->where('role', Role::Administrator)
            ->where('is_sample', true)
            ->count();

        return $survivors === 0 && $going > 0;
    }

    /**
     * The record types this run touches.
     *
     * WHY --only EXISTS. The legacy import writes CRASA's real news and its
     * real calendar, which makes the seeded demonstration versions of both
     * misleading rather than useful: a newsroom carrying thirty-three real
     * items and six invented ones is worse than either. Retiring those two
     * types is not the same decision as the go-live strip, which also takes
     * the demonstration accounts the workshop runs on.
     *
     * @return list<class-string<Model>>
     */
    private function order(): array
    {
        $only = $this->option('only');

        if ($only === null || trim($only) === '') {
            return self::ORDER;
        }

        $wanted = array_map('trim', explode(',', $only));

        return array_values(array_filter(
            self::ORDER,
            fn (string $class) => in_array(class_basename($class), $wanted, true)
        ));
    }

    /** @return array<class-string<Model>, int> */
    private function counts(): array
    {
        $counts = [];

        foreach ($this->order() as $class) {
            $counts[$class] = $this->query($class)->count();
        }

        return $counts;
    }

    /** @param class-string<Model> $class */
    private function strip(string $class): int
    {
        $rows = $this->query($class)->get();

        foreach ($rows as $row) {
            // A demonstration document leaves its file behind otherwise,
            // and a stripped platform with a full disk of orphaned PDFs is
            // not a stripped platform.
            $this->deleteFiles($row);

            // forceDelete where the model soft deletes: a strip that leaves
            // the rows recoverable has not removed them.
            method_exists($row, 'forceDelete') ? $row->forceDelete() : $row->delete();
        }

        return $rows->count();
    }

    /** @param class-string<Model> $class */
    private function query(string $class)
    {
        $query = $class::query()->where('is_sample', true);

        // Soft-deleted demonstration rows are still demonstration rows.
        if (method_exists($class, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        // The country scope would hide other member states' rows from a
        // console command run by nobody, which is the wrong answer here.
        return $query->withoutGlobalScopes();
    }

    private function deleteFiles(Model $row): void
    {
        if ($row instanceof Document && $row->file_path) {
            Storage::disk('local')->delete($row->file_path);

            if ($row->thumbnail_path) {
                Storage::disk('public')->delete($row->thumbnail_path);
            }
        }

        if ($row instanceof MediaAsset) {
            Storage::disk($row->disk)->delete($row->path);
            Storage::disk($row->disk)->deleteDirectory('media/renditions/'.$row->id);
        }

        if ($row instanceof Report && $row->pdf_path) {
            Storage::disk('local')->delete($row->pdf_path);
        }
    }
}
