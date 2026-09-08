<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One reported figure, for one indicator, one member state, one period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('period', 20);
            $table->decimal('value_numeric', 20, 4)->nullable();
            $table->text('value_text')->nullable();
            $table->char('currency', 3)->nullable();
            // Where the figure came from. A number without a source is not
            // reportable on this platform.
            $table->string('source_note', 500)->nullable();
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->enum('confidence', ['reported', 'estimated', 'provisional'])->default('reported');
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->unique(['indicator_id', 'country_id', 'period'], 'indicator_values_unique');
            $table->index(['country_id', 'period']);
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_values');
    }
};
