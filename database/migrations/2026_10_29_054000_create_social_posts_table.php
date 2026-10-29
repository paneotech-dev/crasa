<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * CRASA's own posts on X, Facebook and LinkedIn, pasted in by the
 * Secretariat and shown on the home page and at the foot of the newsroom.
 *
 * embed_html holds what X's public oEmbed endpoint returned, fetched once
 * when the post was saved, so a page view never waits on a third party to
 * learn what to draw. embed_url is the frame address for the networks that
 * offer a frame rather than markup (Facebook, LinkedIn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_posts')) {
            return;
        }

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('network', 16);
            $table->string('url', 500)->unique();
            $table->dateTime('posted_at')->nullable();
            $table->string('caption', 500)->nullable();
            $table->text('embed_html')->nullable();
            $table->string('embed_url', 700)->nullable();
            $table->string('status', 16)->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
