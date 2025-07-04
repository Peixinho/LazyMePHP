<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Core;
use Core\Helpers\ErrorUtil;
use Core\DB\MSSQL as DBMSSQL;
use Core\DB\SQLite as DBSQLite;
use Core\DB\MySQL as DBMYSQL;

/**
 * LazyMePHP Class
 *
 * The LazyMePHP class is the core of the LazyMePHP configuration and utility hub.
 * It initializes database connections, application settings from environment variables,
 * and provides static methods to access these configurations throughout the application.
 * It also handles activity logging if enabled.
 *
 * @internal This class is intended for internal use by the LazyMePHP framework.
 */
class LazyMePHP
{
  //region Database Configuration Properties
  /** 
   * @var ?string Database name.
   * @internal
   */
  private static ?string $_db_name = null;

  /** 
   * @var ?object Database connection instance (MYSQL, MSSQL, or SQLITE object).
   * @internal
   */
  private static ?object $_db_connection = null;

  /** 
   * @var ?string Database username.
   * @internal
   */
  private static ?string $_db_user = null;

  /** 
   * @var ?string Database password.
   * @internal
   */
  private static ?string $_db_password = null;

  /** 
   * @var ?string Database type ('mysql', 'mssql', 'sqlite').
   * @internal
   */
  private static ?string $_db_type = null;

  /** 
   * @var ?string Database host.
   * @internal
   */
  private static ?string $_db_host = null;

  /** 
   * @var ?string Database driver file name (e.g., "MYSQL.php").
   * @internal
   */
  private static ?string $_db_file = null;

  /** 
   * @var ?string Filesystem path to the SQLite database file, if using SQLite.
   * @internal
   */
  private static ?string $_db_file_path = null;
  //endregion

  //region Application Configuration Properties
  /** 
   * @var ?string Application name.
   * @internal
   */
  private static ?string $_app_name = null;

  /** 
   * @var ?string Application title (used in HTML titles, etc.).
   * @internal
   */
  private static ?string $_app_title = null;

  /** 
   * @var ?string Application version.
   * @internal
   */
  private static ?string $_app_version = null;

  /** 
   * @var ?string Application description.
   * @internal
   */
  private static ?string $_app_description = null;

  /** 
   * @var ?string Application timezone.
   * @internal
   */
  private static ?string $_app_timezone = null;

  /** 
   * @var ?string Email address for application support.
   * @internal
   */
  private static ?string $_support_email = null;

  /** 
   * @var ?int Default number of results to show in paginated lists.
   * @internal
   */
  private static ?int $_app_nresults = null;

  /** 
   * @var ?string Encryption key used for data encryption/decryption.
   * @internal
   */
  private static ?string $_app_encryption = null;

  /** 
   * @var ?bool Flag indicating whether activity logging is enabled.
   * @internal
   */
  private static ?bool $_app_activity_log = null;

  /** 
   * @var ?string Identifier for the user/process performing actions when activity logging is enabled.
   * @internal
   */
  private static ?string $_app_activity_auth = null;

  /** 
   * @var ?bool Flag indicating whether debug mode is enabled.
   * @internal
   */
  private static ?bool $_app_debug_mode = null;

  //region Database Accessor Methods
  /**
   * Returns the configured database name.
   *
   * @return ?string The database name, or null if not set.
   */
  static function DB_NAME(): ?string
  {
    return self::$_db_name;
  }

  /**
   * Establishes and/or returns the active database connection instance.
   *
   * This method determines the database type from the configuration and instantiates
   * the appropriate database driver class (MYSQL, MSSQL, or SQLITE).
   * It uses a singleton pattern for the connection instance.
   *
   * @return ?object The database connection object (MYSQL, MSSQL, or SQLITE instance), or null on failure.
   */
  static function DB_CONNECTION(): ?\Core\DB\ISQL
  {
    if (!self::$_db_connection) {
      // Ensure $_db_type is a string before strtolower, defaulting to 'mysql' if not set.
      $db_type_check = strtolower((string)(self::$_db_type ?? 'mysql')); 

      if ($db_type_check == 'mssql') {
        self::$_db_connection = DBMSSQL::getInstance(self::$_db_name, self::$_db_user, self::$_db_password, self::$_db_host);
      } elseif ($db_type_check == 'mysql') {
        self::$_db_connection = DBMYSQL::getInstance(self::$_db_name, self::$_db_user, self::$_db_password, self::$_db_host);
      } elseif ($db_type_check == 'sqlite') {
        self::$_db_connection = DBSQLite::getInstance(self::$_db_file_path);
      } else {
        // If the DB type is unsupported, trigger an error and return null.
        // ErrorUtil::trigger_error is used if available, otherwise fallback to PHP's trigger_error.
        if (class_exists(ErrorUtil::class)) {
            ErrorUtil::trigger_error("Unsupported DB_TYPE configured: " . self::$_db_type, E_USER_ERROR);
        } else {
            trigger_error("Unsupported DB_TYPE configured: " . self::$_db_type, E_USER_ERROR);
        }
        return null;
      }
    }
    return self::$_db_connection;
  }

