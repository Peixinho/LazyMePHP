<?php

/**
 * LazyMePHP File Validation System
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Validations;

class FileValidation
{
    // File type constants
    public const TYPE_IMAGE = 'image';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_ARCHIVE = 'archive';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_SPREADSHEET = 'spreadsheet';
    public const TYPE_PRESENTATION = 'presentation';
    public const TYPE_PDF = 'pdf';
    public const TYPE_TEXT = 'text';

    // Default allowed MIME types
    private const ALLOWED_MIME_TYPES = [
        self::TYPE_IMAGE => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/bmp',
            'image/tiff'
        ],
        self::TYPE_DOCUMENT => [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text',
            'text/plain',
            'text/rtf'
        ],
        self::TYPE_SPREADSHEET => [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.spreadsheet',
            'text/csv'
        ],
        self::TYPE_PRESENTATION => [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.presentation'
        ],
        self::TYPE_PDF => [
            'application/pdf'
        ],
        self::TYPE_ARCHIVE => [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip',
            'application/x-tar'
        ],
        self::TYPE_VIDEO => [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
            'video/webm',
            'video/mkv'
        ],
        self::TYPE_AUDIO => [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/aac',
            'audio/flac'
        ],
        self::TYPE_TEXT => [
            'text/plain',
            'text/html',
            'text/css',
            'text/javascript',
            'application/json',
            'application/xml',
            'text/xml'
        ]
    ];

    // Dangerous file extensions to block
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
        'asp', 'aspx', 'ashx', 'asmx',
        'jsp', 'jspx',
        'exe', 'bat', 'cmd', 'com', 'scr', 'pif',
        'js', 'vbs', 'wsf', 'hta',
        'jar', 'war', 'ear',
        'sh', 'bash', 'csh', 'ksh', 'tcsh',
        'pl', 'py', 'rb', 'lua',
        'dll', 'so', 'dylib',
        'bin', 'sys', 'drv'
    ];

    // Maximum file sizes (in bytes)
    private const DEFAULT_MAX_SIZES = [
        self::TYPE_IMAGE => 10 * 1024 * 1024,      // 10MB
        self::TYPE_DOCUMENT => 50 * 1024 * 1024,   // 50MB
        self::TYPE_SPREADSHEET => 50 * 1024 * 1024, // 50MB
        self::TYPE_PRESENTATION => 100 * 1024 * 1024, // 100MB
        self::TYPE_PDF => 25 * 1024 * 1024,        // 25MB
        self::TYPE_ARCHIVE => 100 * 1024 * 1024,   // 100MB
        self::TYPE_VIDEO => 500 * 1024 * 1024,     // 500MB
        self::TYPE_AUDIO => 100 * 1024 * 1024,     // 100MB
        self::TYPE_TEXT => 5 * 1024 * 1024         // 5MB
    ];

    /**
     * Validate a file upload
     *
     * @param array $file The $_FILES array element
     * @param array $options Validation options
     * @return array Validation result
     */
    public static function validateFile(array $file, array $options = []): array
    {
        $errors = [];
        $warnings = [];

        // Basic file structure validation
        if (!self::validateFileStructure($file)) {
            return [
                'valid' => false,
                'errors' => ['Invalid file structure'],
                'warnings' => []
            ];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
        }

        // File size validation
        $maxSize = $options['max_size'] ?? self::DEFAULT_MAX_SIZES[self::TYPE_DOCUMENT];
        if ($file['size'] > $maxSize) {
            $errors[] = "File size exceeds maximum allowed size of " . self::formatBytes($maxSize);
        }

        // File type validation
        $allowedTypes = $options['allowed_types'] ?? [self::TYPE_DOCUMENT, self::TYPE_IMAGE, self::TYPE_PDF];
        $typeValidation = self::validateFileType($file, $allowedTypes);
        if (!$typeValidation['valid']) {
            $errors = array_merge($errors, $typeValidation['errors']);
        }

        // File extension validation
        $extensionValidation = self::validateFileExtension($file['name']);
        if (!$extensionValidation['valid']) {
            $errors = array_merge($errors, $extensionValidation['errors']);
        }

        // Content validation (skip for images and other binary files)
        if (!self::isImageFile($file)) {
            $contentValidation = self::validateFileContent($file);
            if (!$contentValidation['valid']) {
                $errors = array_merge($errors, $contentValidation['errors']);
            }
        }

        // Security checks
        $securityValidation = self::performSecurityChecks($file);
        if (!$securityValidation['valid']) {
            $errors = array_merge($errors, $securityValidation['errors']);
        }
        $warnings = array_merge($warnings, $securityValidation['warnings']);

        // Virus scanning (if enabled)
        if ($options['virus_scan'] ?? false) {
            $virusValidation = self::scanForViruses($file);
            if (!$virusValidation['valid']) {
                $errors = array_merge($errors, $virusValidation['errors']);
            }
        }

        // Image-specific validation
        if (in_array(self::TYPE_IMAGE, $allowedTypes) && self::isImageFile($file)) {
            $imageValidation = self::validateImage($file, $options);
            if (!$imageValidation['valid']) {
                $errors = array_merge($errors, $imageValidation['errors']);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'file_info' => [
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type'],
                'extension' => pathinfo($file['name'], PATHINFO_EXTENSION),
                'detected_type' => $typeValidation['detected_type'] ?? null,
                'dimensions' => $imageValidation['dimensions'] ?? null
            ]
        ];
    }

    /**
     * Validate file structure
     */
    private static function validateFileStructure(array $file): bool
    {
        $requiredKeys = ['name', 'type', 'tmp_name', 'error', 'size'];
        foreach ($requiredKeys as $key) {
            if (!isset($file[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage(int $error): string
    {
        return match($error) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds HTML form MAX_FILE_SIZE limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error'
        };
    }

    /**
     * Validate file type
     */
    private static function validateFileType(array $file, array $allowedTypes): array
    {
        $errors = [];
        $detectedType = null;

        // Check MIME type
        $mimeType = $file['type'];
        $allowedMimeTypes = [];
        
        foreach ($allowedTypes as $type) {
            if (isset(self::ALLOWED_MIME_TYPES[$type])) {
                $allowedMimeTypes = array_merge($allowedMimeTypes, self::ALLOWED_MIME_TYPES[$type]);
            }
        }

        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = "File type '$mimeType' is not allowed";
        }

        // Detect actual file type using file content
        $actualMimeType = self::getActualMimeType($file['tmp_name']);
        if ($actualMimeType && $actualMimeType !== $mimeType) {
            $errors[] = "File type mismatch: declared '$mimeType' but detected '$actualMimeType'";
        }

        // Determine detected type
        foreach (self::ALLOWED_MIME_TYPES as $type => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes)) {
                $detectedType = $type;
                break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'detected_type' => $detectedType
        ];
    }

    /**
     * Get actual MIME type from file content
     */
    private static function getActualMimeType(string $filePath): ?string
    {
        if (empty($filePath) || !file_exists($filePath)) {
            return null;
        }

        // Use finfo for MIME type detection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mimeType ?: null;
    }

    /**
     * Validate file extension
     */
    private static function validateFileExtension(string $filename): array
    {
        $errors = [];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Check for dangerous extensions
        if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
            $errors[] = "File extension '$extension' is not allowed for security reasons";
        }

        // Check for double extensions (e.g., file.php.jpg)
        if (preg_match('/\.(php|asp|jsp|exe|bat|cmd|com|scr|pif|js|vbs|wsf|hta|jar|war|ear|sh|bash|csh|ksh|tcsh|pl|py|rb|lua|dll|so|dylib|bin|sys|drv)\.[a-zA-Z0-9]+$/i', $filename)) {
            $errors[] = "File has suspicious double extension";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate file content
     */
    private static function validateFileContent(array $file): array
    {
        $errors = [];
        
        // Check if file exists and is readable
        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            $errors[] = 'File does not exist or is not accessible';
            return ['valid' => false, 'errors' => $errors];
        }
        
        $content = file_get_contents($file['tmp_name']);

        if ($content === false) {
            $errors[] = 'Unable to read file content';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check for PHP tags in content
        if (preg_match('/<\?php|<script|<%|<asp:/i', $content)) {
            $errors[] = 'File contains potentially dangerous code';
        }

        // Check for null bytes (indicator of binary file masquerading as text)
        if (strpos($content, "\x00") !== false) {
            $errors[] = 'File contains null bytes';
        }

        // Check for executable content
        if (preg_match('/MZ|PE|ELF|Mach-O/i', bin2hex(substr($content, 0, 16)))) {
            $errors[] = 'File appears to be an executable';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Perform security checks
     */
    private static function performSecurityChecks(array $file): array
    {
        $errors = [];
        $warnings = [];

        // Check file size for suspicious patterns
        if ($file['size'] === 0) {
            $warnings[] = 'File is empty';
        }

        // Check for suspicious file names
        if (preg_match('/\.(php|asp|jsp|exe|bat|cmd|com|scr|pif|js|vbs|wsf|hta|jar|war|ear|sh|bash|csh|ksh|tcsh|pl|py|rb|lua|dll|so|dylib|bin|sys|drv)$/i', $file['name'])) {
            $errors[] = 'File name contains suspicious extension';
        }

        // Check for path traversal attempts
        if (strpos($file['name'], '..') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
            $errors[] = 'File name contains path traversal characters';
        }

        // Check for overly long file names
        if (strlen($file['name']) > 255) {
            $errors[] = 'File name is too long';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Scan for viruses (placeholder - integrate with actual antivirus)
     */
    private static function scanForViruses(array $file): array
    {
        // This is a placeholder - integrate with actual antivirus software
        // Examples: ClamAV, VirusTotal API, etc.
        
        $errors = [];
        
        // Basic heuristic check for suspicious patterns
        $content = file_get_contents($file['tmp_name']);
        if ($content !== false) {
            // Check for common virus signatures (basic example)
            $suspiciousPatterns = [
                '/eval\s*\(/i',
                '/base64_decode\s*\(/i',
                '/system\s*\(/i',
                '/shell_exec\s*\(/i',
                '/exec\s*\(/i'
            ];

            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $errors[] = 'File contains suspicious code patterns';
                    break;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check if file is an image
     */
    private static function isImageFile(array $file): bool
    {
        $imageTypes = self::ALLOWED_MIME_TYPES[self::TYPE_IMAGE];
        return in_array($file['type'], $imageTypes);
    }

    /**
     * Validate image file
     */
    private static function validateImage(array $file, array $options): array
    {
        $errors = [];
        $dimensions = null;

        // Check if file exists
        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            $errors[] = 'Image file does not exist or is not accessible';
            return ['valid' => false, 'errors' => $errors];
        }

        // Get image information
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $errors[] = 'Invalid image file';
            return ['valid' => false, 'errors' => $errors];
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $dimensions = ['width' => $width, 'height' => $height];

        // Check minimum dimensions
        $minWidth = $options['min_width'] ?? 1;
        $minHeight = $options['min_height'] ?? 1;
        
        if ($width < $minWidth || $height < $minHeight) {
            $errors[] = "Image dimensions must be at least {$minWidth}x{$minHeight} pixels";
        }

        // Check maximum dimensions
        $maxWidth = $options['max_width'] ?? 10000;
        $maxHeight = $options['max_height'] ?? 10000;
        
        if ($width > $maxWidth || $height > $maxHeight) {
            $errors[] = "Image dimensions must not exceed {$maxWidth}x{$maxHeight} pixels";
        }

        // Check aspect ratio if specified
        if (isset($options['aspect_ratio'])) {
            $expectedRatio = $options['aspect_ratio'];
            $actualRatio = $width / $height;
            $tolerance = $options['aspect_tolerance'] ?? 0.1;
            
            if (abs($actualRatio - $expectedRatio) > $tolerance) {
                $errors[] = "Image aspect ratio does not match required ratio";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'dimensions' => $dimensions
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get allowed MIME types for a specific file type
     */
    public static function getAllowedMimeTypes(string $type): array
    {
        return self::ALLOWED_MIME_TYPES[$type] ?? [];
    }

    /**
     * Get maximum file size for a specific file type
     */
    public static function getMaxFileSize(string $type): int
    {
        return self::DEFAULT_MAX_SIZES[$type] ?? self::DEFAULT_MAX_SIZES[self::TYPE_DOCUMENT];
    }

    /**
     * Check if file extension is dangerous
     */
    public static function isDangerousExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::DANGEROUS_EXTENSIONS);
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path traversal characters
        $filename = str_replace(['..', '/', '\\'], '', $filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 255 - strlen($extension) - 1) . '.' . $extension;
        }
        
        return $filename;
    }

    /**
     * Generate secure filename
     */
    public static function generateSecureFilename(string $originalName, string $prefix = ''): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return $prefix . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Validate multiple files
     *
     * @param array $files Array of file arrays
     * @param array $options Validation options
     * @return array Validation result
     */
    public static function validateMultipleFiles(array $files, array $options = []): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'valid_files' => [],
            'invalid_files' => []
        ];

        foreach ($files as $index => $file) {
            $fileResult = self::validateFile($file, $options);
            
            if ($fileResult['valid']) {
                $result['valid_files'][] = $file;
            } else {
                $result['valid'] = false;
                $result['invalid_files'][] = $file;
                $result['errors'][] = "File " . ($index + 1) . " (" . $file['name'] . "): " . implode(', ', $fileResult['errors']);
            }
        }

        return $result;
    }
} 