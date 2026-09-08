<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA_MODEL section 3. The spine: everything in the comparator, the e-Forms
 * and the completeness dashboard depends on it.
 *
 * slug is the citation permalink. Once an instrument is published its slug
 * never changes, because a ministerial brief that cites it must keep
 * resolving. Enforced on the model.
 *
 * Title, summary and body are translatable and live in the translations
 * table, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->enum('instrument_type', [
                'act', 'regulation', 'licence_framework', 'guideline', 'directive', 'policy',
            ]);
            $table->string('reference_number', 80)->nullable();
            $table->date('enacted_on')->nullable();
            $table->date('effective_from')->nullable();
            $table->enum('status', ['in_force', 'amended', 'repealed', 'draft', 'proposed'])
                ->default('draft');
            $table->foreignId('superseded_by_id')->nullable()
                ->constrained('instruments')->nullOnDelete();
            // Set after the first version exists, so it cannot be constrained
            // at insert time without a chicken and egg.
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('slug', 200)->unique();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_id', 'sector_id']);
            $table->index('status');
            $table->index('instrument_type');
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
