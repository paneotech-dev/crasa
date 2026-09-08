<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact enquiries. WS-10.
 *
 * Not in DATA_MODEL: the contact form is contracted but the table was never
 * specified. Added here and recorded in section 6.
 *
 * The submission is stored before the mail is queued, so a mail failure
 * never loses an enquiry. delivered_at records that the notification went
 * out, which is how an undelivered enquiry is found later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 160);
            $table->string('organisation', 200)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 200);
            $table->text('message');
            $table->char('locale', 2)->default('en');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('delivered_at');
            $table->index('handled_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
