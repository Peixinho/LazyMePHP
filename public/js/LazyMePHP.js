/**
 * LazyMePHP - JavaScript Framework for LazyMePHP
 * A comprehensive JavaScript library for form validation, notifications, and UI interactions
 */

// Main namespace
var LazyMePHP = LazyMePHP || {};

// Validation constants - MUST MATCH PHP ValidationsMethod enum exactly
LazyMePHP.ValidationsMethods = {
  STRING: 'string',
  FLOAT: 'float',
  INT: 'int',
  NOTNULL: 'notnull',
  LENGTH: 'length',
  DATE: 'date',
  DATETIME: 'datetime',
  EMAIL: 'email',
  REGEXP: 'regexp',
  BOOLEAN: 'boolean'
};

// Validation patterns - MUST MATCH PHP ValidationPatterns exactly
LazyMePHP.ValidationPatterns = {
  FLOAT: /^[+-]?\d*\.?\d+$/,
  INT: /^[+-]?\d+$/,
  STRING: /^[\p{L}\p{N}\p{P}\p{Z}\p{S}]*$/u,
  DATE: /^\d{4}([\/\-])\d{2}\1\d{2}$/,
  DATETIME: /^\d{4}([\/\-])\d{2}\1\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/,
  ISO_DATE: /^\d{4}-\d{2}-\d{2}(?:\s\d{2}:\d{2}:\d{2})?$/,
  EMAIL: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
};

// Configuration
LazyMePHP.Config = {
  defaultNotificationDuration: 5000,
  defaultNotificationPosition: 'top-right',
  defaultNotificationAnimation: 'slide',
  validationFailMessage: 'Please correct the errors in the form.'
};

/**
 * Initialize LazyMePHP framework
 */
LazyMePHP.Init = function() {
  // Add maxlength to all number inputs
  const numberInputs = document.querySelectorAll('input[type=number]');
  numberInputs.forEach(input => {
    if (input.getAttribute('maxlength')) {
      input.addEventListener('keydown', function(e) {
        const maxlength = parseInt(e.target.getAttribute('maxlength'));
        const currentValue = e.target.value;
        
        // Allow backspace (key code 8)
        if (e.which !== 8 && (e.which === 69 || currentValue.length >= maxlength)) {
          e.preventDefault();
        }
      });
    }
  });

  // Call custom init function if exists
  if (typeof Init === "function") {
    Init();
  }
};

/**
 * Form Validation Module
 */
