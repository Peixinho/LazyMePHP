<?php
namespace Core;

use eftec\bladeone\BladeOne;

class BladeFactory
{
    private static $blade = null;

    public static function getBlade()
    {
        if (self::$blade === null) {
            $views = __DIR__ . '/../Views/';
            $cache = __DIR__ . '/../Views/_compiled';
            self::$blade = new BladeOne($views, $cache, BladeOne::MODE_AUTO);

            // Fix the htmlentities() null parameter deprecation warning
            // Use the new static::e() method instead of the old htmlentities() format
            $reflection = new \ReflectionClass(self::$blade);
            $echoFormatProperty = $reflection->getProperty('echoFormat');
            $echoFormatProperty->setAccessible(true);
            $echoFormatProperty->setValue(self::$blade, 'static::e(%s)');

            // Share global settings here if you want
            self::$blade->share('settings', [
                'appName' => $_ENV['APP_NAME'] ?? 'LazyMePHP',
                'appLogo' => '/img/logo.png',
            ]);
        }
        return self::$blade;
    }
} 