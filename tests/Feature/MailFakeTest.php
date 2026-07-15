<?php

declare(strict_types=1);

use Core\Mail\Mail;
use Core\Mail\Mailable;

// ---------------------------------------------------------------------------
// Inline Mailables
// ---------------------------------------------------------------------------

class WelcomeMail extends Mailable
{
    public function __construct(private string $name) {}

    public function build(Mail $mail): void
    {
        $mail->subject("Welcome, {$this->name}!")->text("Hello {$this->name}");
    }
}

class PasswordResetMail extends Mailable
{
    public function __construct(private string $token) {}

    public function build(Mail $mail): void
    {
        $mail->subject('Reset your password')->text("Token: {$this->token}");
    }
}

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

beforeEach(function () {
    $_ENV['MAIL_FROM_ADDRESS'] = 'noreply@example.com';
    $_ENV['MAIL_FROM_NAME']    = 'Test App';
    Mail::fake();
});

afterEach(function () {
    Mail::resetFake();
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Mail::fake()', function () {
    it('captures mail instead of sending it', function () {
        Mail::to('alice@example.com')->subject('Hi')->text('Hello')->send();

        expect(Mail::sent())->toHaveCount(1);
    });

    it('assertSentTo passes when email was a recipient', function () {
        Mail::to('alice@example.com')->subject('Hi')->text('Hello')->send();

        Mail::assertSentTo('alice@example.com');
    });

    it('assertSentTo is case-insensitive', function () {
        Mail::to('Alice@Example.COM')->subject('Hi')->text('Hello')->send();

        Mail::assertSentTo('alice@example.com');
    });

    it('assertSentTo fails when email was not a recipient', function () {
        Mail::to('alice@example.com')->subject('Hi')->text('Hello')->send();

        expect(fn () => Mail::assertSentTo('bob@example.com'))
            ->toThrow(\RuntimeException::class);
    });

    it('assertNothingSent passes when no mail was sent', function () {
        Mail::assertNothingSent();
    });

    it('assertNothingSent fails when mail was sent', function () {
        Mail::to('alice@example.com')->text('Hi')->send();

        expect(fn () => Mail::assertNothingSent())
            ->toThrow(\RuntimeException::class);
    });

    it('assertSent passes for a dispatched Mailable class', function () {
        $mailable = (new WelcomeMail('Alice'))->to('alice@example.com');
        Mail::dispatch($mailable);

        Mail::assertSent(WelcomeMail::class);
    });

    it('assertSent fails when Mailable was not dispatched', function () {
        expect(fn () => Mail::assertSent(WelcomeMail::class))
            ->toThrow(\RuntimeException::class);
    });

    it('assertSent with callback inspects captured mail', function () {
        $mailable = (new WelcomeMail('Alice'))->to('alice@example.com');
        Mail::dispatch($mailable);

        Mail::assertSent(WelcomeMail::class, fn (array $m) => str_contains($m['subject'], 'Alice'));
    });

    it('sent() returns all captured messages', function () {
        Mail::to('alice@example.com')->subject('A')->text('a')->send();
        Mail::to('bob@example.com')->subject('B')->text('b')->send();

        expect(Mail::sent())->toHaveCount(2);
    });

    it('captured message contains expected fields', function () {
        Mail::to('alice@example.com')->subject('Hello')->html('<b>Hi</b>')->text('Hi')->send();

        $sent = Mail::sent()[0];
        expect($sent['subject'])->toBe('Hello');
        expect($sent['html'])->toBe('<b>Hi</b>');
        expect($sent['text'])->toBe('Hi');
    });

    it('resetFake clears captured messages', function () {
        Mail::to('alice@example.com')->text('Hi')->send();
        Mail::resetFake();

        expect(Mail::sent())->toHaveCount(0);
    });

    it('does not send real mail when in fake mode', function () {
        // If real delivery were attempted without SMTP config, it would fail.
        // In fake mode it must succeed silently.
        $result = Mail::to('alice@example.com')->subject('Hi')->text('Hello')->send();
        expect($result)->toBeTrue();
    });
});
