<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
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
        $from_mail="noreply@chalgarve.min-saude.pt";
        $subject="Application ".APP::APP_NAME()." thrown an error.";
        $message=$errorMsg;

        $message.="<br>";
        $message.="<br><b>Data</b>";
        $message.="<br>".json_encode($_SESSION);
        $message.="<br>".json_encode($_POST);
        $message.="<br>".json_encode($_GET);
        @Sendmail($from_mail, $to_mail, $subject, $message);
		    echo $errorMsg;
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
function FatalErrorShutdownHandler()
{
  $last_error = error_get_last();
  if (is_array($last_error) && array_key_exists('type', $last_error) && $last_error['type'] === E_ERROR) {
    // fatal error
    ErrorHandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
  }
}

/**
 * SendMail
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
        require_once APP::ROOT_PATH()."/src/Ext/jwt_helper.php";

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
			require_once APP::ROOT_PATH()."/src/Ext/jwt_helper.php";
			$token = \JWT::decode(parse_url($url)['query'],APP::APP_URL_TOKEN());
			$url="?".($token->query);
			// Set _GET
			parse_str($token->query, $query);
			foreach($query as $key => $arg)
				$_GET[$key] = $arg;
		}
		return $url;
	}
    /**
     * URLENCODEAPPEND
     *
     * Returns URL Encoded with
     * appended var
     *
     * @param (string)
     * @return (string) (url)
     */
	static function URLENCODEAPPEND($url)
	{
    // Get GET vars
    $get = "";
    foreach($_GET as $k => $g) if ($g) $get.=(strlen($get)==0?"?":"&")."$k=$g";
		return APP::URLENCODE($get.(strlen($get)==0?"?":"&").$url);
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

    /** @var _app_activity_log */
    private static $_app_activity_log;
    /**
     * APP_ACTIVITY_LOG
     *
     * Returns If Activity Log is enabled
     *
     * @param (null)
     * @return (bool)
     */
    static function APP_ACTIVITY_LOG()
    {
      return APP::$_app_activity_log;
    }

    /** @var _app_activity_auth */
    private static $_app_activity_auth;
    /**
     * APP_ACTIVITY_LOG
     *
     * Returns Activity Auth to be Used
     *
     * @param (null)
     * @return (string) (possibly, username)
     */
    static function APP_ACTIVITY_AUTH()
    {
      return APP::$_app_activity_auth;
    }

    /** @var _logdata */
    private static $_app_logdata = array();
    /**
     * APP_LOGDATA
     *
     * Sets LOG DATA
     *
     * @param (string) table
     * @param (array) log
     * @return (null)
     */
    static function APP_LOGDATA($table,$log,$pk=NULL,$method=NULL)
    {
      if (!array_key_exists($table, APP::$_app_logdata)) APP::$_app_logdata[$table] = array();
      array_push(APP::$_app_logdata[$table], array("log" => $log, "pk" => $pk, "method" => $method));
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
        APP::$_app_activity_log   = $CONFIG['APP_ACTIVITY_LOG'];
        APP::$_app_activity_auth  = $CONFIG['APP_ACTIVITY_AUTH'];
        APP::$_app_url_token	    = $CONFIG['APP_URL_TOKEN'];
        APP::$_app_nresults 	    = $CONFIG['APP_NRESULTS'];

        // Set Timezone
        date_default_timezone_set(APP::$_app_timezone);
        // Registers User Error Function Replacement
        @set_error_handler('\LazyMePHP\Config\Internal\ErrorHandler');
        // Registers Fatal Error Function Replacement */
        @register_shutdown_function('\LazyMePHP\Config\Internal\FatalErrorShutdownHandler');
    }

    /**
     * Log Activity
     *
     * Records Activity
     *
     * @param (string) (controller)
     * @param (string) (multiple arguments)
     * @return (null)
     */
    static function LOG_ACTIVITY() {
      if (APP::APP_ACTIVITY_LOG()) {
        $queryString = "INSERT INTO __LOG_ACTIVITY (`date`,`user`,`method`) VALUES (?,?,?)";
        APP::DB_CONNECTION()->Query($queryString, $obj, array(date("Y-m-d H:i"),APP::$_app_activity_auth,$_SERVER['REQUEST_METHOD']));
        $id = APP::DB_CONNECTION()->GetLastInsertedID('__LOG_ACTIVITY');
        $queryString = "INSERT INTO __LOG_ACTIVITY_OPTIONS (`id_log_activity`, `subOption`, `value`) VALUES ";
        $count = 0;
        $queryStringData = array();
        foreach($_GET as $kArg => $arg) {
          if ($arg) {
            $queryString.=($count>0?",":"")."(?,?,?)";
            array_push($queryStringData,$id, $kArg, $arg);
            $count++;
          }
        }
        if ($count>0)
          APP::DB_CONNECTION()->Query($queryString, $obj, $queryStringData);

        $count = 0;
        $queryString = "INSERT INTO __LOG_DATA (`id_log_activity`, `table`, `pk`, `method`, `field`, `dataBefore`, `dataAfter`) VALUES ";
        $queryStringData = array();
        if (is_array(APP::$_app_logdata)) {
          foreach(APP::$_app_logdata as $table => $d) 
          foreach($d as $data) {
            if (is_array($data['log'])) {
              foreach($data['log'] as $field => $values) {
                $queryString.=($count>0?",":"")."(?,?,?,?,?,?,?)";
                array_push($queryStringData,$id,$table,(array_key_exists('pk',$data)?$data['pk']:''), (array_key_exists('method',$data)?$data['method']:''), $field, $values[0], $values[1]);
                $count++;
              }
            }
          }
        }
        if ($count>0)
          APP::DB_CONNECTION()->Query($queryString, $obj,$queryStringData);
      }
    }
}
?>