<?php
/**
 * LazyMePHP Validation Library
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace LazyMePHP\Validations;

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
    case ISO_DATE = 'iso_date';
    case EMAIL = 'email';
    case REGEXP = 'regexp';
    case BOOLEAN = 'boolean';
}

/**
 * Regex patterns for validations.
 */
class ValidationPatterns {
    public const FLOAT = '/^[+-]?\d*\.?\d+$/';
    public const INT = '/^[+-]?\d+$/';
    public const STRING = '/^^[a-zA-Z0-9][a-zA-Z0-9\s.,;:!?=\'"\-()]*$/';
    public const DATE = '/^\d{2}\/\d{2}\/\d{4}$/';
    public const ISO_DATE = '/^\d{4}-\d{2}-\d{2}(?:\s\d{2}:\d{2}:\d{2})?$/';
    public const EMAIL = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
}

/**
 * Validates a field against a list of validation methods.
 *
 * @param mixed $value The value to validate.
 * @param array $validations Array of validation methods and optional parameters.
 * @return array Array of error messages; empty if all validations pass.
 * @throws \InvalidArgumentException If validation parameters are invalid.
 */
function ValidateField(mixed $value, array $validations): array
{
    $errors = [];
    $i = 0;

    while ($i < count($validations)) {
        if (!($validations[$i] instanceof ValidationsMethod)) {
            throw new \InvalidArgumentException("Validation at index $i must be a ValidationsMethod enum.");
        }

        switch ($validations[$i]) {
            case ValidationsMethod::STRING:
                $params = isset($validations[$i + 1]) && is_array($validations[$i + 1]) ? $validations[$i + 1] : [];
                if (!ValidateString($value, $params)) {
                    $min = $params['min_length'] ?? 0;
                    $max = $params['max_length'] ?? 'unlimited';
                    $errors[] = "Value must be a valid string (min: $min, max: $max characters).";
                }
                $i += is_array($validations[$i + 1] ?? null) ? 2 : 1;
                break;

            case ValidationsMethod::FLOAT:
                if (!ValidateFloat($value)) {
                    $errors[] = 'Value must be a valid floating-point number.';
                }
                $i++;
                break;

            case ValidationsMethod::INT:
                if (!ValidateInteger($value)) {
                    $errors[] = 'Value must be a valid integer.';
                }
                $i++;
                break;

            case ValidationsMethod::NOTNULL:
                if (!ValidateNotNull($value)) {
                    $errors[] = 'Value cannot be null or empty.';
                }
                $i++;
                break;

            case ValidationsMethod::LENGTH:
                if (!isset($validations[++$i]) || !is_int($validations[$i])) {
                    throw new \InvalidArgumentException('LENGTH validation requires an integer parameter.');
                }
                if (!ValidateLength($value, $validations[$i])) {
                    $errors[] = "Value must be exactly {$validations[$i]} characters long.";
                }
                $i++;
                break;

            case ValidationsMethod::DATE:
                if (!ValidateDate($value)) {
                    $errors[] = 'Value must be a valid date in DD/MM/YYYY format.';
                }
                $i++;
                break;

            case ValidationsMethod::ISO_DATE:
                if (!ValidateISODate($value)) {
                    $errors[] = 'Value must be a valid ISO 8601 date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).';
                }
                $i++;
                break;

            case ValidationsMethod::EMAIL:
                if (!ValidateEmail($value)) {
                    $errors[] = 'Value must be a valid email address.';
                }
                $i++;
                break;

            case ValidationsMethod::REGEXP:
                if (!isset($validations[++$i]) || !is_string($validations[$i])) {
                    throw new \InvalidArgumentException('REGEXP validation requires a string regex parameter.');
                }
                if (!ValidateRegExp($value, $validations[$i])) {
                    $errors[] = 'Value does not match the specified regular expression.';
                }
                $i++;
                break;

            case ValidationsMethod::BOOLEAN:
                if (!ValidateBoolean($value)) {
                    $errors[] = 'Value must be a valid boolean (true/false or 0/1).';
                }
                $i++;
                break;

            default:
                throw new \InvalidArgumentException("Unknown validation method: {$validations[$i]->value}");
        }
    }

    return $errors;
}

