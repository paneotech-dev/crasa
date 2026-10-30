<?php

namespace Tests\Unit;

use App\Search\Highlight;
use PHPUnit\Framework\TestCase;

/**
 * The database matches without regard to accents, so the marks must too:
 * "itinerance" found "itinérance" and then marked nothing.
 */
class SearchHighlightTest extends TestCase
{
    public function test_a_term_typed_without_accents_marks_the_accented_word(): void
    {
        $this->assertSame(
            'Lignes directrices de la SADC sur l’<mark>itinérance</mark>',
            Highlight::mark('Lignes directrices de la SADC sur l’itinérance', ['itinerance']),
        );
    }

    public function test_an_accented_term_marks_the_plain_word_and_keeps_its_case(): void
    {
        $this->assertSame('<mark>Telecomunicacoes</mark>', Highlight::mark('Telecomunicacoes', ['telecomunicações']));
        $this->assertSame('<mark>ROAMING</mark> e <mark>roaming</mark>', Highlight::mark('ROAMING e roaming', ['roaming']));
    }

    public function test_the_position_is_counted_in_characters(): void
    {
        $this->assertSame(4, Highlight::position('été itinérance', ['itinerance']));
        $this->assertNull(Highlight::position('roaming', ['itinerance']));
    }

    public function test_terms_cannot_inject_markup_or_break_the_pattern(): void
    {
        $escaped = e('a <b> (x) & y');

        $this->assertStringNotContainsString('<script>', Highlight::mark($escaped, ['<script>']));
        $this->assertSame('a &lt;b&gt; <mark>(x)</mark> &amp; y', Highlight::mark($escaped, ['(x)']));
    }
}
