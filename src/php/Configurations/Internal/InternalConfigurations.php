<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\Config\Internal;
use \LazyMePHP\DB\MYSQL;
use \LazyMePHP\DB\MSSQL;

function str_rot47($str)
{
  return strtr($str,
    '!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~',
    'PQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNO'
  );
}

/**
 * ErrorHandler
 *
 * Replaces User Error
 *
 * @param (string) (errno)
 * @param (string) (errstr)
 * @param (string) (errfile)
 * @param (string) (errline)
 * @return (NULL)
 */
function ErrorHandler($errno, $errstr, $errfile, $errline)
{
    if ($errno!=8) // Avoid Undefined Errors
    {
        $errorMsg =
                    "<div style=\"margin:5px;z-index:10000;position:absolute;background-color:#A31919;padding:10px;color:#FFFF66;font-family:sans-serif;font-size:8pt;\">
                        <b><u>ERROR:</u></b>
                        <ul type=\"none\">
                            <li><b>ERROR NR:</b> $errno</li>
                            <li><b>DESCRIPTION:</b> $errstr</li>
                            <li><b>FILE:</b> $errfile</li>
                            <li><b>LINE:</b> $errline<br/></li>
                            <li><b>PHP VERSION:</b> ".phpversion()."
                        </ul>
                        An email with this message was sent to the developer.
                    </div>";

        $to_mail=APP::APP_SUPPORT_EMAIL();
        $from_mail=APP::APP_SUPPORT_EMAIL();
        $subject="Application ".APP::APP_NAME()." thrown an error.";
        $message=$errorMsg;
        echo $message;
        @Sendmail($from_mail, $to_mail, $subject, $message);
        die();
    }
}

/**
 * fatalErrorShutdownHandler
 *
 * Replaces Fatal Error
 *
 * @return (NULL)
 */
function fatalErrorShutdownHandler()
{
  $last_error = error_get_last();
  if (is_array($last_error) && array_key_exists('type', $last_error) && $last_error['type'] === E_ERROR) {
    // fatal error
    ErrorHandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
  }
}

/**
 * sendMail
 *
 * Send Email obviously
 *
 * @param (string) (from_mail)
 * @param (string) (to_mail)
 * @param (string) (subject)
 * @param (string) (message)
 * @return (bool) (send)
 */
function SendMail($from_mail,$to_mail,$subject,$message) {
    $headers = "Content-Type: text/html; charset=iso-8859-1\n";
    $headers.="From: $from_mail\n";
    $send=mail("$to_mail", "$subject", "$message", "$headers");
    return $send;
}

class APP
{
    // DATABASE

    /** @var _db_name */
    private static $_db_name;
    /**
     * DB_NAME
     *
     * Returns Database Name
     *
     * @param (NULL)
     * @return (string) (db_name)
     */
    static function DB_NAME()
    {
        return APP::$_db_name;
    }

    /** @var _db_connection */
    private static $_db_connection;
    /**
     * DB_CONNECTION
     *
     * Returns Database Instance
     *
     * @param (NULL)
     * @return (object) (db_instance)
     */
    static function DB_CONNECTION()
    {
        if (!APP::$_db_connection && APP::$_db_user && APP::$_db_password && APP::$_db_host && APP::$_db_name)
        {
            if (APP::$_db_type == 1) // MSSQL
			{
                APP::$_db_connection = new MSSQL(APP::$_db_name, APP::$_db_user, APP::$_db_password, APP::$_db_host);
			}

            else if (APP::$_db_type == 2) // MYSQL
			{
                APP::$_db_connection = new MYSQL(APP::$_db_name, APP::$_db_user, APP::$_db_password, APP::$_db_host);
			}
        }

        return APP::$_db_connection;
    }

    /** @var _db_user */
    private static $_db_user;
    /**
     * DB_USER
     *
     * Returns Database User Credentials
     *
     * @param (NULL)
     * @return (object) (db_user)
     */
    static function DB_USER()
    {
        return APP::$_db_user;
    }

    /** @var _db_password */
    private static $_db_password;
    /**
     * DB_PASSWORD
     *
     * Returns Database Password Credentials
     *
     * @param (NULL)
     * @return (object) (db_password)
     */
    static function DB_PASSWORD()
    {
        return APP::$_db_password;
    }

    /** @var _db_type */
    private static $_db_type;
    /**
     * DB_TYPE
     *
     * Returns Database Type
     *
     * @param (NULL)
     * @return (object) (db_host)
     */
    static function DB_TYPE()
    {
        return APP::$_db_type;
    }

    /** @var _db_host */
    private static $_db_host;
    /**
     * DB_HOST
     *
     * Returns Database Host
     *
     * @param (NULL)
     * @return (object) (db_host)
     */
    static function DB_HOST()
    {
        return APP::$_db_host;
    }

    /** @var _db_file */
    private static $_db_file;
    /**
     * DB_DRIVER
     *
     * Returns Database File
     *
     * @param (NULL)
     * @return (object) (db_file)
     */
    static function DB_FILE()
    {
        return APP::$_db_file;
    }

    // APPLICATION

    /** @var _app_name */
    private static $_app_name;
    /**
     * APP_NAME
     *
     * Returns Application Name
     *
     * @param (NULL)
     * @return (object) (app_name)
     */
    static function APP_NAME()
    {
        return APP::$_app_name;
    }

    /** @var _app_title */
    private static $_app_title;
    /**
     * APP_TITLE
     *
     * Returns Application Title
     *
     * @param (NULL)
     * @return (object) (app_title)
     */
    static function APP_TITLE()
    {
        return APP::$_app_title;
    }

