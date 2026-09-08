<?php

namespace App\Console\Commands;

use App\Models\PageBlock;
use Illuminate\Console\Command;

/**
 * Every block still carrying placeholder copy, as one list to send to the
 * Secretariat.
 */
class ListPlaceholderContent extends Command
{
    protected $signature = 'content:placeholders {--csv : Output as CSV for a spreadsheet}';

    protected $description = 'List every block whose copy is placeholder pending real content from CRASA';

    public function handle(): int
    {
        $blocks = PageBlock::with('page')
            ->where('needs_content', true)
            ->get()
            ->sortBy(fn (PageBlock $b) => $b->page?->slug.'-'.$b->position);

        if ($blocks->isEmpty()) {
            $this->info('No placeholder content. Every block carries real copy.');

            return self::SUCCESS;
        }

        $rows = $blocks->map(fn (PageBlock $block) => [
            $block->page?->slug ?? '(orphan)',
            $block->type()->label(),
            $block->content_note ?? '',
        ])->all();

        if ($this->option('csv')) {
            $this->line('page,block,needed');
            foreach ($rows as $row) {
                $this->line('"'.implode('","', array_map(fn ($c) => str_replace('"', '""', $c), $row)).'"');
            }

            return self::SUCCESS;
        }

        $this->table(['Page', 'Block', 'What CRASA needs to supply'], $rows);
        $this->newLine();
        $this->warn($blocks->count().' block(s) carry placeholder copy.');

        return self::SUCCESS;
    }
}
