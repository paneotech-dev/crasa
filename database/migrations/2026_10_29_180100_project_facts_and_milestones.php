<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S8 remainder: the facts a project page shows (programme, funding partner,
 * countries, lead committee). Milestones are per-language text kept in the
 * translations table, one per line. Every value starts empty: CRASA fills
 * them in the back office; nothing is invented here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'programme')) {
                $table->string('programme', 120)->nullable()->after('state');
            }
            if (! Schema::hasColumn('projects', 'funding_partner')) {
                $table->string('funding_partner', 200)->nullable()->after('partner');
            }
            if (! Schema::hasColumn('projects', 'countries')) {
                $table->string('countries', 500)->nullable()->after('funding_partner');
            }
            if (! Schema::hasColumn('projects', 'lead_committee_id')) {
                $table->foreignId('lead_committee_id')->nullable()->after('sector_id')->constrained('committees')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'lead_committee_id')) {
                $table->dropConstrainedForeignId('lead_committee_id');
            }
            foreach (['programme', 'funding_partner', 'countries'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
