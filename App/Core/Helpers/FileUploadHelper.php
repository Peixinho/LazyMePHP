<?php

/**
 * LazyMePHP File Upload Helper
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Helpers;

use Core\Validations\FileValidation;
use Core\Helpers\NotificationHelper;

class FileUploadHelper
{
    /**
     * Upload a single file with validation
     *
     * @param array $file The $_FILES array element
     * @param string $destinationPath Destination directory
     * @param array $options Upload options
     * @return array Upload result
     */
    public static function uploadFile(array $file, string $destinationPath, array $options = []): array
    {
        // Validate file
        $validation = FileValidation::validateFile($file, $options);
        
        if (!$validation['valid']) {
            $errorData = [
                'file_name' => $file['name'] ?? 'unknown',
                'file_size' => $file['size'] ?? 0,
                'file_type' => $file['type'] ?? 'unknown',
                'destination_path' => $destinationPath,
                'validation_errors' => $validation['errors'],
                'validation_warnings' => $validation['warnings'] ?? []
            ];
            
            self::logUploadError('File validation failed', $errorData);
            
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings']
            ];
        }

        // Create destination directory if it doesn't exist
        if (!is_dir($destinationPath)) {
            if (!mkdir($destinationPath, 0755, true)) {
                $errorData = [
                    'file_name' => $file['name'] ?? 'unknown',
                    'destination_path' => $destinationPath,
                    'error_type' => 'directory_creation_failed'
                ];
                
                self::logUploadError('Unable to create destination directory', $errorData);
                
                return [
                    'success' => false,
                    'errors' => ['Unable to create destination directory']
                ];
            }
        }

        // Generate secure filename
        $originalName = $file['name'];
        $secureName = $options['keep_original_name'] ?? false 
            ? FileValidation::sanitizeFilename($originalName)
            : FileValidation::generateSecureFilename($originalName, $options['prefix'] ?? '');

        $destinationFile = rtrim($destinationPath, '/') . '/' . $secureName;

        // Check if file already exists
        if (file_exists($destinationFile) && !($options['overwrite'] ?? false)) {
            $errorData = [
                'file_name' => $file['name'] ?? 'unknown',
                'destination_file' => $destinationFile,
                'error_type' => 'file_already_exists'
            ];
            
            self::logUploadError('File already exists', $errorData);
            
            return [
                'success' => false,
                'errors' => ['File already exists']
            ];
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destinationFile)) {
            $errorData = [
                'file_name' => $file['name'] ?? 'unknown',
                'tmp_name' => $file['tmp_name'] ?? 'unknown',
                'destination_file' => $destinationFile,
                'error_type' => 'move_upload_failed'
            ];
            
            self::logUploadError('Failed to move uploaded file', $errorData);
            
            return [
                'success' => false,
                'errors' => ['Failed to move uploaded file']
            ];
        }

        // Set proper permissions
        chmod($destinationFile, $options['permissions'] ?? 0644);

        return [
            'success' => true,
            'file_path' => $destinationFile,
            'file_name' => $secureName,
            'original_name' => $originalName,
            'file_info' => $validation['file_info'],
            'warnings' => $validation['warnings']
        ];
    }

    /**
     * Upload multiple files with validation
     *
     * @param array $files The $_FILES array
     * @param string $destinationPath Destination directory
     * @param array $options Upload options
     * @return array Upload results
     */
    public static function uploadMultipleFiles(array $files, string $destinationPath, array $options = []): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        // Handle multiple file uploads
        foreach ($files as $file) {
            $result = self::uploadFile($file, $destinationPath, $options);
            $results[] = $result;

            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return [
            'success' => $errorCount === 0,
            'results' => $results,
            'summary' => [
                'total' => count($files),
                'successful' => $successCount,
                'failed' => $errorCount
            ]
        ];
    }

    /**
     * Upload image with specific validation
     *
     * @param array $file The $_FILES array element
     * @param string $destinationPath Destination directory
     * @param array $options Image-specific options
     * @return array Upload result
     */
    public static function uploadImage(array $file, string $destinationPath, array $options = []): array
    {
        $imageOptions = array_merge([
            'allowed_types' => [FileValidation::TYPE_IMAGE],
            'max_size' => FileValidation::getMaxFileSize(FileValidation::TYPE_IMAGE),
            'min_width' => $options['min_width'] ?? 1,
            'min_height' => $options['min_height'] ?? 1,
            'max_width' => $options['max_width'] ?? 10000,
            'max_height' => $options['max_height'] ?? 10000,
            'aspect_ratio' => $options['aspect_ratio'] ?? null,
            'aspect_tolerance' => $options['aspect_tolerance'] ?? 0.1
        ], $options);

        $result = self::uploadFile($file, $destinationPath, $imageOptions);

        // Generate thumbnail if requested
        if ($result['success'] && ($options['generate_thumbnail'] ?? false)) {
            $thumbnailResult = self::generateThumbnail(
                $result['file_path'],
                $options['thumbnail_path'] ?? $destinationPath . '/thumbnails',
                $options['thumbnail_width'] ?? 150,
                $options['thumbnail_height'] ?? 150
            );

            if ($thumbnailResult['success']) {
                $result['thumbnail_path'] = $thumbnailResult['file_path'];
            }
        }

        return $result;
    }

    /**
     * Generate thumbnail from image
     *
     * @param string $sourcePath Source image path
     * @param string $destinationPath Thumbnail destination
     * @param int $width Thumbnail width
     * @param int $height Thumbnail height
     * @return array Thumbnail generation result
     */
    public static function generateThumbnail(string $sourcePath, string $destinationPath, int $width, int $height): array
    {
        if (!file_exists($sourcePath)) {
            $errorData = [
                'source_path' => $sourcePath,
                'destination_path' => $destinationPath,
                'error_type' => 'source_image_not_found'
            ];
            
            self::logUploadError('Source image does not exist', $errorData);
            
            return [
                'success' => false,
                'errors' => ['Source image does not exist']
            ];
        }

        // Create thumbnail directory
        if (!is_dir($destinationPath)) {
            if (!mkdir($destinationPath, 0755, true)) {
                return [
                    'success' => false,
                    'errors' => ['Unable to create thumbnail directory']
                ];
            }
        }

        // Get image information
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return [
                'success' => false,
                'errors' => ['Invalid image file']
            ];
        }

        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $sourceType = $imageInfo[2];

        // Calculate thumbnail dimensions
        $ratio = min($width / $sourceWidth, $height / $sourceHeight);
        $thumbWidth = (int)round($sourceWidth * $ratio);
        $thumbHeight = (int)round($sourceHeight * $ratio);

        // Create source image resource
        $sourceImage = match($sourceType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default => null
        };

        if (!$sourceImage) {
            return [
                'success' => false,
                'errors' => ['Unsupported image type']
            ];
        }

        // Create thumbnail image
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // Preserve transparency for PNG and GIF
        if (in_array($sourceType, [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefill($thumbImage, 0, 0, $transparent);
        }

        // Resize image
        imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

        // Generate thumbnail filename
        $sourceName = pathinfo($sourcePath, PATHINFO_FILENAME);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $thumbName = $sourceName . '_thumb.' . $extension;
        $thumbPath = rtrim($destinationPath, '/') . '/' . $thumbName;

        // Save thumbnail
        $success = match($sourceType) {
            IMAGETYPE_JPEG => imagejpeg($thumbImage, $thumbPath, 90),
            IMAGETYPE_PNG => imagepng($thumbImage, $thumbPath, 9),
            IMAGETYPE_GIF => imagegif($thumbImage, $thumbPath),
            IMAGETYPE_WEBP => imagewebp($thumbImage, $thumbPath, 90),
            default => false
        };

        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        if (!$success) {
            return [
                'success' => false,
                'errors' => ['Failed to save thumbnail']
            ];
        }

        return [
            'success' => true,
            'file_path' => $thumbPath,
            'file_name' => $thumbName,
            'dimensions' => ['width' => $thumbWidth, 'height' => $thumbHeight]
        ];
    }

    /**
     * Delete uploaded file
     *
     * @param string $filePath File path to delete
     * @param bool $deleteThumbnail Also delete thumbnail if exists
     * @return array Delete result
     */
    public static function deleteFile(string $filePath, bool $deleteThumbnail = true): array
    {
        if (!file_exists($filePath)) {
            $errorData = [
                'file_path' => $filePath,
                'error_type' => 'file_not_found_for_deletion'
            ];
            
            self::logUploadError('File does not exist for deletion', $errorData);
            
            return [
                'success' => false,
                'errors' => ['File does not exist']
            ];
        }

        // Delete main file
        if (!unlink($filePath)) {
            $errorData = [
                'file_path' => $filePath,
                'error_type' => 'file_deletion_failed'
            ];
            
            self::logUploadError('Failed to delete file', $errorData);
            
            return [
                'success' => false,
                'errors' => ['Failed to delete file']
            ];
        }

        // Delete thumbnail if requested
        if ($deleteThumbnail) {
            $pathInfo = pathinfo($filePath);
            $thumbnailPath = $pathInfo['dirname'] . '/thumbnails/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
            
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }

        return [
            'success' => true,
            'message' => 'File deleted successfully'
        ];
    }

    /**
     * Get file information
     *
     * @param string $filePath File path
     * @return array File information
     */
    public static function getFileInfo(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'errors' => ['File does not exist']
            ];
        }

        $fileInfo = [
            'name' => basename($filePath),
            'path' => $filePath,
            'size' => filesize($filePath),
            'size_formatted' => self::formatBytes(filesize($filePath)),
            'type' => mime_content_type($filePath),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
            'modified' => filemtime($filePath),
            'modified_formatted' => date('Y-m-d H:i:s', filemtime($filePath))
        ];

        // Get image dimensions if it's an image
        if (strpos($fileInfo['type'], 'image/') === 0) {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo !== false) {
                $fileInfo['dimensions'] = [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1]
                ];
            }
        }

        return [
            'success' => true,
            'file_info' => $fileInfo
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
     * Validate and process file upload with notifications
     *
     * @param array $file The $_FILES array element
     * @param string $destinationPath Destination directory
     * @param array $options Upload options
     * @return array Upload result
     */
    public static function processFileUpload(array $file, string $destinationPath, array $options = []): array
    {
        $result = self::uploadFile($file, $destinationPath, $options);

        if ($result['success']) {
            NotificationHelper::success(
                'File uploaded successfully',
                ['file_name' => $result['file_name']]
            );

            // Log warnings if any
            if (!empty($result['warnings'])) {
                foreach ($result['warnings'] as $warning) {
                    NotificationHelper::warning($warning);
                }
            }
        } else {
            // Additional logging for processFileUpload failures
            $errorData = [
                'file_name' => $file['name'] ?? 'unknown',
                'file_size' => $file['size'] ?? 0,
                'file_type' => $file['type'] ?? 'unknown',
                'destination_path' => $destinationPath,
                'upload_errors' => $result['errors'],
                'upload_warnings' => $result['warnings'] ?? [],
                'error_type' => 'process_upload_failed'
            ];
            
            self::logUploadError('File upload processing failed', $errorData);
            
            NotificationHelper::error(
                'File upload failed',
                ['errors' => $result['errors']]
            );
        }

        return $result;
    }

    /**
     * Get upload configuration for forms
     *
     * @param array $allowedTypes Allowed file types
     * @param int|null $maxSize Maximum file size in bytes
     * @return array Configuration for form
     */
    public static function getUploadConfig(array $allowedTypes, ?int $maxSize = null): array
    {
        $mimeTypes = [];
        foreach ($allowedTypes as $type) {
            $mimeTypes = array_merge($mimeTypes, FileValidation::getAllowedMimeTypes($type));
        }

        $maxSize = $maxSize ?? FileValidation::getMaxFileSize($allowedTypes[0] ?? FileValidation::TYPE_DOCUMENT);

        return [
            'accept' => implode(',', $mimeTypes),
            'max_size' => $maxSize,
            'max_size_formatted' => self::formatBytes($maxSize),
            'allowed_types' => $allowedTypes
        ];
    }

    /**
     * Log file upload errors to the logging system
     *
     * @param string $message Error message
     * @param array $context Additional context data
     */
    private static function logUploadError(string $message, array $context = []): void
    {
        try {
            // Check if logging is enabled
            if (!\Core\LazyMePHP::ACTIVITY_LOG()) {
                return;
            }

            // Prepare log data
            $logData = [
                'error_message' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ];

            // Merge context data
            $logData = array_merge($logData, $context);

            // Log to file system as fallback
            $logDir = __DIR__ . '/../../../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logFile = $logDir . '/file_upload_errors.log';
            $logEntry = date('Y-m-d H:i:s') . ' - ' . $message . ' - ' . json_encode($logData) . PHP_EOL;
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

            // Log to database using LoggingHelper
            if (class_exists('Core\Helpers\LoggingHelper')) {
                \Core\Helpers\LoggingHelper::logError(
                    'FILE_UPLOAD_ERROR',
                    $message,
                    500,
                    'ERROR',
                    'FILE_UPLOAD',
                    $logData
                );
            }

        } catch (\Throwable $e) {
            // If logging fails, at least try to write to error log
            error_log("FileUploadHelper logging failed: " . $e->getMessage());
        }
    }
} 