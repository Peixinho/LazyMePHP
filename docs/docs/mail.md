---
id: mail
title: Mail
sidebar_position: 9
---

# Mail

A fluent mailer that supports PHP's built-in `mail()` and SMTP with STARTTLS.

## Configuration

```env
MAIL_DRIVER=smtp          # mail (default) | smtp
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=My App

# SMTP only
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=tls       # tls | ssl | none
```

## Sending a quick email

```php
use Core\Mail\Mail;

Mail::to('alice@example.com', 'Alice')
    ->subject('Welcome!')
    ->html('<h1>Hi Alice</h1><p>Thanks for signing up.</p>')
    ->text('Hi Alice — thanks for signing up.')
    ->send();
```

### Fluent builder methods

| Method | Description |
|---|---|
| `Mail::to($address, $name = '')` | Primary recipient (static entry point) |
| `->cc($address, $name = '')` | Add a CC recipient |
| `->bcc($address, $name = '')` | Add a BCC recipient |
| `->from($address, $name = '')` | Override the sender |
| `->subject($text)` | Email subject line |
| `->html($html)` | HTML body |
| `->text($plain)` | Plain-text body |
| `->header($name, $value)` | Add a custom MIME header |
| `->send()` | Dispatch the email; returns `bool` |

When both `html()` and `text()` are set, the email is sent as `multipart/alternative`.

## Mailable classes

For recurring email types (welcome emails, password resets, invoices), create a `Mailable` subclass:

```php
// App/Mail/WelcomeMail.php
namespace App\Mail;

use Core\Mail\Mail;
use Core\Mail\Mailable;

class WelcomeMail extends Mailable
{
    public function __construct(private string $userName) {}

    public function build(Mail $mail): void
    {
        $mail
            ->subject("Welcome, {$this->userName}!")
            ->html("<h1>Hi {$this->userName}</h1><p>Your account is ready.</p>")
            ->text("Hi {$this->userName} — your account is ready.");
    }
}
```

Dispatch it:

```php
use Core\Mail\Mail;
use App\Mail\WelcomeMail;

$mailable = new WelcomeMail($user->name);
$mailable->to($user->email, $user->name);

Mail::dispatch($mailable);
```

## SMTP with TLS

Set `MAIL_DRIVER=smtp` and fill in the SMTP credentials. The mailer:

1. Opens a TCP connection to `MAIL_HOST:MAIL_PORT`
2. Issues `EHLO` and upgrades to TLS via `STARTTLS` when the server advertises it
3. Authenticates with `AUTH LOGIN`
4. Sends the message and issues `QUIT`

For SSL-wrapped SMTP (port 465), set `MAIL_ENCRYPTION=ssl`.

## Driver: `mail`

The default driver calls PHP's built-in `mail()` function. It requires a working sendmail or MTA on the host. Suitable for simple setups or when your hosting provider handles outbound mail.

```env
MAIL_DRIVER=mail
MAIL_FROM_ADDRESS=noreply@example.com
```

No other config needed.