LazyMePHP.Validation = {
  /**
   * Initialize validation for a form
   * @param {HTMLFormElement|string} formOrSelector - Form element or CSS selector
   * @returns {boolean} True if initialization was successful
   */
  initForm: function(formOrSelector) {
    const form = typeof formOrSelector === 'string' 
      ? document.querySelector(formOrSelector) 
      : formOrSelector;

    if (!(form instanceof HTMLFormElement)) {
      console.error('InitFormValidation: Argument must be an HTMLFormElement or valid selector');
      return false;
    }

    form.addEventListener('submit', function(event) {
      const result = LazyMePHP.Validation.validateForm(form, event);
      if (!result.success) {
        event.preventDefault();
        event.stopPropagation();
      }
    });

    return true;
  },

  /**
   * Validate a form and provide visual feedback
   * @param {HTMLFormElement} form - Form to validate
   * @param {SubmitEvent} [event] - Optional submit event
   * @returns {Object} Validation result
   */
  validateForm: function(form, event) {
    if (!(form instanceof HTMLFormElement)) {
      console.error('ValidateForm: Argument must be an HTMLFormElement');
      return {
        success: false,
        errors: { general: ['Invalid form element'] },
        validated_data: {},
        metadata: { validated_fields: [], failed_fields: [] }
      };
    }

    // Clear previous validation states
    this.clearValidationStates(form);

    const errors = {};
    const validatedData = {};
    const validatedFields = [];
    const failedFields = [];
    const elements = form.querySelectorAll('input,select,textarea:not([disabled])');

    elements.forEach(element => {
      const fieldName = element.name || element.id || `field_${Math.random().toString(36).substr(2, 9)}`;
      const validationAttr = element.getAttribute('validation');
      
      if (!validationAttr) return;

      const validationMethods = validationAttr.split(',').map(method => method.trim());
      const params = this.parseValidationParams(element);

      const result = this.validateField(element, validationMethods, params);

      if (!result.valid) {
        errors[fieldName] = result.errors;
        failedFields.push(fieldName);
        this.markFieldInvalid(element, result.errors);
      } else {
        validatedData[fieldName] = result.value;
        validatedFields.push(fieldName);
        this.markFieldValid(element);
      }
    });

    const success = Object.keys(errors).length === 0;
    
    if (!success && event) {
      const failMsg = form.getAttribute('validation-fail') || LazyMePHP.Config.validationFailMessage;
      LazyMePHP.Notifications.showError(failMsg);
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
  },

  /**
   * Validate a single form field
   * @param {HTMLElement} field - Form field element
   * @param {string[]} methods - Array of validation method names
   * @param {Object} params - Validation parameters
   * @returns {Object} Validation result
   */
  validateField: function(field, methods, params = {}) {
    if (!(field instanceof HTMLElement)) {
      return { valid: false, errors: ['Invalid field element'], value: null };
    }

    const value = field.value.trim();
    const errors = [];
    let i = 0;

    while (i < methods.length) {
      const method = methods[i];
      
      // Check if method exists in ValidationsMethods object
      const methodExists = Object.values(LazyMePHP.ValidationsMethods).includes(method);
      if (!methodExists) {
        errors.push(`Unknown validation method: ${method}`);
        i++;
        continue;
      }

      const validationResult = this.executeValidation(method, value, methods, params, i);
      
      if (!validationResult.valid) {
        errors.push(...validationResult.errors);
      }
      
      i = validationResult.nextIndex;
    }

    return {
      valid: errors.length === 0,
      errors,
      value: errors.length === 0 ? value : null
    };
  },

  /**
   * Execute a specific validation method
   * @param {string} method - Validation method name
   * @param {string} value - Value to validate
   * @param {string[]} methods - All methods array
   * @param {Object} params - Validation parameters
   * @param {number} currentIndex - Current index in methods array
   * @returns {Object} Validation result with next index
   */
  executeValidation: function(method, value, methods, params, currentIndex) {
    // Check if method exists in ValidationsMethods object
    const methodExists = Object.values(LazyMePHP.ValidationsMethods).includes(method);
    if (!methodExists) {
      return {
        valid: false,
        errors: [`Unknown validation method: ${method}`],
        nextIndex: currentIndex + 1
      };
    }

    const validators = {
      [LazyMePHP.ValidationsMethods.STRING]: () => {
        if (!this.validators.validateString(value, params)) {
          const min = params.min_length || 0;
          const max = params.max_length || 'unlimited';
          return [`Value must be a valid string (min: ${min}, max: ${max} characters).`];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.FLOAT]: () => {
        if (!this.validators.validateFloat(value)) {
          return ['Value must be a valid floating-point number.'];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.INT]: () => {
        if (!this.validators.validateInteger(value)) {
          return ['Value must be a valid integer.'];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.NOTNULL]: () => {
        if (!this.validators.validateNotNull(value)) {
          return ['Value cannot be null or empty.'];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.LENGTH]: () => {
        const length = parseInt(methods[currentIndex + 1], 10);
        if (isNaN(length)) {
          return ['LENGTH validation requires an integer parameter.'];
        }
        if (!this.validators.validateLength(value, length)) {
          return [`Value must be exactly ${length} characters long.`];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.DATE]: () => {
        if (!this.validators.validateDate(value)) {
          return ['Value must be a valid date.'];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.DATETIME]: () => {
        if (!this.validators.validateDateTime(value)) {
          return ['Value must be a valid date/time in YYYY-MM-DD or YYYY/MM/DD, optionally with hours.'];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.EMAIL]: () => {
        if (!this.validators.validateEmail(value)) {
          return ['Value must be a valid email address.'];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.BOOLEAN]: () => {
        if (!this.validators.validateBoolean(value)) {
          return ['Value must be a valid boolean representation (e.g., true, false, 1, 0, on, off).'];
        }
        return [];
      },
      [LazyMePHP.ValidationsMethods.REGEXP]: () => {
        const regexp = methods[currentIndex + 1];
        if (!regexp) {
          return ['REGEXP validation requires a string regex parameter.'];
        }
        if (!this.validators.validateRegExp(value, regexp)) {
          return ['Value does not match the specified regular expression.'];
        }
        return [];
      }
    };

    const errors = validators[method] ? validators[method]() : [`Unknown validation method: ${method}`];
    
    // Calculate next index based on method
    let nextIndex = currentIndex + 1;
    if (method === LazyMePHP.ValidationsMethods.LENGTH || method === LazyMePHP.ValidationsMethods.REGEXP) {
      nextIndex = currentIndex + 2; // Skip parameter
    }

    return {
      valid: errors.length === 0,
      errors,
      nextIndex
    };
  },

  /**
   * Parse validation parameters from element attributes
   * @param {HTMLElement} element - Form element
   * @returns {Object} Parsed parameters
   */
  parseValidationParams: function(element) {
    const paramsAttr = element.getAttribute('data-validation-params');
    if (!paramsAttr) return {};
    
    try {
      return JSON.parse(paramsAttr);
    } catch (e) {
      console.warn(`Invalid data-validation-params for ${element.name || element.id}: ${e.message}`);
      return {};
    }
  },

  /**
   * Clear all validation states from a form
   * @param {HTMLFormElement} form - Form element
   */
  clearValidationStates: function(form) {
    form.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
      el.classList.remove('is-valid', 'is-invalid');
      const feedback = el.nextElementSibling;
      if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.remove();
      }
    });
  },

  /**
   * Mark a field as valid
   * @param {HTMLElement} field - Form field element
   */
  markFieldValid: function(field) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    const feedback = field.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
      feedback.remove();
    }
  },

  /**
   * Mark a field as invalid
   * @param {HTMLElement} field - Form field element
   * @param {string[]} errors - Error messages
   */
  markFieldInvalid: function(field, errors) {
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
  },

  // Individual validators - MUST MATCH PHP behavior exactly
  validators: {
    validateNotNull: function(value) {
      return value !== null && value !== '';
    },

    validateFloat: function(value) {
      return (this.isNumeric(value) && LazyMePHP.ValidationPatterns.FLOAT.test(value)) || this.isEmpty(value);
    },

    validateInteger: function(value) {
      return (this.isNumeric(value) && LazyMePHP.ValidationPatterns.INT.test(value)) || this.isEmpty(value);
    },

    validateString: function(value, params = {}) {
      if (!this.isString(value)) {
        return false;
      }

      const minLength = params.min_length || 0;
      const maxLength = params.max_length || Number.MAX_SAFE_INTEGER;
      const regex = params.regex ? new RegExp(params.regex, 'u') : LazyMePHP.ValidationPatterns.STRING;

      if (value.length < minLength || value.length > maxLength) {
        return false;
      }

      if (params.sanitize) {
        value = this.sanitizeString(value);
      }

      return this.validateRegExp(value, regex) || this.isEmpty(value);
    },

    validateLength: function(value, size) {
      return this.isString(value) && value.length === size;
    },

    validateDate: function(value) {
      if (!this.isString(value) || !this.validateRegExp(value, LazyMePHP.ValidationPatterns.DATE)) {
        return false;
      }
      // Accept both YYYY/MM/DD and YYYY-MM-DD
      const parts = value.split(/[\/\-]/);
      return this.checkDate(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2])) || this.isEmpty(value);
    },

    validateDateTime: function(value) {
      if (!this.isString(value) || !this.validateRegExp(value, LazyMePHP.ValidationPatterns.DATETIME)) {
        return false;
      }
      // Extract date part
      const datePart = value.split(/[ T]/)[0];
      const parts = datePart.split(/[\/\-]/);
      return this.checkDate(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2])) || this.isEmpty(value);
    },

    validateISODate: function(value) {
      if (!this.isString(value) || !this.validateRegExp(value, LazyMePHP.ValidationPatterns.ISO_DATE)) {
        return false;
      }
      try {
        const date = new Date(value);
        return !isNaN(date.getTime());
      } catch (e) {
        return false;
      }
    },

    validateEmail: function(value) {
      return (this.isString(value) && this.isValidEmail(value)) || this.isEmpty(value);
    },

    validateBoolean: function(value) {
      if (typeof value === 'boolean') {
        return true;
      }

      const normalizedValue = this.isString(value) ? value.toLowerCase().trim() : value;

      if (['true', '1', 'on', 1].includes(normalizedValue)) {
        return true;
      }
      
      if (value === null || value === '' || ['false', '0', 'off', 0].includes(normalizedValue)) {
        return true;
      }

      return false;
    },

    validateRegExp: function(value, regexp) {
      if (value === null || value === '') {
        return true; // Empty values are allowed
      }
      return this.isString(value) && regexp.test(value);
    },

    // Helper methods to match PHP behavior
    isString: function(value) {
      return typeof value === 'string';
    },

    isNumeric: function(value) {
      return !isNaN(value) && !isNaN(parseFloat(value));
    },

    isEmpty: function(value) {
      return value === null || value === '';
    },

    isValidEmail: function(value) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(value);
    },

    sanitizeString: function(value) {
      // Simple HTML tag removal - matches PHP strip_tags behavior
      return value.replace(/<[^>]*>/g, '');
    },

    checkDate: function(year, month, day) {
      const date = new Date(year, month - 1, day);
      return date.getFullYear() === year && 
             date.getMonth() === month - 1 && 
             date.getDate() === day;
    }
  }
};

