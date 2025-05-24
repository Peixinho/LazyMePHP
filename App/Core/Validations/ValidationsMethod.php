<?php
/**
 * LazyMePHP Validation Library
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Validations;

/**
 * Enum for validation methods.
 */
enum ValidationsMethod: string {
    case STRING = 'string';
    case FLOAT = 'float';
    case INT = 'int';
    case NOTNULL = 'notnull';
    case LENGTH = 'length';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case ISO_DATE = 'iso_date';
    case EMAIL = 'email';
    case REGEXP = 'regexp';
    case BOOLEAN = 'boolean';
}

