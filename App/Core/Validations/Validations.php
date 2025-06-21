<?php
/**
 * LazyMePHP Validation Library
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Validations;

class Validations {
  
  /**
   * Validates a field against a list of validation methods.
   *
   * @param mixed $value The value to validate.
   * @param array $validations Array of validation methods and optional parameters.
   * @param array $messages Optional array of custom error messages for each validation rule.
   * @return array Array of error messages; empty if all validations pass.
   * @throws \InvalidArgumentException If validation parameters are invalid.
   */
  static function ValidateField(mixed $value, array $validations, array $messages = []): array
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
                  if (!self::ValidateString($value, $params)) {
                      $min = $params['min_length'] ?? 0;
                      $max = $params['max_length'] ?? 'unlimited';
                      $errors[] = $messages[ValidationsMethod::STRING->value] ?? "Value must be a valid string (min: $min, max: $max characters).";
                  }
                  $i += is_array($validations[$i + 1] ?? null) ? 2 : 1;
                  break;

              case ValidationsMethod::FLOAT:
                  if (!self::ValidateFloat($value)) {
                      $errors[] = $messages[ValidationsMethod::FLOAT->value] ?? 'Value must be a valid floating-point number.';
                  }
                  $i++;
                  break;

              case ValidationsMethod::INT:
                  if (!self::ValidateInteger($value)) {
                      $errors[] = $messages[ValidationsMethod::INT->value] ?? 'Value must be a valid integer.';
                  }
                  $i++;
                  break;

              case ValidationsMethod::NOTNULL:
                  if (!self::ValidateNotNull($value)) {
                      $errors[] = $messages[ValidationsMethod::NOTNULL->value] ?? 'Value cannot be null or empty.';
                  }
                  $i++;
                  break;

              case ValidationsMethod::LENGTH:
                  if (!isset($validations[++$i]) || !is_int($validations[$i])) {
                      throw new \InvalidArgumentException('LENGTH validation requires an integer parameter.');
                  }
                  if (!self::ValidateLength($value, $validations[$i])) {
                      $errors[] = $messages[ValidationsMethod::LENGTH->value] ?? "Value must be exactly {$validations[$i]} characters long.";
                  }
                  $i++;
                  break;

              case ValidationsMethod::DATE:
                  if (!self::ValidateDate($value)) {
                      $errors[] = $messages[ValidationsMethod::DATE->value] ?? 'Value must be a valid date.';
                  }
                  $i++;
                  break;

              case ValidationsMethod::EMAIL:
                  if (!self::ValidateEmail($value)) {
                      $errors[] = $messages[ValidationsMethod::EMAIL->value] ?? 'Value must be a valid email address.';
                  }
                  $i++;
                  break;

              case ValidationsMethod::REGEXP:
                  if (!isset($validations[++$i]) || !is_string($validations[$i])) {
                      throw new \InvalidArgumentException('REGEXP validation requires a string regex parameter.');
                  }
                  if (!self::ValidateRegExp($value, $validations[$i])) {
                      $errors[] = $messages[ValidationsMethod::REGEXP->value] ?? 'Value does not match the specified regular expression.';
                  }
                  $i++;
                  break;

              case ValidationsMethod::BOOLEAN:
                  // ValidateBoolean will now modify $value by reference to true/false if valid
                  if (!self::ValidateBoolean($value)) { 
                      $errors[] = $messages[ValidationsMethod::BOOLEAN->value] ?? 'Value must be a valid boolean representation (e.g., true, false, 1, 0, on, off).';
                  }
                  // After ValidateBoolean, $value is either the original if invalid, or an actual boolean if valid.
                  $i++;
                  break;

              case ValidationsMethod::DATETIME:
                  if (!self::ValidateDateTime($value)) {
                      $errors[] = $messages[ValidationsMethod::DATETIME->value] ?? 'Value must be a valid date/time in YYYY-MM-DD or YYYY/MM/DD, optionally with hours.';
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
  static function ValidateNotNull(mixed $value): bool
  {
      return $value !== null && $value !== '';
  }

  /**
   * Validates that a value is a float.
   *
   * @param mixed $value The value to validate.
   * @return bool True if the value is a valid float.
   */
  static function ValidateFloat(mixed $value): bool
  {
      return (is_numeric($value) && self::ValidateRegExp((string)$value, ValidationPatterns::FLOAT)) || empty($value);
  }

  /**
   * Validates that a value is an integer.
   *
   * @param mixed $value The value to validate.
   * @return bool True if the value is a valid integer.
   */
  static function ValidateInteger(mixed $value): bool
  {
      return (is_numeric($value) && self::ValidateRegExp((string)$value, ValidationPatterns::INT)) || empty($value);
  }

  /**
  * @param array{min_length?: int, max_length?: int, regex?: string, sanitize?: bool} $params
  */
  static function ValidateString(mixed $value, array $params = []): bool
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

      if (!empty($params['sanitize'])) {
        $value = strip_tags($value);
      }

      return self::ValidateRegExp($value, $regex) || empty($value);
  }

  /**
   * Validates that a value has a specific length.
   *
   * @param mixed $value The value to validate.
   * @param int $size The expected length.
   * @return bool True if the value has the specified length.
   */
  static function ValidateLength(mixed $value, int $size): bool
  {
      return is_string($value) && strlen($value) === $size;
  }

  /**
   * Validates that a value is a date in DD/MM/YYYY format.
   *
   * @param mixed $value The value to validate.
   * @return bool True if the value is a valid date.
   */
  static function ValidateDate(mixed $value): bool
  {
      if (!is_string($value) || !self::ValidateRegExp($value, ValidationPatterns::DATE)) {
          return false;
      }
      // Accept both YYYY/MM/DD and YYYY-MM-DD
      $parts = preg_split('/[\/\-]/', $value);
      return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]) || empty($value);
  }

  /**
   * Validates that a value is an ISO 8601 date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).
   *
   * @param mixed $value The value to validate.
   * @return bool True if the value is a valid ISO 8601 date.
   */
  static function ValidateISODate(mixed $value): bool
  {
      if (!is_string($value) || !self::ValidateRegExp($value, ValidationPatterns::ISO_DATE)) {
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
  static function ValidateEmail(mixed $value): bool
  {
      return (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) || empty($value);
  }

  /**
   * Validates that a value is a boolean (true/false or 0/1).
   *
   * @param mixed $value The value to validate.
   * @return bool True if the value is a valid boolean.
   */
  static function ValidateBoolean(mixed &$value): bool // Pass by reference
  {
      if (is_bool($value)) {
          return true; // It's already a boolean, $value is not changed.
      }

      // Normalize string values to lowercase for case-insensitive comparison.
      // For non-string, non-bool values (like int 0, 1), use them as is for in_array checks.
      $normalizedValue = is_string($value) ? strtolower(trim($value)) : $value;

      if (in_array($normalizedValue, ['true', '1', 'on', 1], true)) {
          $value = true; // Update original value to actual boolean true
          return true;
      }
      
      // For boolean validation, null, empty string, 'false', '0', 'off', and 0 are all treated as 'false'.
      // This covers cases like optional checkboxes not being submitted (resulting in null)
      // or submitting an empty value, or explicitly submitting a "false" equivalent.
      if ($value === null || $value === '' || in_array($normalizedValue, ['false', '0', 'off', 0], true)) {
          $value = false; // Update original value to actual boolean false
          return true;
      }

      // If the value is none of the above, it's not a recognized boolean representation.
      return false; 
  }

  /**
   * Validates a value against a regular expression.
   *
   * @param mixed $value The value to validate.
   * @param string $regexp The regular expression.
   * @return bool True if the value matches the regex.
   */
  static function ValidateRegExp(mixed $value, string $regexp): bool
  {
      if ($value === null || $value === '') {
          return true; // Empty values are allowed
      }
      return is_string($value) && preg_match($regexp, $value) === 1;
  }

  static function ValidateDateTime(mixed $value): bool
  {
      if (!is_string($value) || !self::ValidateRegExp($value, ValidationPatterns::DATETIME)) {
          return false;
      }
      // Extract date part
      $datePart = preg_split('/[ T]/', $value)[0];
      $parts = preg_split('/[\/\-]/', $datePart);
      return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]) || empty($value);
  }

  /**
   * Validates form data against a set of validation rules.
   *
   * @param array $validationRules The validation rules to apply.
   * @return array An array containing validation results.
   */
  static function ValidateFormData(array $validationRules): array
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
        'int' => FILTER_SANITIZE_NUMBER_INT, // Or FILTER_VALIDATE_INT if strict format needed pre-validation
        'float' => FILTER_SANITIZE_NUMBER_FLOAT, // Or FILTER_VALIDATE_FLOAT
        'bool' => FILTER_DEFAULT, // Boolean needs special handling for 'on', 'off', 'true', 'false'
        'string', 'iso_date' => FILTER_DEFAULT,
        default => FILTER_DEFAULT
      };
      // For booleans, filter_input with FILTER_VALIDATE_BOOLEAN has specific behaviors
      // (e.g. "false", "off", "0", "" are false, "true", "on", "1" are true).
      // We'll get the raw value for our custom ValidateBoolean.
      $value = isset($_POST[$field]) ? $_POST[$field] : null;
      
      $trim = $rule['trim'] ?? true;
      // Trimming should only apply to strings.
      if (is_string($value) && $trim) {
          $value = trim($value);
      } else if ($value === null && ($rule['type'] === 'bool' && !($rule['required'] ?? true))) {
          // For optional boolean fields, if not present in POST, it implies false.
          // The previous `filter_input` with `FILTER_VALIDATE_BOOLEAN` would yield `false`.
          // With direct `$_POST` access, `null` means not present.
          // Our `ValidateBoolean` will get `null`, which it will currently reject.
          // The casting in `ValidateFormData` `in_array($value, ...)` would turn `null` to `0`.
          // This is an important interaction. If an optional checkbox is not sent, it should be `false`.
          // Let's ensure $value is explicitly `false` if it's an optional bool and not in POST.
          // This makes it consistent for ValidateBoolean.
          // However, ValidateNotNull should handle required. If it's not required and not present, it's effectively false.
          // The current ValidateBoolean will return false for null. ValidateField will add error.
          // This needs to be handled carefully.
          // If a boolean is not required and not present, it should be considered `false` without error.
          // This is a gap.
      }

      // Check for missing field.
      // If an optional boolean is not present in POST, $value will be null.
      // The updated ValidateBoolean will correctly interpret null as `false`.
      if (!isset($_POST[$field]) && !array_key_exists($field, $_POST)) { // array_key_exists for fields sent as null
          if ($rule['required'] ?? true) {
              // For required fields (including booleans that must be explicitly true, if such rule exists apart from NOTNULL)
              $errors[$field] = ['Field is required.'];
              $missingFields[] = $field;
              continue; 
          } else if ($rule['type'] === 'bool') {
              // Optional boolean not present in POST, it defaults to false (handled by ValidateBoolean(null))
              $value = null; // Explicitly set to null to go through ValidateBoolean
          }
           else {
              // Optional non-boolean field not present
              $validatedData[$field] = null; 
              $validatedFields[] = $field;
              continue; 
          }
      }
      
      // Apply validations
      $validations = $rule['validations'];
      // $value is passed to ValidateField. If a boolean validation is present, 
      // ValidateBoolean (called by ValidateField) will modify $value by reference.
      $fieldErrors = self::ValidateField($value, $validations, $rule['messages'] ?? []); 
      
      if ($fieldErrors) {
          $errors[$field] = $fieldErrors;
          continue;
      }
      // After successful validation, $value now holds the potentially normalized value 
      // (e.g., actual boolean for boolean types).

      // Handle cases where $value might be an empty string after validation (e.g. optional string field)
      // and it's not required. This is effectively a 'null' or 'not provided' state.
      // For boolean, empty string is now treated as false by ValidateBoolean.
      if ($value === '' && !($rule['required'] ?? true) && $rule['type'] !== 'bool') {
          $validatedData[$field] = null; // Store as null if optional and empty string (for non-booleans)
          $validatedFields[] = $field;
          continue;
      }

      // In ValidateFormData, after $fieldErrors and before type casting:
      if (isset($rule['callback']) && is_callable($rule['callback'])) {
          $callbackError = $rule['callback']($value);
          if ($callbackError !== null) {
              if (!isset($errors[$field])) {
                  $errors[$field] = [];
              }
              $errors[$field][] = $callbackError;
              continue;
          }
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
          // $value should now be an actual boolean (true/false) if ValidateBoolean ran and was successful.
          // Or it could be an unvalidated value if ValidateBoolean wasn't in the chain or failed early.
          // The in_array check is robust for various inputs if $value wasn't normalized.
          if (is_bool($value)) {
            $validatedData[$field] = $value; // Return actual boolean, not integer
          } else {
            // Fallback for safety, though ValidateBoolean should have normalized it.
            $validatedData[$field] = in_array(is_string($value) ? strtolower($value) : $value, [true, '1', 1, 'true', 'on'], true);
          }
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

  static function ValidateJsonData($jsonInput, array $validationRules): array
  {
      $validatedData = [];
      $errors = [];
      $missingFields = [];
      $validatedFields = [];

      // Handle JSON input (string or array)
      $inputData = is_string($jsonInput) ? json_decode($jsonInput, true) : $jsonInput;
      if (!is_array($inputData)) {
          throw new \InvalidArgumentException('JSON input must be a valid JSON string or an array.');
      }

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
          // Get value from JSON input
          $value = isset($inputData[$field]) ? $inputData[$field] : null;
          $trim = $rule['trim'] ?? true;

          // Apply trimming if applicable
          if ($value !== null && $trim && is_string($value)) {
              $value = trim($value);
          }

          // Check for missing field
          if ($value === null && !array_key_exists($field, $inputData)) {
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
          $fieldErrors = self::ValidateField($value, $validations, $rule['messages'] ?? []);
          if ($fieldErrors) {
              $errors[$field] = $fieldErrors;
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

          // In ValidateJsonData, after $fieldErrors and before type casting:
          if (isset($rule['callback']) && is_callable($rule['callback'])) {
              $callbackError = $rule['callback']($value);
              if ($callbackError !== null) {
                  if (!isset($errors[$field])) {
                      $errors[$field] = [];
                  }
                  $errors[$field][] = $callbackError;
                  continue;
              }
          }

          // Cast to the appropriate type
          try {
              switch ($rule['type']) {
                  case 'int':
                      if (is_numeric($value) && floor(floatval($value)) == $value) {
                          $validatedData[$field] = (int)$value;
                      } else {
                          $errors[$field] = ['Value must be an integer.'];
                          continue 2;
                      }
                      break;
                  case 'float':
                      if (is_numeric($value)) {
                          $validatedData[$field] = (float)$value;
                      } else {
                          $errors[$field] = ['Value must be a number.'];
                          continue 2;
                      }
                      break;
                  case 'bool':
                      // $value should be an actual boolean (true/false) if ValidateBoolean ran.
                      if (is_bool($value)) {
                          $validatedData[$field] = $value; // Return actual boolean, not integer
                      } else {
                          // Fallback for various JSON representations if not pre-normalized by ValidateBoolean
                           $normalizedJsonBool = is_string($value) ? strtolower(trim($value)) : $value;
                           $validatedData[$field] = in_array($normalizedJsonBool, [true, '1', 1, 'true', 'on'], true);
                      }
                      break;
                  case 'string':
                      $validatedData[$field] = (string)$value;
                      break;
                  case 'iso_date':
                      // Verify ISO 8601 date format
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
}