  /**
   * Returns the configured database username.
   *
   * @return ?string The database username, or null if not set.
   */
  static function DB_USER(): ?string
  {
    return self::$_db_user;
  }

  /**
   * Returns the configured database password.
   *
   * @return ?string The database password, or null if not set.
   */
  static function DB_PASSWORD(): ?string
  {
    return self::$_db_password;
  }

  /**
   * Returns the configured database type.
   *
   * @return ?string The database type (e.g., 'mysql', 'mssql', 'sqlite'), or null if not set.
   */
  static function DB_TYPE(): ?string
  {
    return self::$_db_type;
  }

  /**
   * Returns the configured database host.
   *
   * @return ?string The database host, or null if not set.
   */
  static function DB_HOST(): ?string
  {
    return self::$_db_host;
  }

  /**
   * Returns the database driver file name.
   *
   * @return ?string The database driver file name (e.g., "MYSQL.php"), or null if not set.
   */
  static function DB_FILE(): ?string
  {
    return self::$_db_file;
  }

  /**
   * Returns the filesystem path to the SQLite database file.
   * Relevant only if `DB_TYPE` is 'sqlite'.
   *
   * @return ?string The path to the SQLite database file, or null if not applicable/set.
   */
  static function DB_FILE_PATH(): ?string
  {
    return self::$_db_file_path;
  }
  //endregion

  //region Application Settings Accessor Methods
  /**
   * Returns the configured application name.
   *
   * @return ?string The application name, or null if not set.
   */
  static function NAME(): ?string
  {
    return self::$_app_name;
  }

  /**
   * Returns the configured application title.
   *
   * @return ?string The application title, or null if not set.
   */
  static function TITLE(): ?string
  {
    return self::$_app_title;
  }

  /**
   * Returns the configured application version.
   *
   * @return ?string The application version, or null if not set.
   */
  static function VERSION(): ?string
  {
    return self::$_app_version;
  }

  /**
   * Returns the configured application description.
   *
   * @return ?string The application description, or null if not set.
   */
  static function DESCRIPTION(): ?string
  {
    return self::$_app_description;
  }

  /**
   * Returns the configured application timezone.
   *
   * @return ?string The application timezone, or null if not set.
   */
  static function TIMEZONE(): ?string
  {
    return self::$_app_timezone;
  }

  /**
   * Returns the configured support email address.
   *
   * @return ?string The support email address, or null if not set.
   */
  static function SUPPORT_EMAIL(): ?string
  {
    return self::$_support_email;
  }

  /**
   * Returns the default number of results for paginated lists.
   *
   * @return ?int The number of results, or null if not set.
   */
  static function NRESULTS(): ?int
  {
    return self::$_app_nresults;
  }

  /**
   * Returns the configured encryption key.
   *
   * @return ?string The encryption key, or null if not set.
   */
  static function ENCRYPTION(): ?string
  {
    return self::$_app_encryption;
  }

  /**
   * Checks if activity logging is enabled.
   *
   * @return ?bool True if activity logging is enabled, false otherwise, or null if not set.
   */
  static function ACTIVITY_LOG(): ?bool
  {
    return self::$_app_activity_log;
  }

  /**
   * Gets the authentication identifier for activity logging.
   *
   * @return ?string The authentication identifier, or null if not set.
   */
  static function ACTIVITY_AUTH(): ?string
  {
    return self::$_app_activity_auth;
  }

  /**
   * Checks if debug mode is enabled.
   *
   * @return ?bool True if debug mode is enabled, false otherwise, or null if not set.
   */
  static function DEBUG_MODE(): ?bool
  {
    return self::$_app_debug_mode;
  }
  





