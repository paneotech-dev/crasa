<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The home page's "What we do" section (C7) is drawn from the pillars
 * block, which was hidden when the page was a plain stack of blocks. The
 * Concept 3 home needs it shown. Matched by page slug and block type.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->set(true);
    }

    public function down(): void
    {
        $this->set(false);
    }

    private function set(bool $visible): void
    {
        $page = DB::table('pages')->where('slug', 'home')->value('id');

        if ($page === null) {
            return;
        }

        DB::table('page_blocks')
            ->where('page_id', $page)
            ->where('block_type', 'pillars')
            ->update(['is_visible' => $visible, 'updated_at' => now()]);
    }
};
