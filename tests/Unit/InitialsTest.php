<?php

namespace Tests\Unit;

use App\Support\Initials;
use PHPUnit\Framework\TestCase;

class InitialsTest extends TestCase
{
    public function test_initials_come_from_words_that_start_with_a_letter(): void
    {
        $this->assertSame('DR', Initials::of('Demo Member State Representative (ICASA)'));
        $this->assertSame('BL', Initials::of('Bridget Linzie'));
        $this->assertSame('É', Initials::of('Émile'));
        $this->assertSame('', Initials::of('(ICASA)'));
    }
}
