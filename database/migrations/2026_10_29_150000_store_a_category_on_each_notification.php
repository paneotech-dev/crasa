<?php

use App\Support\Notifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Day 3, notify: every stored notice carries its category (approval
 * requests, decisions, validation results, documents, meetings,
 * registrations, announcements, other), so the notifications page can
 * filter by type in the query. New notices are written with it; this fills
 * it in on the rows written before, from their type. Data only, no schema
 * change; down() takes the key out again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')->orderBy('id')->select(['id', 'data'])->chunk(500, function ($rows): void {
            foreach ($rows as $row) {
                $data = json_decode((string) $row->data, true);

                if (! is_array($data) || isset($data['category'])) {
                    continue;
                }

                $data['category'] = Notifier::categoryOf($data);
                DB::table('notifications')->where('id', $row->id)->update(['data' => json_encode($data)]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')->orderBy('id')->select(['id', 'data'])->chunk(500, function ($rows): void {
            foreach ($rows as $row) {
                $data = json_decode((string) $row->data, true);

                if (! is_array($data) || ! array_key_exists('category', $data)) {
                    continue;
                }

                unset($data['category'], $data['dedupe']);
                DB::table('notifications')->where('id', $row->id)->update(['data' => json_encode($data)]);
            }
        });
    }
};