    /** @var _app_version */
    private static $_app_version;
    /**
     * APP_VERSION
     *
     * Returns Application Version
     *
     * @param (NULL)
     * @return (object) (app_version)
     */
    static function APP_VERSION()
    {
        return APP::$_app_version;
    }

    /** @var _app_description */
    private static $_app_description;
    /**
     * APP_DESCRIPTION
     *
     * Returns Application Description
     *
     * @param (NULL)
     * @return (object) (app_description)
     */
    static function APP_DESCRIPTION()
    {
        return APP::$_app_description;
    }

    /** @var _app_timezone */
    private static $_app_timezone;
    /**
     * APP_TIMEZONE
     *
     * Returns Application Timezone
     *
     * @param (NULL)
     * @return (object) (app_timezone)
     */
    static function APP_TIMEZONE()
    {
        return APP::$_app_timezone;
    }

    // SUPPORT EMAIL

    /** @var _support_email */
    private static $_support_email;
    /**
     * SUPPORT_EMAIL
     *
     * Returns Support Emails
     *
     * @param (NULL)
     * @return (object) (support_email)
     */
    static function APP_SUPPORT_EMAIL()
    {
        return APP::$_support_email;
    }

    // URL ENCRYPTION

    /** @var _app_url_encryption */
    private static $_app_url_encryption;
    /**
     * URL ENCRYPTION
     *
     * Returns if url is encrypted
     *
     * @param (NULL)
     * @return (bool) (url encrypted)
     */
    static function APP_URL_ENCRYPTION()
    {
        return APP::$_app_url_encryption;
    }

    // ROOT PATH

    /** @var _root_path */
    private static $_root_path;
    /**
     * ROOT_PATH
     *
     * Returns Full Path of the Application
     *
     * @param (NULL)
     * @return (object) (root_path)
     */
    static function ROOT_PATH()
    {
        return APP::$_root_path;
    }

    /**
     * URLENCODE
     *
     * Returns URL Encoded
     *
     * @param (string)
     * @return (string) (url)
     */
	static function URLENCODE($url)
	{
		if (APP::APP_URL_ENCRYPTION())
		{
			require_once __DIR__."/../../Ext/jwt_helper.php";

			$_token = array();
			if (count(parse_url($url))>0)
			{
				foreach(parse_url($url) as $key => $arg) {
					$_token[$key] = $arg;
				}
				$token = \JWT::encode($_token,APP::APP_URL_TOKEN());
				return substr($url, 0, strpos($url, '?'))."?".$token;
			}
		}
		return $url;
	}
    /**
     * URLDECODE
     *
     * Returns URL Decoded
     *
     * @param (string)
     * @return (string) (url)
     */
	static function URLDECODE($url)
	{
		if (APP::APP_URL_ENCRYPTION())
		{
			require_once __DIR__."/../../Ext/jwt_helper.php";
			$token = \JWT::decode(parse_url($url)['query'],APP::APP_URL_TOKEN());
			$url="?".($token->query);
			// Set _GET
			parse_str($token->query, $query);
			foreach($query as $key => $arg)
				$_GET[$key] = $arg;
		}
		return $url;
	}

    /** @var _app_url_token */
    private static $_app_url_token;
    /**
     * URL TOKEN SECRET
     *
     * Returns Token Secret
     *
     * @param (null)
     * @return (string) (url)
     */
	static function APP_URL_TOKEN()
	{
		return APP::$_app_url_token;
	}

    /** @var _app_nresults */
    private static $_app_nresults;
    /**
     * NUMBER OF RESULTS
     *
     * Returns Number of Results
     *
     * @param (null)
     * @return (string) (url)
     */
	static function APP_NRESULTS()
	{
		return APP::$_app_nresults;
	}

    /**
     * Constructor
     *
     * Class Constructor
     *
     * @param (array) (CONFIG)
     * @return (NULL)
     */
    public function __construct($CONFIG)
    {

        APP::$_root_path          = $CONFIG['ROOT'];

        // GET DB FILE AND Include IT
        APP::$_db_file            = $CONFIG['DB_FILE'];

        require_once $CONFIG['ROOT']."/".$CONFIG['DB_FILE'];
        // END

        APP::$_db_type            = $CONFIG['DB_TYPE'];
        APP::$_db_user            = $CONFIG['DB_USER'];
        APP::$_db_password        = $CONFIG['DB_PASSWORD'];
        APP::$_db_host            = $CONFIG['DB_HOST'];
        APP::$_db_name            = $CONFIG['DB_NAME'];
        APP::$_app_name           = $CONFIG['APP_NAME'];
        APP::$_app_title          = $CONFIG['APP_TITLE'];
        APP::$_app_version        = $CONFIG['APP_VERSION'];
        APP::$_app_description    = $CONFIG['APP_DESCRIPTION'];
        APP::$_app_timezone       = $CONFIG['APP_TIMEZONE'];
        APP::$_support_email      = $CONFIG['APP_EMAIL_SUPPORT'];
        APP::$_app_url_encryption = $CONFIG['APP_URL_ENCRYPTION'];
		APP::$_app_url_token	  = $CONFIG['APP_URL_TOKEN'];
		APP::$_app_nresults 	  = $CONFIG['APP_NRESULTS'];

        // Set Timezone
        date_default_timezone_set(APP::$_app_timezone);
        // Registers User Error Function Replacement
        @set_error_handler('\LazyMePHP\Config\Internal\ErrorHandler');
        // Registers Fatal Error Function Replacement */
        @register_shutdown_function('\LazyMePHP\Config\Internal\fatalErrorShutdownHandler');
    }
}
?>
