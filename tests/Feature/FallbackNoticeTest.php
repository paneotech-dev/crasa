<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * When a translation is missing the reader sees the English text with an
 * inline notice. Never a blank page, never a hidden record.
 */
class FallbackNoticeTest extends TestCase
{
    public function test_the_notice_names_the_missing_language_in_that_language(): void
    {
        $this->get('/pt')->assertSee('Esta página ainda não está disponível em português');
        $this->get('/fr')->assertSee("Cette page n'est pas encore disponible en français");
    }

    public function test_no_notice_is_shown_to_a_reader_already_in_english(): void
    {
        $this->get('/en')->assertDontSee('c-notice--info', false);
    }

    public function test_the_notice_explains_which_language_the_text_is_in(): void
    {
        $this->get('/pt')->assertSee('apresentado em inglês');
    }
}
