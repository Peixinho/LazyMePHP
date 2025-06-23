<?php

declare(strict_types=1);

namespace Core\Helpers;

/**
 * Error Page Generator using Blade templating
 * Provides a customizable error page system
 */
class ErrorPage
{
    /**
     * Generate error page using Blade template
     */
    public static function generate(array $errorData): string
    {
        $errorId = $errorData['error_id'];
        $type = isset($errorData['type']) ? (string)$errorData['type'] : 'Error';
        $message = $errorData['message'] ?? 'An unexpected error occurred';
        
        // Extract error code from type (e.g., "404 - Page Not Found" -> "404")
        // Also handle PHP error constants (E_USER_ERROR = 256, etc.)
        $errorCode = '500';
        if (preg_match('/^(\d+)/', $type, $matches)) {
            $extractedCode = $matches[1];
            
            // Map PHP error constants to HTTP error codes
            $phpErrorMap = [
                '1' => '500',   // E_ERROR
                '2' => '500',   // E_WARNING  
                '4' => '500',   // E_PARSE
                '8' => '500',   // E_NOTICE
                '16' => '500',  // E_CORE_ERROR
                '32' => '500',  // E_CORE_WARNING
                '64' => '500',  // E_COMPILE_ERROR
                '128' => '500', // E_COMPILE_WARNING
                '256' => '500', // E_USER_ERROR
                '512' => '500', // E_USER_WARNING
                '1024' => '500', // E_USER_NOTICE
                '2048' => '500', // E_STRICT
                '4096' => '500', // E_RECOVERABLE_ERROR
                '8192' => '500', // E_DEPRECATED
                '16384' => '500' // E_USER_DEPRECATED
            ];
            
            // If it's a known PHP error constant, map it to 500
            if (isset($phpErrorMap[$extractedCode])) {
                $errorCode = $phpErrorMap[$extractedCode];
            } else {
                // Otherwise use the extracted code (for HTTP status codes like 404)
                $errorCode = $extractedCode;
            }
        }
        
        // Set appropriate title based on error code
        $title = 'Internal Server Error';
        if ($errorCode === '404') {
            $title = 'Page Not Found';
        }
        
        // Prepare data for Blade template
        $viewData = [
            'errorCode' => $errorCode,
            'title' => $title,
            'message' => $message,
            'errorId' => $errorId,
            'file' => $errorData['file'] ?? '',
            'line' => $errorData['line'] ?? '',
            'trace' => $errorData['trace'] ?? ''
        ];
        
        // Use Blade to render the error page
        return self::renderBladeView('_Components.Error', $viewData);
    }
    
    /**
     * Render Blade view with data
     */
    private static function renderBladeView(string $view, array $data): string
    {
        try {
            // Check if BladeOne is available
            if (class_exists('\eftec\bladeone\BladeOne')) {
                $viewsPath = __DIR__ . '/../../Views';
                $cachePath = __DIR__ . '/../../Views/_compiled';
                
                // Create cache directory if it doesn't exist
                if (!is_dir($cachePath)) {
                    mkdir($cachePath, 0755, true);
                }
                
                $blade = new \eftec\bladeone\BladeOne($viewsPath, $cachePath, \eftec\bladeone\BladeOne::MODE_DEBUG);
                return $blade->run($view, $data);
            } else {
                // Fallback to simple template if BladeOne is not available
                return self::renderSimpleTemplate($data);
            }
        } catch (\Exception $e) {
            // Fallback to simple template if Blade rendering fails
            return self::renderSimpleTemplate($data);
        }
    }
    
    /**
     * Fallback simple template renderer
     */
    private static function renderSimpleTemplate(array $data): string
    {
        // Use the same Blade template as fallback
        $viewsPath = __DIR__ . '/../../Views';
        $templateFile = $viewsPath . '/_Components/Error.blade.php';
        
        if (file_exists($templateFile)) {
            // Simple template replacement for fallback
            $template = file_get_contents($templateFile);
            
            // Replace Blade variables with actual values
            $replacements = [
                '{{ $errorCode }}' => htmlspecialchars($data['errorCode']),
                '{{ $title }}' => htmlspecialchars($data['title']),
                '{{ $message }}' => htmlspecialchars($data['message']),
                '{{ $errorId }}' => htmlspecialchars($data['errorId']),
                '@if($errorId)' => $data['errorId'] ? '' : '<!--',
                '@endif' => $data['errorId'] ? '' : '-->',
            ];
            
            return str_replace(array_keys($replacements), array_values($replacements), $template);
        }
        
        // Ultimate fallback - minimal error page
        return '<!DOCTYPE html>
<html>
<head><title>Error</title></head>
<body>
<h1>Error ' . htmlspecialchars($data['errorCode']) . '</h1>
<p>' . htmlspecialchars($data['message']) . '</p>
<p>Error ID: ' . htmlspecialchars($data['errorId']) . '</p>
</body>
</html>';
    }
} 