/**
 * Validates that a value is not null or empty.
 *
 * @param mixed $value The value to validate.
 * @return bool True if the value is not null and not empty.
 */
function ValidateNotNull(mixed $value): bool
{
    return $value !== null && $value !== '';
}

/**
 * Validates that a value is a float.
 *
 * @param mixed $value The value to validate.
 * @return bool True if the value is a valid float.
 */
function ValidateFloat(mixed $value): bool
{
    return is_numeric($value) && ValidateRegExp((string)$value, ValidationPatterns::FLOAT);
}

/**
 * Validates that a value is an integer.
 *
 * @param mixed $value The value to validate.
 * @return bool True if the value is a valid integer.
 */
function ValidateInteger(mixed $value): bool
{
    return is_numeric($value) && ValidateRegExp((string)$value, ValidationPatterns::INT);
}

/**
 * Validates that a value is a string with alphanumeric characters, spaces, and common punctuation.
 *
 * @param mixed $value The value to validate.
 * @param array{min_length?: int, max_length?: int, regex?: string} $params Optional parameters:
 *   - min_length: Minimum string length (default: 0).
 *   - max_length: Maximum string length (default: unlimited).
 *   - regex: Custom regex pattern to override default.
 * @return bool True if the value is a valid string.
 */
function ValidateString(mixed $value, array $params = []): bool
{
    if (!is_string($value)) {
        return false;
    }

    $minLength = $params['min_length'] ?? 0;
    $maxLength = $params['max_length'] ?? PHP_INT_MAX;
    $regex = $params['regex'] ?? ValidationPatterns::STRING;

    if (strlen($value) < $minLength || strlen($value) > $maxLength) {
        return false;
    }

    return ValidateRegExp($value, $regex);
}

/**
 * Validates that a value has a specific length.
 *
 * @param mixed $value The value to validate.
 * @param int $size The expected length.
 * @return bool True if the value has the specified length.
 */
function ValidateLength(mixed $value, int $size): bool
{
    return is_string($value) && strlen($value) === $size;
}

/**
 * Validates that a value is a date in DD/MM/YYYY format.
 *
 * @param mixed $value The value to validate.
 * @return bool True if the value is a valid date.
 */
function ValidateDate(mixed $value): bool
{
    if (!is_string($value) || !ValidateRegExp($value, ValidationPatterns::DATE)) {
        return false;
    }
    $parts = explode('/', $value);
    return checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2]);
}

/**
 * Validates that a value is an ISO 8601 date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).
 *
 * @param mixed $value The value to validate.
 * @return bool True if the value is a valid ISO 8601 date.
 */
