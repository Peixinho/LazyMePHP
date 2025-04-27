var LazyMePHP = LazyMePHP || {};

var ValidationsMethods = {
  STRING: 0,
  LATIN: 1,
  FLOAT: 2,
  INT: 3,
  NOTNULL: 4,
  LENGTH: 5,
  DATE: 6,
  POSTAL: 7,
  EMAIL: 8,
  LATINPUNCTUATION: 9,
};

LazyMePHP.Init = function () {
  // Add maxlength to all number inputs
  el = document.querySelectorAll('input[type=number]');
  for (let i=0;i<el.length;++i) {
    if (el[i].getAttribute('maxlength'))
      el[i].addEventListener('keydown', function(e) {
        let maxlength = e.target.getAttribute('maxlength');
        let t = e.target.value;
        if (e.which!=8 && (e.which==69 || t.length>=maxlength)) e.preventDefault();
      });
  }

  if (typeof Init == "function") Init();
};

LazyMePHP.ShowError = function (msg) {
  /**
    * Change this function to treat messages differently
    */
    alert(msg);
};
LazyMePHP.ShowSuccess = function (msg) {
  /**
    * Change this function to treat messages differently
    */
    alert(msg);
};


/**
  * Validates a form and provides visual feedback.
  * @param {HTMLFormElement} form The form to validate.
  * @returns {Object} Validation result with success, errors, and validated data.
  */
  LazyMePHP.ValidationsMethods = {
    STRING: 'STRING',
    FLOAT: 'FLOAT',
    INT: 'INT',
    NOTNULL: 'NOTNULL',
    LENGTH: 'LENGTH',
    DATE: 'DATE',
    ISO_DATE: 'ISO_DATE',
    EMAIL: 'EMAIL',
    BOOLEAN: 'BOOLEAN',
    REGEXP: 'REGEXP'
  };

LazyMePHP.ValidationPatterns = {
  FLOAT: /^[+-]?\d*\.?\d+$/,
  INT: /^[+-]?\d+$/,
  STRING: /^[a-zA-Z0-9\s.,;:!?=\'"\-()A-zÀ-ú ]*$/,
  DATE: /^\d{2}\/\d{2}\/\d{4}$/,
  ISO_DATE: /^\d{4}-\d{2}-\d{2}(?:\s\d{2}:\d{2}:\d{2})?$/,
  EMAIL: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
};

/**
  * Initializes validation for a form, binding to the submit event.
  * @param {HTMLFormElement|string} formOrSelector The form element or a CSS selector.
  * @returns {boolean} True if initialization was successful.
  */
  LazyMePHP.InitFormValidation = function(formOrSelector) {
    const form = typeof formOrSelector === 'string' 
      ? document.querySelector(formOrSelector) 
      : formOrSelector;

    if (!(form instanceof HTMLFormElement)) {
      console.error('InitFormValidation: Argument must be an HTMLFormElement or valid selector');
      return false;
    }

    form.addEventListener('submit', function(event) {
      const result = LazyMePHP.ValidateForm(form, event);
      if (!result.success) {
        event.preventDefault();
        event.stopPropagation();
      }
    });

    return true;
  };

/**
  * Validates a form and provides visual feedback.
  * @param {HTMLFormElement} form The form to validate.
  * @param {SubmitEvent} [event] Optional submit event to prevent default behavior.
  * @returns {Object} Validation result with success, errors, and validated data.
  */
  LazyMePHP.ValidateForm = function(form, event) {
    if (!(form instanceof HTMLFormElement)) {
      console.error('ValidateForm: Argument must be an HTMLFormElement');
      return { success: false, errors: { general: ['Invalid form element'] }, validated_data: {}, metadata: { validated_fields: [], failed_fields: [] } };
    }

    // Clear previous validation states
    form.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
      el.classList.remove('is-valid', 'is-invalid');
      const feedback = el.nextElementSibling;
      if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.remove();
      }
    });

    const errors = {};
    const validatedData = {};
    const validatedFields = [];
    const failedFields = [];
    const elements = form.querySelectorAll('input,select,textarea:not([disabled])');

    elements.forEach(element => {
      const fieldName = element.name || element.id || `field_${elements.length}`;
      const validationAttr = element.getAttribute('validation');
      if (!validationAttr) return;

      const validationMethods = validationAttr.split(',').map(method => method.trim());
      const paramsAttr = element.getAttribute('data-validation-params');
      let params = {};
      try {
        params = paramsAttr ? JSON.parse(paramsAttr) : {};
      } catch (e) {
        console.warn(`Invalid data-validation-params for ${fieldName}: ${e.message}`);
      }

      const result = LazyMePHP.ValidateField(element, validationMethods, params);

      if (!result.valid) {
        errors[fieldName] = result.errors;
        failedFields.push(fieldName);
        LazyMePHP.MarkFieldInvalid(element, result.errors);
      } else {
        validatedData[fieldName] = result.value;
        validatedFields.push(fieldName);
        LazyMePHP.MarkFieldValid(element);
      }
    });

    const success = Object.keys(errors).length === 0;
    if (!success && event) {
      const failMsg = form.getAttribute('validation-fail') || 'Please correct the errors in the form.';
      LazyMePHP.ShowError(failMsg);
    }

    return {
      success,
      errors,
      validated_data: validatedData,
      metadata: {
        validated_fields: validatedFields,
        failed_fields: failedFields
      }
    };
  };

