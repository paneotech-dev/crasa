<?php

namespace Tests\Feature;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * mail:check refuses to report success when nothing would be delivered.
 */
class MailCheckCommandTest extends TestCase
{
    public function test_a_silent_driver_is_refused(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('mail:check', ['to' => 'someone@example.org'])
            ->expectsOutputToContain('delivers nothing')
            ->assertFailed();
    }

    public function test_an_example_from_address_is_refused(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'hello@example.com']);

        $this->artisan('mail:check', ['to' => 'someone@example.org'])
            ->expectsOutputToContain('MAIL_FROM_ADDRESS')
            ->assertFailed();
    }

    public function test_a_malformed_address_is_refused(): void
    {
        $this->artisan('mail:check', ['to' => 'not-an-address'])->assertFailed();
    }

    public function test_a_refusing_relay_is_reported_as_a_failure(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'no-reply@crasa.test',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 9,
            'mail.mailers.smtp.timeout' => 2,
        ]);

        $this->artisan('mail:check', ['to' => 'someone@example.org'])
            ->expectsOutputToContain('refused')
            ->assertFailed();
    }

    public function test_an_accepted_message_is_sent_once_to_the_address_given(): void
    {
        Event::fake([MessageSent::class]);

        // The array transport is on the silent list by name, so it is
        // registered under another name to stand in for a real relay.
        config([
            'mail.mailers.check' => ['transport' => 'array'],
            'mail.default' => 'check',
            'mail.from.address' => 'no-reply@crasa.test',
        ]);

        $this->artisan('mail:check', ['to' => 'someone@example.org'])
            ->expectsOutputToContain('Accepted by check')
            ->assertSuccessful();

        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $e) => $e->message->getTo()[0]->getAddress() === 'someone@example.org');
    }
}
