<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA_MODEL section 4. The harmonised taxonomy, RP-11.
 *
 * Definitions, not values. source_standard is not optional: an indicator
 * without a citable published definition does not belong in a taxonomy that
 * fourteen national regulators are asked to report against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->enum('category', [
                'licensing', 'spectrum', 'interconnection', 'market', 'quality', 'universal_access',
            ]);
            $table->string('slug', 80)->unique();
            $table->string('name', 200);
            // Precise enough that fourteen regulators report the same thing.
            $table->text('definition');
            $table->string('unit', 40);
            $table->enum('data_type', ['numeric', 'currency', 'percentage', 'text', 'boolean', 'date']);
            $table->enum('periodicity', ['annual', 'biannual', 'quarterly', 'ad_hoc']);
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            // Where the definition came from. Never null.
            $table->string('source_standard', 200);
            $table->string('source_url', 255)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'display_order']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