/**
  * Validates a single form field.
  * @param {HTMLElement} field The form field element.
  * @param {string[]} methods Array of validation method names.
  * @param {Object} params Optional validation parameters (e.g., min_length).
  * @returns {Object} Validation result with valid status, errors, and value.
  */
  LazyMePHP.ValidateField = function(field, methods, params = {}) {
    if (!(field instanceof HTMLElement)) {
      return { valid: false, errors: ['Invalid field element'], value: null };
    }

    const value = field.value.trim();
    const errors = [];
    let i = 0;

    while (i < methods.length) {
      const method = methods[i];
      if (!LazyMePHP.ValidationsMethods[method]) {
        errors.push(`Unknown validation method: ${method}`);
        i++;
        continue;
      }

      switch (LazyMePHP.ValidationsMethods[method]) {
        case LazyMePHP.ValidationsMethods.STRING:
          if (!ValidateString(value, params)) {
            const min = params.min_length || 0;
            const max = params.max_length || 'unlimited';
            errors.push(`Must be a valid string (min: ${min}, max: ${max} characters)`);
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.FLOAT:
          if (!ValidateFloat(value)) {
            errors.push('Must be a valid floating-point number');
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.INT:
          if (!ValidateInteger(value)) {
            errors.push('Must be a valid integer');
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.NOTNULL:
          if (!ValidateNotNull(value)) {
            errors.push('Field cannot be empty');
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.LENGTH:
          const length = parseInt(methods[++i], 10);
          if (isNaN(length)) {
            errors.push('LENGTH validation requires a numeric parameter');
          } else if (!ValidateLength(value, length)) {
            errors.push(`Must be exactly ${length} characters long`);
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.DATE:
          if (!ValidateDate(value)) {
            errors.push('Must be a valid date in DD/MM/YYYY format');
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.ISO_DATE:
          if (!ValidateISODate(value)) {
            errors.push('Must be a valid ISO 8601 date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)');
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.EMAIL:
          if (!ValidateEmail(value)) {
            errors.push('Must be a valid email address');
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.BOOLEAN:
          if (!ValidateBoolean(value)) {
            errors.push('Must be a valid boolean (true/false or 0/1)');
          }
          i++;
          break;
        case LazyMePHP.ValidationsMethods.REGEXP:
          const regexp = methods[++i];
          if (!regexp) {
            errors.push('REGEXP validation requires a regex parameter');
          } else if (!ValidateRegExp(value, regexp)) {
            errors.push('Does not match the specified pattern');
          }
          i++;
          break;
      }
    }

    return {
      valid: errors.length === 0,
      errors,
      value: errors.length === 0 ? value : null
    };
  };

/**
  * Marks a field as valid with CSS and removes error messages.
  * @param {HTMLElement} field The form field element.
  */
  LazyMePHP.MarkFieldValid = function(field) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    const feedback = field.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
      feedback.remove();
    }
  };

/**
  * Marks a field as invalid with CSS and displays error messages.
  * @param {HTMLElement} field The form field element.
  * @param {string[]} errors Array of error messages.
  */
  LazyMePHP.MarkFieldInvalid = function(field, errors) {
    field.classList.remove('is-valid');
    field.classList.add('is-invalid');
    const customMsg = field.getAttribute('validation-fail');
    const message = customMsg || errors.join(', ');

    let feedback = field.nextElementSibling;
    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
      feedback = document.createElement('div');
      feedback.classList.add('invalid-feedback');
      field.parentNode.insertBefore(feedback, field.nextSibling);
    }
    feedback.textContent = message;
  };

/**
  * Displays a global error message.
  * @param {string} message The error message.
  */
  LazyMePHP.ShowError = function(message) {
    if (typeof LazyMePHP._ShowError === 'function') {
      LazyMePHP._ShowError(message);
    } else {
      alert(message);
    }
  };

/**
  * Validates that a value is not empty.
  * @param {string} value The value to validate.
  * @returns {boolean} True if not empty.
  */
  function ValidateNotNull(value) {
    return value !== null && value !== '';
  }

/**
  * Validates that a value is a float.
  * @param {string} value The value to validate.
  * @returns {boolean} True if a valid float.
  */
  function ValidateFloat(value) {
    if (!ValidateNotNull(value)) return true;
    return LazyMePHP.ValidationPatterns.FLOAT.test(value);
  }

/**
  * Validates that a value is an integer.
  * @param {string} value The value to validate.
  * @returns {boolean} True if a valid integer.
  */
  function ValidateInteger(value) {
    if (!ValidateNotNull(value)) return true;
    return LazyMePHP.ValidationPatterns.INT.test(value);
  }

/**
  * Validates that a value is a string.
  * @param {string} value The value to validate.
  * @param {Object} params Optional parameters (min_length, max_length, regex).
  * @returns {boolean} True if a valid string.
  */
  function ValidateString(value, params = {}) {
    if (!ValidateNotNull(value)) return true;
    const minLength = params.min_length || 0;
    const maxLength = params.max_length || Number.MAX_SAFE_INTEGER;
    const regex = params.regex ? new RegExp(params.regex) : LazyMePHP.ValidationPatterns.STRING;

    if (value.length < minLength || value.length > maxLength) {
      return false;
    }
    return regex.test(value);
  }

/**
  * Validates that a value has a specific length.
  * @param {string} value The value to validate.
  * @param {number} size The expected length.
  * @returns {boolean} True if the length matches.
  */
  function ValidateLength(value, size) {
    if (!ValidateNotNull(value)) return true;
    return value.length === size;
  }

/**
  * Validates that a value is a date in DD/MM/YYYY format.
  * @param {string} value The value to validate.
  * @returns {boolean} True if a valid date.
  */
  function ValidateDate(value) {
    if (!ValidateNotNull(value)) return true;
    if (!LazyMePHP.ValidationPatterns.DATE.test(value)) return false;
    const [day, month, year] = value.split('/').map(Number);
    const date = new Date(year, month - 1, day);
    return date.getDate() === day && date.getMonth() === month - 1 && date.getFullYear() === year;
  }

/**
  * Validates that a value is an ISO 8601 date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).
  * @param {string} value The value to validate.
  * @returns {boolean} True if a valid ISO date.
  */
  function ValidateISODate(value) {
    if (!ValidateNotNull(value)) return true;
    if (!LazyMePHP.ValidationPatterns.ISO_DATE.test(value)) return false;
    return !isNaN(new Date(value).getTime());
  }

/**
  * Validates that a value is an email address.
  * @param {string} value The value to validate.
  * @returns {boolean} True if a valid email.
  */
  function ValidateEmail(value) {
    if (!ValidateNotNull(value)) return true;
    return LazyMePHP.ValidationPatterns.EMAIL.test(value);
  }

/**
  * Validates that a value is a boolean (true/false, 0/1, on/off).
  * @param {string} value The value to validate.
  * @returns {boolean} True if a valid boolean.
  */
  function ValidateBoolean(value) {
    if (!ValidateNotNull(value)) return true;
    return ['true', 'false', '1', '0', 'on', 'off'].includes(value.toLowerCase());
  }

/**
  * Validates a value against a regular expression.
  * @param {string} value The value to validate.
  * @param {string} regexp The regex pattern.
  * @returns {boolean} True if the value matches.
  */
  function ValidateRegExp(value, regexp) {
    if (!ValidateNotNull(value)) return true;
    try {
      const reg = new RegExp(regexp);
      return reg.test(value);
    } catch (e) {
      console.warn(`Invalid regex: ${regexp}`);
      return false;
    }
  }
Date.now =
  Date.now ||
  function () {
    return +new Date();
  };
