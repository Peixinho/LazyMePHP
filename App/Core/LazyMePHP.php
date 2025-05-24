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
use Core\DB\MYSQL as DBMYSQL;

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
   * @var array Holds data to be logged for the current request when activity logging is enabled.
   * Structure: [$table_name => [['log' => $data, 'pk' => $pk, 'method' => $method], ...]]
   * @internal
   */
  private static array $_app_logdata = [];

  /** 
   * @var ?bool Flag indicating whether URL rewriting (e.g., mod_rewrite) is enabled.
   * @internal
   */
  private static ?bool $_app_modrewrite = null;
  //endregion

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
  static function DB_CONNECTION(): ?object
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
   * Returns the identifier for the user/process for activity logging.
   *
   * @return ?string The activity log auth identifier, or null if not set.
   */
  static function ACTIVITY_AUTH(): ?string
  {
    return self::$_app_activity_auth;
  }
  
  /**
   * Stores data to be logged for the current request if activity logging is enabled.
   * This data is processed by the `LOG_ACTIVITY` method at the end of the request.
   *
   * @param string $table The name of the database table related to the log entry.
   * @param array $log An array containing the data changes. Typically `['field_name' => ['before_value', 'after_value']]`.
   * @param ?string $pk The primary key value of the record being logged.
   * @param ?string $method The method that initiated the change (e.g., 'INSERT', 'UPDATE', 'DELETE').
   * @return void
   */
  static function LOGDATA(string $table, array $log, ?string $pk = null, ?string $method = null): void
  {
    // Ensure the entry for the table exists.
    if (!array_key_exists($table, self::$_app_logdata)) {
        self::$_app_logdata[$table] = [];
    }
    // Add the log data to the array for this table.
    self::$_app_logdata[$table][] = ["log" => $log, "pk" => $pk, "method" => $method];
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
    self::$_app_timezone = $_ENV['APP_TIMEZONE'] ?? 'UTC';
    self::$_support_email = $_ENV['APP_EMAIL_SUPPORT'] ?? 'noreply@example.com';
    self::$_app_activity_log = ($_ENV['APP_ACTIVITY_LOG'] ?? 'false') === 'true';
    self::$_app_activity_auth = $_ENV['APP_ACTIVITY_AUTH'] ?? ''; // E.g., default system user for logs
    self::$_app_nresults = (int)($_ENV['APP_NRESULTS'] ?? 100);
    self::$_app_encryption = $_ENV['APP_ENCRYPTION'] ?? 'your-default-strong-encryption-key'; // IMPORTANT: Change this default!

    // --- System Setup ---
    // Set the default timezone for all date/time functions.
    date_default_timezone_set(self::$_app_timezone);
    
    // Register custom error and shutdown handlers using ErrorUtil.
    // The @ suppresses errors from these functions themselves, which ErrorUtil should handle.

    //@set_error_handler([\Helpers\ErrorUtil::class, 'ErrorHandler']);
    //@register_shutdown_function([\Helpers\ErrorUtil::class, 'FatalErrorShutdownHandler']);
  }

  /**
   * Logs activities performed during the request if activity logging is enabled.
   * This method inserts records into `__LOG_ACTIVITY`, `__LOG_ACTIVITY_OPTIONS`,
   * and `__LOG_DATA` tables based on data collected via `APP_LOGDATA`.
   *
   * @return void
   */
  static function LOG_ACTIVITY(): void 
  {
    // Proceed only if activity logging is enabled and a database connection exists.
    if (self::ACTIVITY_LOG() && self::DB_CONNECTION()) {
      // --- Log main activity ---
      $logActivityQuery = "INSERT INTO __LOG_ACTIVITY (`date`, `user`, `method`) VALUES (?, ?, ?)";
      $currentDateTime = date("Y-m-d H:i:s"); // Use standard SQL datetime format
      $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'CLI'; // Default to CLI if not an HTTP request
      
      self::DB_CONNECTION()->Query($logActivityQuery, [$currentDateTime, self::$_app_activity_auth, $requestMethod]);
      $logActivityId = self::DB_CONNECTION()->GetLastInsertedID('__LOG_ACTIVITY');

      // If $logActivityId is not valid, we cannot proceed with logging details.
      if (!$logActivityId) {
          if (class_exists(ErrorUtil::class)) {
            ErrorUtil::trigger_error("Failed to retrieve last insert ID for __LOG_ACTIVITY.", E_USER_WARNING);
          } else {
            trigger_error("Failed to retrieve last insert ID for __LOG_ACTIVITY.", E_USER_WARNING);
          }
          return;
      }

      // --- Log URL/Route parameters for the activity ---
      $urlParts = [];
      if (function_exists('\LazyMePHP\Helper\url')) { // Check if helper function exists
          $urlParts = explode('/', (string)\LazyMePHP\Helper\url());
      }
      
      if (!empty($urlParts)) {
          $logOptionsQueryParts = [];
          $logOptionsQueryData = [];
          foreach ($urlParts as $key => $part) {
              if (!empty($part)) { // Log only non-empty parts
                  $logOptionsQueryParts[] = "(?, ?, ?)";
                  array_push($logOptionsQueryData, $logActivityId, $key, $part);
              }
          }

          if (!empty($logOptionsQueryParts)) {
              $logOptionsQuery = sprintf(
                  "INSERT INTO __LOG_ACTIVITY_OPTIONS (`id_log_activity`, `subOption`, `value`) VALUES %s",
                  implode(", ", $logOptionsQueryParts)
              );
              self::DB_CONNECTION()->Query($logOptionsQuery, $logOptionsQueryData);
          }
      }

      // --- Log detailed data changes ---
      if (!empty(self::$_app_logdata)) {
          $logDataQueryParts = [];
          $logDataQueryData = [];
          foreach (self::$_app_logdata as $tableName => $entries) {
              foreach ($entries as $entry) {
                  if (is_array($entry['log'])) {
                      foreach ($entry['log'] as $fieldName => $values) {
                          // Ensure values has at least two elements (before and after)
                          $dataBefore = $values[0] ?? null;
                          $dataAfter = $values[1] ?? null;
                          
                          $logDataQueryParts[] = "(?, ?, ?, ?, ?, ?, ?)";
                          array_push(
                              $logDataQueryData,
                              $logActivityId,
                              $tableName,
                              (string)($entry['pk'] ?? ''), // Ensure PK is a string
                              (string)($entry['method'] ?? ''), // Ensure method is a string
                              (string)$fieldName, // Ensure field name is a string
                              $dataBefore,
                              $dataAfter
                          );
                      }
                  }
              }
          }

          if (!empty($logDataQueryParts)) {
              $logDataQuery = sprintf(
                  "INSERT INTO __LOG_DATA (`id_log_activity`, `table`, `pk`, `method`, `field`, `dataBefore`, `dataAfter`) VALUES %s",
                  implode(", ", $logDataQueryParts)
              );
              self::DB_CONNECTION()->Query($logDataQuery, $logDataQueryData);
          }
      }
      // Clear log data for the current request after processing.
      self::$_app_logdata = [];
    }
  }
}
?>
