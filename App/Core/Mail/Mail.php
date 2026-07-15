<?php

declare(strict_types=1);

namespace Core\Mail;

/**
 * Mail — simple fluent mailer.
 *
 * Reads config from .env:
 *   MAIL_FROM_ADDRESS   noreply@example.com
 *   MAIL_FROM_NAME      My App
 *   MAIL_DRIVER         mail | smtp  (defaults to 'mail')
 *   MAIL_HOST           smtp.example.com
 *   MAIL_PORT           587
 *   MAIL_USERNAME       user@example.com
 *   MAIL_PASSWORD       secret
 *   MAIL_ENCRYPTION     tls | ssl | none
 *
 * Usage:
 *   Mail::to('alice@example.com')
 *       ->subject('Hello!')
 *       ->html('<h1>Hi Alice</h1>')
 *       ->text('Hi Alice')
 *       ->send();
 *
 *   // Send a Mailable
 *   Mail::send(new WelcomeEmail($user));
 */
class Mail
{
    private array  $to      = [];
    private array  $cc      = [];
    private array  $bcc     = [];
    private string $subject = '(no subject)';
    private string $html    = '';
    private string $text    = '';
    private array  $headers = [];
    private string $fromAddress;
    private string $fromName;

    public function __construct()
    {
        $this->fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@localhost';
        $this->fromName    = $_ENV['MAIL_FROM_NAME']    ?? 'LazyMePHP';
    }

    // -----------------------------------------------------------------------
    // Static entry points
    // -----------------------------------------------------------------------

    public static function to(string $address, string $name = ''): static
    {
        $instance = new static();
        return $instance->addRecipient('to', $address, $name);
    }

    /** Send a Mailable object directly. */
    public static function dispatch(Mailable $mailable): bool
    {
        $instance = new static();
        if ($recipient = $mailable->getTo()) {
            $instance->addRecipient('to', $recipient['address'], $recipient['name']);
        }
        $mailable->build($instance);
        return $instance->deliver();
    }

    // -----------------------------------------------------------------------
    // Fluent builder
    // -----------------------------------------------------------------------

    public function cc(string $address, string $name = ''): static
    {
        return $this->addRecipient('cc', $address, $name);
    }

    public function bcc(string $address, string $name = ''): static
    {
        return $this->addRecipient('bcc', $address, $name);
    }

