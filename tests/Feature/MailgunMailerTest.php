<?php

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;

/**
 * Build the Mailgun transport from a services config, the way MailManager does.
 */
function mailgunTransport(string $endpoint = 'api.mailgun.net'): MailgunHttpTransport
{
    config()->set('services.mailgun', [
        'domain' => 'mg.example.test',
        'secret' => 'test-secret',
        'endpoint' => $endpoint,
        'scheme' => 'https',
    ]);

    Mail::forgetMailers();

    return Mail::mailer('mailgun')->getSymfonyTransport();
}

test('the mailgun mailer is registered as a transport', function () {
    expect(config('mail.mailers.mailgun.transport'))->toBe('mailgun');
});

test('the mailgun mailer sends through the configured domain and region', function () {
    expect((string) mailgunTransport())
        ->toBe('mailgun+https://api.mailgun.net?domain=mg.example.test');
});

test('the endpoint setting selects the mailgun region', function () {
    expect((string) mailgunTransport('api.eu.mailgun.net'))
        ->toContain('api.eu.mailgun.net');
});
