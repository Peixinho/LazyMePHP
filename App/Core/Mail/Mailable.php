<?php

declare(strict_types=1);

namespace Core\Mail;

/**
 * Mailable — base class for structured email messages.
 *
 * Subclass and implement build() to configure the Mail instance:
 *
 *   class WelcomeEmail extends Mailable
 *   {
 *       public function __construct(private readonly string $name) {}
 *
 *       public function build(Mail $mail): void
 *       {
 *           $mail->subject("Welcome, {$this->name}!")
 *                ->html("<h1>Hi {$this->name}</h1><p>Thanks for signing up.</p>")
 *                ->text("Hi {$this->name}. Thanks for signing up.");
 *       }
 *   }
 *
 *   // Send it:
 *   Mail::to('alice@example.com')->send(new WelcomeEmail('Alice'));
 *   // or:
 *   Mail::send((new WelcomeEmail('Alice'))->to('alice@example.com'));
 */
abstract class Mailable
{
    private ?string $toAddress = null;
    private string  $toName    = '';

    public function to(string $address, string $name = ''): static
    {
        $this->toAddress = $address;
        $this->toName    = $name;
        return $this;
    }

    abstract public function build(Mail $mail): void;

    public function getTo(): ?array
    {
        return $this->toAddress !== null
            ? ['address' => $this->toAddress, 'name' => $this->toName]
            : null;
    }
}