    public function from(string $address, string $name = ''): static
    {
        $this->fromAddress = $address;
        $this->fromName    = $name;
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): static
    {
        $this->html = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Send
    // -----------------------------------------------------------------------

    public function send(): bool
    {
        return $this->deliver();
    }

    private function deliver(): bool
    {
        if (empty($this->to)) {
            throw new \LogicException('Mail must have at least one recipient.');
        }

        $driver = strtolower($_ENV['MAIL_DRIVER'] ?? 'mail');

        return match ($driver) {
            'smtp' => $this->sendViaSmtp(),
            default => $this->sendViaMail(),
        };
    }

    // -----------------------------------------------------------------------
    // Drivers
    // -----------------------------------------------------------------------

    private function sendViaMail(): bool
    {
        [$body, $contentType] = $this->buildBody();
        $to      = implode(', ', array_map([$this, 'formatAddress'], $this->to));
        $headers = $this->buildHeaders($contentType);

        return mail($to, $this->subject, $body, implode("\r\n", $headers));
    }

    private function sendViaSmtp(): bool
    {
        $host       = $_ENV['MAIL_HOST']       ?? 'localhost';
        $port       = (int)($_ENV['MAIL_PORT'] ?? 587);
        $username   = $_ENV['MAIL_USERNAME']   ?? '';
        $password   = $_ENV['MAIL_PASSWORD']   ?? '';
        $encryption = strtolower($_ENV['MAIL_ENCRYPTION'] ?? 'tls');

        $scheme = match($encryption) {
            'ssl'   => 'ssl',
            'tls'   => 'tcp',
            default => 'tcp',
        };

        $errno  = 0;
        $errstr = '';
        $socket = @stream_socket_client("$scheme://$host:$port", $errno, $errstr, 10);

        if (!$socket) {
            error_log("Mail SMTP connect failed ($host:$port): $errstr");
            return false;
        }

        stream_set_timeout($socket, 10);

        try {
            $this->smtpRead($socket); // greeting

            $this->smtpWrite($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $ehloResponse = $this->smtpRead($socket);

            // Upgrade to TLS if requested
            if ($encryption === 'tls' && str_contains($ehloResponse, 'STARTTLS')) {
                $this->smtpWrite($socket, 'STARTTLS');
                $this->smtpRead($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpWrite($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                $this->smtpRead($socket);
            }

            // Authenticate
            if ($username !== '') {
                $this->smtpWrite($socket, 'AUTH LOGIN');
                $this->smtpRead($socket);
                $this->smtpWrite($socket, base64_encode($username));
                $this->smtpRead($socket);
                $this->smtpWrite($socket, base64_encode($password));
                $this->smtpRead($socket);
            }

            $this->smtpWrite($socket, 'MAIL FROM:<' . $this->fromAddress . '>');
            $this->smtpRead($socket);

            foreach ($this->allRecipients() as $address) {
                $this->smtpWrite($socket, "RCPT TO:<$address>");
                $this->smtpRead($socket);
            }

            $this->smtpWrite($socket, 'DATA');
            $this->smtpRead($socket);

            [$body, $contentType] = $this->buildBody();
            $headers = $this->buildHeaders($contentType);

            $message  = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
            $this->smtpWrite($socket, $message);
            $this->smtpRead($socket);

            $this->smtpWrite($socket, 'QUIT');
        } finally {
            fclose($socket);
        }

        return true;
    }

    private function smtpWrite($socket, string $data): void
    {
        fwrite($socket, $data . "\r\n");
    }

    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break; // last line of multi-line response
        }
        return $response;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function addRecipient(string $list, string $address, string $name): static
    {
        $this->{$list}[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    private function formatAddress(array $recipient): string
    {
        $address = $recipient['address'];
        $name    = $recipient['name'];
        return $name !== '' ? "\"$name\" <$address>" : $address;
    }

    private function allRecipients(): array
    {
        $addresses = [];
        foreach (array_merge($this->to, $this->cc, $this->bcc) as $r) {
            $addresses[] = $r['address'];
        }
        return $addresses;
    }

    private function buildBody(): array
    {
        if ($this->html !== '' && $this->text !== '') {
            $boundary    = 'lm_' . bin2hex(random_bytes(8));
            $contentType = "multipart/alternative; boundary=\"$boundary\"";
            $body        = "--$boundary\r\n"
                         . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                         . $this->text . "\r\n"
                         . "--$boundary\r\n"
                         . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                         . $this->html . "\r\n"
                         . "--$boundary--";
            return [$body, $contentType];
        }

        if ($this->html !== '') {
            return [$this->html, 'text/html; charset=UTF-8'];
        }

        return [$this->text, 'text/plain; charset=UTF-8'];
    }

    private function buildHeaders(string $contentType): array
    {
        $from     = $this->formatAddress(['address' => $this->fromAddress, 'name' => $this->fromName]);
        $toList   = implode(', ', array_map([$this, 'formatAddress'], $this->to));
        $headers  = [
            "From: $from",
            "To: $toList",
            "Subject: {$this->subject}",
            "MIME-Version: 1.0",
            "Content-Type: $contentType",
            "X-Mailer: LazyMePHP",
        ];

        if (!empty($this->cc)) {
            $headers[] = 'Cc: ' . implode(', ', array_map([$this, 'formatAddress'], $this->cc));
        }
        if (!empty($this->bcc)) {
            $headers[] = 'Bcc: ' . implode(', ', array_map([$this, 'formatAddress'], $this->bcc));
        }

        foreach ($this->headers as $name => $value) {
            $headers[] = "$name: $value";
        }

        return $headers;
    }
}
