<?php

declare(strict_types=1);

namespace LazyMePHP\Helpers;

use LazyMePHP\Config\Internal\APP;

class ErrorUtil
{
    private static function SendMail(string $from_mail, string $to_mail, string $subject, string $message): bool
    {
        $headers = "Content-Type: text/html; charset=iso-8859-1\n";
        $headers .= "From: $from_mail\n";
        return mail($to_mail, $subject, $message, $headers);
    }

    public static function ErrorHandler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        if (!(error_reporting() & $errno)) {
            return;
        }
        if ($errno === E_NOTICE) {
            return; 
        }

        $errorMsg =
        "<div style='margin:5px;z-index:10000;position:absolute;background-color:#A31919;padding:10px;color:#FFFF66;font-family:sans-serif;font-size:8pt;'>
          <b><u>ERROR:</u></b>
          <ul type='none'>
          <li><b>ERROR NR:</b> $errno</li>
          <li><b>DESCRIPTION:</b> $errstr</li>
          <li><b>FILE:</b> $errfile</li>
          <li><b>LINE:</b> $errline<br/></li>
          <li><b>PHP VERSION:</b> ".phpversion()."
          </ul>
          An email with this message was sent to the developer.
          </div>";

        $appName = class_exists(APP::class) && method_exists(APP::class, 'APP_NAME') ? APP::APP_NAME() : 'LazyMePHP Application';
        $supportEmail = class_exists(APP::class) && method_exists(APP::class, 'APP_SUPPORT_EMAIL') ? APP::APP_SUPPORT_EMAIL() : 'noreply@example.com';

        $to_mail = $supportEmail;
        $from_mail = "noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $subject = "Application Error: " . $appName;
        
        $messageContent = $errorMsg;
        $messageContent .= "<br><br><b>Request Data:</b><br>";
        if (isset($_SESSION)) $messageContent .= "SESSION: ".json_encode($_SESSION)."<br>";
        if (isset($_POST)) $messageContent .= "POST: ".json_encode($_POST)."<br>";
        if (isset($_GET)) $messageContent .= "GET: ".json_encode($_GET)."<br>";
        
        if (!headers_sent()) {
            self::SendMail($from_mail, $to_mail, $subject, $messageContent);
        }
        
        echo $errorMsg;
        die();
    }

    public static function FatalErrorShutdownHandler(): void
    {
        $last_error = error_get_last();
        if (is_array($last_error) && $last_error['type'] === E_ERROR) {
            self::ErrorHandler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
        }
    }
    
    public static function trigger_error(string $message, int $type = E_USER_NOTICE): void 
    {
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            @session_start(); 
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION['APP']['ERROR']['INTERNAL']['TYPE'] = $type;
            $_SESSION['APP']['ERROR']['INTERNAL']['MESSAGE'] = $message;
        }
    }

    public static function GetErrors(): void 
    {
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            if (isset($_SESSION['APP']['ERROR']['INTERNAL']['MESSAGE'])) {
                echo htmlspecialchars((string)$_SESSION['APP']['ERROR']['INTERNAL']['MESSAGE']);
            }
            if (isset($_SESSION['APP']['ERROR']['DB']['MESSAGE'])) {
                echo htmlspecialchars((string)$_SESSION['APP']['ERROR']['DB']['MESSAGE']);
            }
            unset($_SESSION['APP']['ERROR']);
        }
    }

    public static function HasErrors(): bool 
    {
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            return (isset($_SESSION['APP']['ERROR']['INTERNAL']['MESSAGE']) || isset($_SESSION['APP']['ERROR']['DB']['MESSAGE']));
        }
        return false;
    }
}
```
