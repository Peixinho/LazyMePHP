<?php
/**
 * LazyMePHP Validation Library
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Validations;

/**
 * Regex patterns for validations.
 */
class ValidationPatterns {
    public const FLOAT = '/^[+-]?\d*\.?\d+$/';
    public const INT = '/^[+-]?\d+$/';
    public const STRING = '/^[\x20-\x7E\xA0-\xFF]*$/';
    public const DATE = '/^\d{4}([\/\-])\d{2}\1\d{2}$/';
    public const DATETIME = '/^\d{4}([\/\-])\d{2}\1\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/';
    public const ISO_DATE = '/^\d{4}-\d{2}-\d{2}(?:\s\d{2}:\d{2}:\d{2})?$/';
    public const EMAIL = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
}