function ValidateISODate(mixed $value): bool
{
    if (!is_string($value) || !ValidateRegExp($value, ValidationPatterns::ISO_DATE)) {
        return false;
    }
    try {
        new \DateTime($value);
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Validates that a value is an email address.
 *
 * @param mixed $value The value to validate.
 * @return bool True if the value is a valid email.
 */
function ValidateEmail(mixed $value): bool
{
    return is_string($value) && ValidateRegExp($value, ValidationPatterns::EMAIL);
}

/**
 * Validates that a value is a boolean (true/false or 0/1).
 *
 * @param mixed $value The value to validate.
 * @return bool True if the value is a valid boolean.
 */
function ValidateBoolean(mixed $value): bool
{
    return is_bool($value) || in_array($value, [0, 1, '0', '1'], true);
}

/**
 * Validates a value against a regular expression.
 *
 * @param mixed $value The value to validate.
 * @param string $regexp The regular expression.
 * @return bool True if the value matches the regex.
 */
function ValidateRegExp(mixed $value, string $regexp): bool
{
    if ($value === null || $value === '') {
        return false;
    }
    return is_string($value) && preg_match($regexp, $value) === 1;
}
function ValidateFormData(array $validationRules): array
{
  $validatedData = [];
  $errors = [];
  $missingFields = [];
  $validatedFields = [];

  // Validate rule structure
  foreach ($validationRules as $field => $rule) {
    if (!isset($rule['validations']) || !is_array($rule['validations'])) {
      throw new \InvalidArgumentException("Validation rule for '$field' must include a 'validations' array.");
    }
    if (!isset($rule['type']) || !in_array($rule['type'], ['int', 'float', 'bool', 'string', 'iso_date'], true)) {
      throw new \InvalidArgumentException("Validation rule for '$field' must specify a valid 'type' (int, float, bool, string, iso_date).");
    }
  }

  // Validate each field
  foreach ($validationRules as $field => $rule) {
    // Get input with appropriate filter
    $filter = match ($rule['type']) {
    'int' => FILTER_VALIDATE_INT,
      'float' => FILTER_VALIDATE_FLOAT,
      'bool' => FILTER_VALIDATE_BOOLEAN,
      'string', 'iso_date' => FILTER_DEFAULT,
      default => FILTER_DEFAULT
    };
    $value = filter_input(INPUT_POST, $field, $filter);
    $trim = $rule['trim'] ?? true;
    $value = $value !== false && $value !== null && $trim ? trim((string)$value) : $value;

    // Check for missing field
    if ($value === null && !isset($_POST[$field])) {
      if ($rule['required'] ?? true) {
        $errors[$field] = ['Field is required.'];
        $missingFields[] = $field;
      } else {
        $validatedData[$field] = null;
        $validatedFields[] = $field;
      }
      continue;
    }

    // Apply validations
    $validations = $rule['validations'];
    if (isset($rule['params'])) {
      // Merge params into validations (e.g., min_length for STRING)
      $validations = array_merge($validations, [$rule['params']]);
    }
    $fieldErrors = ValidateField($value, $validations);
    if ($fieldErrors) {
      $errors[$field] = $fieldErrors;
      var_dump($value,$field, $fieldErrors);
      continue;
    }

    // Handle empty values
    if ($value === '' || ($rule['type'] === 'string' && $value === null)) {
      if ($rule['required'] ?? true) {
        $errors[$field] = ['Field cannot be empty.'];
      } else {
        $validatedData[$field] = null;
        $validatedFields[] = $field;
      }
      continue;
    }

    // Cast to the appropriate type
    try {
      switch ($rule['type']) {
      case 'int':
        $validatedData[$field] = (int)$value;
        break;
      case 'float':
        $validatedData[$field] = (float)$value;
        break;
      case 'bool':
        // Handle SQLite INTEGER booleans (0/1) and form inputs
        $validatedData[$field] = in_array($value, [true, '1', 1, 'true', 'on'], true) ? 1 : 0;
        break;
      case 'string':
        $validatedData[$field] = (string)$value;
        break;
      case 'iso_date':
        // Verify ISO_DATE format
        $date = \DateTime::createFromFormat('Y-m-d|', $value) ?: \DateTime::createFromFormat('Y-m-d H:i:s|', $value);
        if (!$date) {
          $errors[$field] = ['Invalid ISO 8601 date format.'];
          continue 2;
        }
        $validatedData[$field] = $value;
        break;
      }
      $validatedFields[] = $field;
    } catch (\Exception $e) {
      $errors[$field] = ["Type casting error: {$e->getMessage()}"];
      continue;
    }
  }

  return [
    'success' => empty($errors),
    'validated_data' => $validatedData,
    'errors' => $errors,
    'metadata' => [
      'missing_fields' => $missingFields,
      'validated_fields' => $validatedFields
    ]
  ];
}
?>
