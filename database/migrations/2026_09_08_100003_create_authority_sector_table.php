<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which sectors each authority regulates.
 *
 * DEVIATION from docs/DATA_MODEL.md section 2, flagged for a decision.
 *
 * The specification gives `authorities.mandate_sectors` as a MySQL SET
 * column. Two reasons this is a pivot instead:
 *
 * 1. SET exists only in MySQL and MariaDB. Laravel's SQLite grammar has no
 *    typeSet, so the column cannot be created in the test database, and this
 *    account has no CREATE DATABASE grant for a MariaDB test schema. A SET
 *    would leave the authorities table untestable.
 * 2. CLAUDE.md requires a normalised schema with referential integrity
 *    enforced. A SET enforces nothing against the sectors table, and the
 *    comparator and completeness dashboard both need to ask which regulators
 *    hold a postal or broadcasting mandate. That is an indexed join here and
 *    a string scan with a SET.
 *
 * Reverting to a SET is a one-table change if the Secretariat prefers the
 * literal specification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authority_sector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authority_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['authority_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_sector');
    }
};
