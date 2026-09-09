<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
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
                            {--force : Skip the confirmation}';

    protected $description = 'Remove every row flagged as demonstration data, before go-live';

    /**
     * The order matters. Children before parents, so a foreign key never
     * blocks a delete and nothing is orphaned on the way.
     *
     * @var list<class-string<Model>>
     */
    private const ORDER = [
        // Deepest first.
        DelegateRegistration::class,
        Submission::class,
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
    ];

    public function handle(): int
    {
        $counts = $this->counts();
        $total = array_sum($counts);

        if ($total === 0) {
            $this->info('No demonstration data. Nothing to remove.');

            return self::SUCCESS;
        }

        $this->table(
            ['Record type', 'Rows flagged'],
            collect($counts)->filter()->map(fn ($n, $class) => [class_basename($class), $n])->values()->all()
        );

        if ($this->option('dry-run')) {
            $this->line('');
            $this->info($total.' rows would be removed. Nothing has been changed.');

            return self::SUCCESS;
        }

        $organisations = ($counts[Authority::class] ?? 0) + ($counts[Committee::class] ?? 0);

        if ($organisations > 0) {
            $this->warn(
                'This includes '.$organisations.' authority and committee records. Removing them leaves '
                .'the fourteen member states with no named regulator until CRASA confirms the details.'
            );
        }

        if (! $this->option('force') && ! $this->confirm('Remove all '.$total.' demonstration rows?')) {
            $this->line('Nothing has been changed.');

            return self::SUCCESS;
        }

        // One transaction. A failure halfway leaves the platform as it was
        // rather than half stripped.
        $removed = DB::transaction(function () {
            $removed = 0;

            foreach (self::ORDER as $class) {
                $removed += $this->strip($class);
            }

            return $removed;
        });

        $this->info($removed.' demonstration rows removed.');
        $this->line('Run `php artisan content:placeholders` to see what CRASA still has to supply.');

        return self::SUCCESS;
    }

    /** @return array<class-string<Model>, int> */
    private function counts(): array
    {
        $counts = [];

        foreach (self::ORDER as $class) {
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
