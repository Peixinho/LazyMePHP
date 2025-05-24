<?php
/**
 * LazyMePHP Validation Library
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Validations;

/**
 * Regex patterns for validations.
 */
class ValidationPatterns {
    public const FLOAT = '/^[+-]?\d*\.?\d+$/';
    public const INT = '/^[+-]?\d+$/';
    public const STRING = '/^[a-zA-Z0-9\s.,;:!?@=\'"\-()A-zÀ-ú ]*$/';
    public const DATE = '/^\d{2}\/\d{2}\/\d{4}$/';
    public const DATETIME = '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/';
    public const ISO_DATE = '/^\d{4}-\d{2}-\d{2}(?:\s\d{2}:\d{2}:\d{2})?$/';
    public const EMAIL = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
}

