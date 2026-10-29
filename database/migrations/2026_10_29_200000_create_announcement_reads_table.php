<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which member has opened which Secretariat notice (Day 3, member area).
 *
 * The notice list marks a notice as read or unread and flags the recent
 * unread ones as new. One row per reader and notice, written the first
 * time the notice is opened.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_reads')) {
            return;
        }

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