/**
 * Notification System Module
 */
LazyMePHP.Notifications = {
  /**
   * Show a notification
   * @param {string} type - Notification type (success, error, warning, info, debug, critical)
   * @param {string} message - Notification message
   * @param {Object} options - Notification options
   * @returns {HTMLElement} Notification element
   */
  show: function(type, message, options = {}) {
    const {
      title = '',
      duration = this.getDefaultDuration(type),
      dismissible = this.getDefaultDismissible(type),
      position = options.position || LazyMePHP.Config.defaultNotificationPosition,
      animation = options.animation || LazyMePHP.Config.defaultNotificationAnimation,
      category = options.category || 'user',
      priority = options.priority || 2,
      id = options.id || this.generateId()
    } = options;
    
    const container = LazyMePHP.Notifications.getOrCreateContainer();
    const notification = LazyMePHP.Notifications.createNotification(type, message, title, dismissible, animation, category, priority, id);
    
    container.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
      notification.classList.add('show');
    }, 10);
    
    // Auto-dismiss
    if (duration > 0) {
      LazyMePHP.Notifications.setupAutoDismiss(notification, duration);
    }
    
    // Store notification reference for management
    this.storeNotification(notification, { type, message, options });
    
    return notification;
  },

  /**
   * Get default duration for notification type
   * @param {string} type - Notification type
   * @returns {number} Duration in milliseconds
   */
  getDefaultDuration: function(type) {
    const durations = {
      'success': 5000,
      'error': 8000,
      'warning': 6000,
      'info': 4000,
      'debug': 3000,
      'critical': 0 // Never auto-dismiss
    };
    return durations[type] || LazyMePHP.Config.defaultNotificationDuration;
  },

  /**
   * Get default dismissible setting for notification type
   * @param {string} type - Notification type
   * @returns {boolean} Whether notification is dismissible
   */
  getDefaultDismissible: function(type) {
    const dismissible = {
      'success': true,
      'error': false, // Errors should be manually dismissed
      'warning': true,
      'info': true,
      'debug': true,
      'critical': false // Critical notifications should not be dismissible
    };
    return dismissible[type] !== undefined ? dismissible[type] : true;
  },

  /**
   * Generate unique notification ID
   * @returns {string} Unique ID
   */
  generateId: function() {
    return 'notif_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  },

  /**
   * Store notification reference for management
   * @param {HTMLElement} notification - Notification element
   * @param {Object} data - Notification data
   */
  storeNotification: function(notification, data) {
    if (!this.activeNotifications) {
      this.activeNotifications = new Map();
    }
    this.activeNotifications.set(notification, data);
  },

  /**
   * Get or create notification container
   * @returns {HTMLElement} Container element
   */
  getOrCreateContainer: function() {
    let container = document.querySelector('.notification-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'notification-container';
      document.body.appendChild(container);
    }
    return container;
  },

  /**
   * Create notification element
   * @param {string} type - Notification type
   * @param {string} message - Message content
   * @param {string} title - Optional title
   * @param {boolean} dismissible - Whether notification can be dismissed
   * @param {string} animation - Animation type
   * @param {string} category - Notification category
   * @param {number} priority - Priority level
   * @param {string} id - Unique ID
   * @returns {HTMLElement} Notification element
   */
  createNotification: function(type, message, title, dismissible, animation, category, priority, id) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type} notification-${animation} notification-priority-${priority}`;
    notification.setAttribute('data-id', id);
    notification.setAttribute('data-category', category);
    notification.setAttribute('data-priority', priority);
    
    let content = '';
    if (title) {
      content += `<div class="notification-title">${title}</div>`;
    }
    content += `<div class="notification-message">${message}</div>`;
    
    // Add category badge for non-user categories
    if (category !== 'user') {
      content += `<div class="notification-category">${category}</div>`;
    }
    
    if (dismissible) {
      content += `<button class="notification-close" onclick="LazyMePHP.Notifications.dismiss(this.parentElement)"><span>&times;</span></button>`;
    }
    
    content += '<div class="notification-progress"></div>';
    notification.innerHTML = content;
    
    return notification;
  },

  /**
   * Dismiss a specific notification
   * @param {HTMLElement} notification - Notification element
   */
  dismiss: function(notification) {
    if (notification && notification.parentNode) {
      notification.classList.remove('show');
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
          this.removeNotificationReference(notification);
        }
      }, 300);
    }
  },

  /**
   * Remove notification reference from storage
   * @param {HTMLElement} notification - Notification element
   */
  removeNotificationReference: function(notification) {
    if (this.activeNotifications && this.activeNotifications.has(notification)) {
      this.activeNotifications.delete(notification);
    }
  },

  /**
   * Setup auto-dismiss functionality
   * @param {HTMLElement} notification - Notification element
   * @param {number} duration - Duration in milliseconds
   */
  setupAutoDismiss: function(notification, duration) {
    const progress = notification.querySelector('.notification-progress');
    if (progress) {
      progress.style.transition = `transform ${duration}ms linear`;
      setTimeout(() => {
        progress.style.transform = 'scaleX(0)';
      }, 10);
    }
    
    setTimeout(() => {
      if (notification.parentNode) {
        this.dismiss(notification);
      }
    }, duration);
  },

  /**
   * Show success notification
   * @param {string} message - Message content
   * @param {Object} options - Notification options
   * @returns {HTMLElement} Notification element
   */
  showSuccess: function(message, options = {}) {
    return this.show('success', message, options);
  },

  /**
   * Show error notification
   * @param {string} message - Message content
   * @param {Object} options - Notification options
   * @returns {HTMLElement} Notification element
   */
  showError: function(message, options = {}) {
    return this.show('error', message, options);
  },

  /**
   * Show warning notification
   * @param {string} message - Message content
   * @param {Object} options - Notification options
   * @returns {HTMLElement} Notification element
   */
  showWarning: function(message, options = {}) {
    return this.show('warning', message, options);
  },

  /**
   * Show info notification
   * @param {string} message - Message content
   * @param {Object} options - Notification options
   * @returns {HTMLElement} Notification element
   */
  showInfo: function(message, options = {}) {
    return this.show('info', message, options);
  },

  /**
   * Show debug notification
   * @param {string} message - Message content
   * @param {Object} options - Notification options
   * @returns {HTMLElement} Notification element
   */
  showDebug: function(message, options = {}) {
    return this.show('debug', message, options);
  },

  /**
   * Show critical notification
   * @param {string} message - Message content
   * @param {Object} options - Notification options
   * @returns {HTMLElement} Notification element
   */
  showCritical: function(message, options = {}) {
    return this.show('critical', message, options);
  },

  /**
   * Clear all notifications
   */
  clearAll: function() {
    document.querySelectorAll('.notification').forEach(notification => {
      this.dismiss(notification);
    });
  },

  /**
   * Clear notifications by type
   * @param {string} type - Notification type
   */
  clearByType: function(type) {
    document.querySelectorAll(`.notification-${type}`).forEach(notification => {
      this.dismiss(notification);
    });
  },

  /**
   * Clear notifications by category
   * @param {string} category - Notification category
   */
  clearByCategory: function(category) {
    document.querySelectorAll(`[data-category="${category}"]`).forEach(notification => {
      this.dismiss(notification);
    });
  },

  /**
   * Get notification statistics
   * @returns {Object} Statistics
   */
  getStats: function() {
    const notifications = document.querySelectorAll('.notification');
    const stats = {
      total: notifications.length,
      byType: {},
      byCategory: {},
      byPriority: {}
    };

    notifications.forEach(notification => {
      const type = notification.className.match(/notification-(\w+)/)?.[1] || 'unknown';
      const category = notification.getAttribute('data-category') || 'unknown';
      const priority = notification.getAttribute('data-priority') || 'unknown';

      stats.byType[type] = (stats.byType[type] || 0) + 1;
      stats.byCategory[category] = (stats.byCategory[category] || 0) + 1;
      stats.byPriority[priority] = (stats.byPriority[priority] || 0) + 1;
    });

    return stats;
  },

  /**
   * Get active notifications
   * @returns {Map} Active notifications map
   */
  getActiveNotifications: function() {
    return this.activeNotifications || new Map();
  }
};

// Backward compatibility aliases with proper binding
LazyMePHP.InitFormValidation = LazyMePHP.Validation.initForm;
LazyMePHP.ValidateForm = LazyMePHP.Validation.validateForm;
LazyMePHP.ValidateField = LazyMePHP.Validation.validateField;
LazyMePHP.MarkFieldValid = LazyMePHP.Validation.markFieldValid;
LazyMePHP.MarkFieldInvalid = LazyMePHP.Validation.markFieldInvalid;
LazyMePHP.ShowNotification = function(type, message, options) {
  return LazyMePHP.Notifications.show(type, message, options);
};
LazyMePHP.ShowSuccess = function(message, options) {
  return LazyMePHP.Notifications.showSuccess(message, options);
};
LazyMePHP.ShowWarning = function(message, options) {
  return LazyMePHP.Notifications.showWarning(message, options);
};
LazyMePHP.ShowInfo = function(message, options) {
  return LazyMePHP.Notifications.showInfo(message, options);
};
LazyMePHP.ShowError = function(message, options) {
  return LazyMePHP.Notifications.showError(message, options);
};
LazyMePHP.ShowDebug = function(message, options) {
  return LazyMePHP.Notifications.showDebug(message, options);
};
LazyMePHP.ShowCritical = function(message, options) {
  return LazyMePHP.Notifications.showCritical(message, options);
};
LazyMePHP.ClearNotifications = function() {
  return LazyMePHP.Notifications.clearAll();
};
LazyMePHP.ClearNotificationsByType = function(type) {
  return LazyMePHP.Notifications.clearByType(type);
};
LazyMePHP.ClearNotificationsByCategory = function(category) {
  return LazyMePHP.Notifications.clearByCategory(category);
};
LazyMePHP.GetNotificationStats = function() {
  return LazyMePHP.Notifications.getStats();
};

// Polyfill for Date.now if not available
if (!Date.now) {
  Date.now = function() {
    return +new Date();
  };
}
