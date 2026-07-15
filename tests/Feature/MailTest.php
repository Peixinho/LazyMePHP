<?php

declare(strict_types=1);

use Core\Mail\Mail;
use Core\Mail\Mailable;

beforeEach(function () {
    $_ENV['MAIL_FROM_ADDRESS'] = 'noreply@example.com';
    $_ENV['MAIL_FROM_NAME']    = 'Test App';
    $_ENV['MAIL_DRIVER']       = 'mail';
});

// -----------------------------------------------------------------------
// Builder API
// -----------------------------------------------------------------------

test('to() returns a Mail instance', function () {
    $mail = Mail::to('alice@example.com');
    expect($mail)->toBeInstanceOf(Mail::class);
});

test('to() with name sets recipient', function () {
    $mail = Mail::to('alice@example.com', 'Alice');
    expect($mail)->toBeInstanceOf(Mail::class);
});

test('subject() is chainable', function () {
    $mail = Mail::to('alice@example.com')->subject('Hello');
    expect($mail)->toBeInstanceOf(Mail::class);
});

test('html() is chainable', function () {
    $mail = Mail::to('alice@example.com')->html('<h1>Hi</h1>');
    expect($mail)->toBeInstanceOf(Mail::class);
});

test('text() is chainable', function () {
    $mail = Mail::to('alice@example.com')->text('Hi');
    expect($mail)->toBeInstanceOf(Mail::class);
});

test('cc() is chainable', function () {
    $mail = Mail::to('alice@example.com')->cc('bob@example.com');
    expect($mail)->toBeInstanceOf(Mail::class);
});

test('bcc() is chainable', function () {
    $mail = Mail::to('alice@example.com')->bcc('carol@example.com');
    expect($mail)->toBeInstanceOf(Mail::class);
});

test('from() overrides the default sender', function () {
    $mail = Mail::to('alice@example.com')->from('custom@example.com', 'Custom');
    expect($mail)->toBeInstanceOf(Mail::class);
});

test('header() is chainable', function () {
    $mail = Mail::to('alice@example.com')->header('X-Custom', 'value');
    expect($mail)->toBeInstanceOf(Mail::class);
});

// -----------------------------------------------------------------------
// Validation
// -----------------------------------------------------------------------

test('send() without recipients throws LogicException', function () {
    $mail = new Mail();
    $mail->send();
})->throws(\LogicException::class, 'at least one recipient');

// -----------------------------------------------------------------------
// Mailable
// -----------------------------------------------------------------------

test('Mailable subclass can be built via dispatch()', function () {
    $mailable = new class extends Mailable {
        public function build(Mail $mail): void
        {
            $mail->subject('Test')->html('<p>Test</p>');
        }
    };
    $mailable->to('alice@example.com');

    expect($mailable)->toBeInstanceOf(Mailable::class);
    expect($mailable->getTo()['address'])->toBe('alice@example.com');
});

test('Mailable::to() returns the same instance', function () {
    $mailable = new class extends Mailable {
        public function build(Mail $mail): void {}
    };

    $result = $mailable->to('alice@example.com', 'Alice');
    expect($result)->toBe($mailable);
    expect($mailable->getTo())->toBe(['address' => 'alice@example.com', 'name' => 'Alice']);
});

test('Mailable::getTo() returns null when not set', function () {
    $mailable = new class extends Mailable {
        public function build(Mail $mail): void {}
    };

    expect($mailable->getTo())->toBeNull();
});