  /**
   * Initializes the application configuration by loading settings from environment variables,
   * setting up database parameters, and registering error handlers.
   * This constructor is typically called once during the application bootstrap process.
   */
  public function __construct()
  {
    // --- Database Configuration ---
    // Determine DB_TYPE from environment, default to 'mysql'.
    self::$_db_type = strtolower($_ENV['DB_TYPE'] ?? 'mysql');

    // Configure database parameters based on DB_TYPE.
    if (self::$_db_type === 'sqlite') {
        self::$_db_file = "SQLITE.php"; // DB driver file name for SQLite.
        // Default path for SQLite database file if not specified in .env.
        self::$_db_file_path = $_ENV['DB_FILE_PATH'] ?? __DIR__.'/../../database/database.sqlite'; 
    } elseif (self::$_db_type === 'mssql') {
        self::$_db_file = "MSSQL.php"; // DB driver file name for MSSQL.
        // Load MSSQL specific connection details from .env, with defaults.
        self::$_db_user = $_ENV['DB_USER'] ?? '';
        self::$_db_password = $_ENV['DB_PASSWORD'] ?? '';
        self::$_db_host = $_ENV['DB_HOST'] ?? 'localhost';
        self::$_db_name = $_ENV['DB_NAME'] ?? '';
    } else { // Default to MySQL for any other DB_TYPE or if unspecified.
        self::$_db_file = "MYSQL.php"; // DB driver file name for MySQL.
        // Load MySQL specific connection details from .env, with defaults.
        self::$_db_user = $_ENV['DB_USER'] ?? '';
        self::$_db_password = $_ENV['DB_PASSWORD'] ?? '';
        self::$_db_host = $_ENV['DB_HOST'] ?? 'localhost';
        self::$_db_name = $_ENV['DB_NAME'] ?? '';
        if (self::$_db_type !== 'mysql') { // Log if defaulting due to unrecognized type
            if (class_exists(ErrorUtil::class)) {
                ErrorUtil::trigger_error("Unrecognized DB_TYPE '" . ($_ENV['DB_TYPE'] ?? 'null') . "', defaulting to mysql.", E_USER_NOTICE);
            } else {
                trigger_error("Unrecognized DB_TYPE '" . ($_ENV['DB_TYPE'] ?? 'null') . "', defaulting to mysql.", E_USER_NOTICE);
            }
        }
    }

    // Construct the full path to the database driver file and require it.
    $db_file_full_path = __DIR__."/../DB/".self::$_db_file;
    if (file_exists($db_file_full_path)) {
        require_once $db_file_full_path;
        // Optionally, store the full path if needed later, though typically just the name is fine for identification.
        // self::$_db_file = $db_file_full_path; 
    } else {
        // Trigger a fatal error if the database driver file is not found.
         if (class_exists(ErrorUtil::class)) {
            ErrorUtil::trigger_error("Database driver file not found: " . $db_file_full_path, E_USER_ERROR);
        } else {
            trigger_error("Database driver file not found: " . $db_file_full_path, E_USER_ERROR);
        }
    }

    // --- Application Settings ---
    // Load various application settings from .env, providing defaults.
    self::$_app_name = $_ENV['APP_NAME'] ?? 'LazyMePHP';
    self::$_app_title = $_ENV['APP_TITLE'] ?? 'LazyMePHP Application';
    self::$_app_version = $_ENV['APP_VERSION'] ?? '1.0';
    self::$_app_description = $_ENV['APP_DESCRIPTION'] ?? 'A LazyMePHP application.';
    self::$_app_timezone = trim($_ENV['APP_TIMEZONE'] ?? 'UTC', '"\'');
    self::$_support_email = $_ENV['APP_EMAIL_SUPPORT'] ?? 'noreply@example.com';
    self::$_app_activity_log = ($_ENV['APP_ACTIVITY_LOG'] ?? 'false') === 'true';
    self::$_app_activity_auth = $_ENV['APP_ACTIVITY_AUTH'] ?? ''; // E.g., default system user for logs
    self::$_app_nresults = (int)($_ENV['APP_NRESULTS'] ?? 100);
    self::$_app_encryption = $_ENV['APP_ENCRYPTION'] ?? 'your-default-strong-encryption-key'; // IMPORTANT: Change this default!
    self::$_app_debug_mode = ($_ENV['APP_DEBUG_MODE'] ?? 'false') === 'true';

    // --- System Setup ---
    // Set the default timezone for all date/time functions.
    date_default_timezone_set(self::$_app_timezone);
    
    // Initialize PerformanceUtil with environment settings
    if (class_exists('\\Core\\Helpers\\PerformanceUtil')) {
        \Core\Helpers\PerformanceUtil::initialize();
    }
  }



  /**
   * Reset all static properties for testing purposes
   */
  public static function reset(): void
  {
    self::$_db_name = null;
    self::$_db_connection = null;
    self::$_db_user = null;
    self::$_db_password = null;
    self::$_db_type = null;
    self::$_db_host = null;
    self::$_db_file = null;
    self::$_db_file_path = null;
    self::$_app_name = null;
    self::$_app_title = null;
    self::$_app_version = null;
    self::$_app_description = null;
    self::$_app_timezone = null;
    self::$_support_email = null;
    self::$_app_nresults = null;
    self::$_app_encryption = null;
    self::$_app_activity_log = null;
    self::$_app_activity_auth = null;
    self::$_app_debug_mode = null;
    // Activity logging data reset moved to ActivityLogger
    
    // Reset database instances to prevent singleton conflicts during testing
    if (class_exists('Core\DB\MySQL')) {
      \Core\DB\MySQL::resetInstance();
    }
    if (class_exists('Core\DB\MSSQL')) {
      \Core\DB\MSSQL::resetInstance();
    }
    if (class_exists('Core\DB\SQLite')) {
      \Core\DB\SQLite::resetInstance();
    }
  }
}
?>
