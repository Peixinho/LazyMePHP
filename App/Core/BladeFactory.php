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
            $echoFormatProperty->setValue(self::$blade, 'static::e(%s)');

            // @csrf — hidden input for form submission
            self::$blade->directive('csrf', function() {
                return '<?php echo \'<input type="hidden" name="csrf_token" value="\' . \Core\Security\CsrfProtection::renderInput() . \'">\'; ?>';
            });

            // @csrfMeta — <meta> tag for AJAX (LazyMePHP.js reads this automatically)
            self::$blade->directive('csrfMeta', function() {
                return '<?php echo \'<meta name="csrf-token" content="\' . \Core\Security\CsrfProtection::renderInput() . \'">\'; ?>';
            });

            // @pagination($meta) or @pagination($meta, '/base-url')
            // Renders page navigation from ModelQuery::paginate() metadata.
            // Returns empty string when there is only one page.
            self::$blade->directive('pagination', function(string $expression) {
                return '<?php echo \Core\Http\PaginationRenderer::render(' . $expression . '); ?>';
            });

            // Share global settings here if you want
            self::$blade->share('settings', [
                'appName' => $_ENV['APP_NAME'] ?? 'LazyMePHP',
                'appLogo' => '/img/logo.png',
            ]);
        }
        return self::$blade;
    }

    /**
     * Render a Blade view with Profiler instrumentation.
     *
     * Prefer this over getBlade()->run() so render spans appear in the debug timeline.
     *
     *   echo BladeFactory::render('users.index', ['users' => $users]);
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $view, array $data = []): string
    {
        if (class_exists(\Core\Debug\Profiler::class)) {
            \Core\Debug\Profiler::start('render', $view);
        }
        $html = self::getBlade()->run($view, $data);
        if (class_exists(\Core\Debug\Profiler::class)) {
            \Core\Debug\Profiler::stop();
        }
        return $html;
    }
}