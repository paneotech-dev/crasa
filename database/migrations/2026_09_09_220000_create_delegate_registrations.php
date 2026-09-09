<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delegate registration, WS-07.
 *
 * READ THE COLUMN LIST AS A BOUNDARY, NOT AS A SCHEMA.
 *
 * A delegate files when they arrive and when they leave, against a meeting.
 * The Secretariat reads a list of that and exports it. That is the whole
 * feature, and the line it sits on protects a separate commercial
 * agreement: the website collects the information, the Event Management
 * Platform manages the operation.
 *
 * Screen 18 draws four things this table deliberately does not carry:
 * accommodation, nights required, dietary requirements and a transfer
 * schedule. Each of those is the managing half rather than the collecting
 * half. A column for any of them is the moment this crosses the line, which
 * is why there is a test that walks these column names.
 *
 * It is called delegate registration everywhere, including here. "Travel
 * logistics" is the name of the thing that is not being built.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegate_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();

            $table->string('delegate_name');
            $table->string('delegate_email');
            $table->string('delegate_role')->nullable();

            // Arrival and departure. Nothing else about the journey.
            $table->string('arrival_point', 120)->nullable();
            $table->date('arrival_on')->nullable();
            $table->time('arrival_at')->nullable();
            $table->string('arrival_reference', 60)->nullable();

            $table->date('departure_on')->nullable();
            $table->time('departure_at')->nullable();
            $table->string('departure_reference', 60)->nullable();

            // Screen 18: "Anything else the Secretariat should know". One
            // free field, deliberately not a set of tracked categories.
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('filed');
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->unique(['meeting_id', 'delegate_email']);
            $table->index(['meeting_id', 'arrival_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegate_registrations');
    }
};
