<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * When a translation is missing the reader sees the English text with an
 * inline notice. Never a blank page, never a hidden record.
 *
 * The component is rendered directly rather than through a page, so these
 * tests keep working as the pages around it change.
 */
class FallbackNoticeTest extends TestCase
{
    private function render(string $locale): string
    {
        app()->setLocale($locale);

        return Blade::render('<x-fallback-notice />');
    }

    public function test_the_notice_names_the_missing_language_in_that_language(): void
    {
        $this->assertStringContainsString(
            'Esta página ainda não está disponível em português',
            $this->render('pt')
        );
        $this->assertStringContainsString(
            "Cette page n'est pas encore disponible en français",
            html_entity_decode($this->render('fr'))
        );
    }

    public function test_the_notice_explains_which_language_the_text_is_in(): void
    {
        $this->assertStringContainsString('apresentado em inglês', $this->render('pt'));
        $this->assertStringContainsString('affiché en anglais', html_entity_decode($this->render('fr')));
    }

    public function test_no_notice_is_shown_to_a_reader_already_in_english(): void
    {
        $this->assertStringNotContainsString('c-notice', $this->render('en'));
    }

    /**
     * The notice always speaks to the reader in the language they are
     * reading, and names the language that is missing.
     */
    public function test_an_explicit_requested_locale_names_that_language_to_the_reader(): void
    {
        app()->setLocale('en');

        $this->assertStringContainsString(
            'This page is not yet available in Portuguese',
            Blade::render('<x-fallback-notice requested="pt" />')
        );
    }
}
