<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

function P($name)
{
		return filter_input(INPUT_POST, $name);
	}

	if (P("db_name") &&
			P("db_user") &&
			P("db_password1") &&
			P("db_password2") &&
			(P("db_password1") == P("db_password2")) &&
			P("db_host") &&
			P("db_db") &&
			P("app_name") &&
			P("app_title") &&
			P("app_version") &&
			P("app_description") &&
			P("app_timezone") &&
			P("email_support") &&
			P("app_fullpath")
		)
	{

		switch(P("db_db"))
		{
			case 1:
				$db_file = "src/php/DB/MSSQL.php";
			break;
			default:
			case 2:
				$db_file = "src/php/DB/MYSQL.php";
			break;
		}

		// Generate new config file
		$file = fopen("src/php/Configurations/Configurations.php","w+");
			fwrite($file,"<?php ");
			fwrite($file, "\n");
			fwrite($file,"/**");
			fwrite($file, "\n");
			fwrite($file," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
			fwrite($file, "\n");
			fwrite($file," * @author Duarte Peixinho - ".date("Y"));
			fwrite($file," *");
			fwrite($file, "\n");
			fwrite($file," * Config File Generated Automatically");
			fwrite($file, "\n");
			fwrite($file," */");
			fwrite($file, "\n");
			fwrite($file, "\n");
			fwrite($file, "require_once 'Internal/InternalConfigurations.php';\n");
			fwrite($file, "use \LazyMePHP\Config\Internal\APP;\n");
			fwrite($file, "\n");
			fwrite($file, "/**\n");
			fwrite($file, " * CONFIGURATIONS\n");
			fwrite($file, " */\n");
			fwrite($file, "\n");
			fwrite($file, "// DATABASE\n");
			fwrite($file, "\$CONFIG['DB_NAME']\t\t\t\t=\"".P("db_name")."\";\n");
			fwrite($file, "\$CONFIG['DB_USER']\t\t\t\t=\"".P("db_user")."\";\n");
			fwrite($file, "\$CONFIG['DB_PASSWORD']\t\t\t=\"".P("db_password1")."\";\n");
			fwrite($file, "\$CONFIG['DB_HOST']\t\t\t\t=\"".P("db_host")."\";\n");
			fwrite($file, "\$CONFIG['DB_TYPE']\t\t\t\t=\"".P("db_db")."\";\n");
			fwrite($file, "\$CONFIG['DB_FILE']\t\t\t\t=\"".$db_file."\";\n");
			fwrite($file, "\n");
			fwrite($file, "// APPLICATION\n");
			fwrite($file, "\$CONFIG['APP_NAME']\t\t\t\t=\"".P("app_name")."\";\n");
			fwrite($file, "\$CONFIG['APP_TITLE']\t\t\t=\"".P("app_title")."\";\n");
			fwrite($file, "\$CONFIG['APP_VERSION']\t\t\t=\"".P("app_version")."\";\n");
			fwrite($file, "\$CONFIG['APP_DESCRIPTION']\t\t=\"".P("app_description")."\";\n");
			fwrite($file, "\$CONFIG['APP_TIMEZONE']\t\t\t=\"".P("app_timezone")."\";\n");
			fwrite($file, "\$CONFIG['APP_URL_ENCRYPTION']\t\t\t=\"".(P("url_encryption")==1?true:false)."\";\n");
			fwrite($file, "\$CONFIG['APP_URL_TOKEN']\t\t\t=\"".P("url_encryption_token")."\";\n");
			fwrite($file, "\$CONFIG['APP_NRESULTS']\t\t\t=\"".P("app_nresults")."\";\n");
			fwrite($file, "\n");
			fwrite($file, "// ACTIVITY LOG\n");
			fwrite($file, "\$CONFIG['APP_ACTIViTY_LOG']\t\t\t\t=\"".P("activity_log")."\";\n");
			fwrite($file, "\$CONFIG['APP_ACTIViTY_AUTH']\t\t\t\t=\"\";\n");
			fwrite($file, "\n");
			fwrite($file, "// SUPPORT EMAIL\n");
			fwrite($file, "\$CONFIG['APP_EMAIL_SUPPORT']\t=\"".P("email_support")."\";\n");
			fwrite($file, "\n");
			fwrite($file, "// ROOT PATH\n");
			fwrite($file, "\$CONFIG['ROOT']\t\t\t\t\t=\"".str_replace('\\','/',P("app_fullpath"))."\";\n");
			fwrite($file, "\n");
			fwrite($file, "/**\n");
			fwrite($file, " * END OF CONFIGURATIONS\n");
			fwrite($file, " */\n");
			fwrite($file, "\n");
			fwrite($file, "// INITIALIZE LazyMePHP\n");
			fwrite($file, "new APP(\$CONFIG);\n");
			fwrite($file, "\n");
			fwrite($file, "?>");
		fclose($file);

		// Require newly created file
		require_once "src/php/Configurations/Configurations.php";

		rename("index.php", "oldIndex");
		rename("index", "index.php");

		// Add Javacript File
		file_put_contents('index.php',str_replace('<!-- !!REPLACE_JS!! -->','<script src="src/js/'.P("app_name").'.js"></script>',file_get_contents('index.php')));
		fopen('src/js/'.P("app_name").'.js', 'w');

    echo "<img src='src/img/logo.png' />";
    echo "<br>";
    echo "<br>";
    // Running Helper
		if (P("run_db_helper"))
		{
			echo "<h2>Configurations successfuly created. This file was auto deleted, redirecting to DB Helper GUI</h2>";
			echo "<script>setTimeout(function(){ window.open('DBHelper/index.php', '_self'); }, 3000);</script>";
		}
		else
		{
			echo "<h2>Configurations successfuly created. This file was auto deleted, redirecting to index.php</h2>";
			echo "<script>setTimeout(function(){ window.open('index.php', '_self'); }, 3000);</script>";
		}
		exit();
	}

?>

	<html>
	<head>
		<title>Configure App</title>

<script type="text/javascript">
	function $(name)
	{
		return document.getElementById(name);
	}
	var errorMsg = "";
	function addError(msg)
	{
		errorMsg += (errorMsg.length>0?"\n":"") + msg;
	}
	function ValidateForm()
	{
		errorMsg = "";

		if ($("db_name").value.length===0)
			addError("Database name is empty.");

		if ($("db_user").value.length===0)
			addError("Database user is empty.");

		if ($("db_password1").value!==$("db_password2").value || $("db_password1").value.length===0)
			addError("Database password is empty or password mismacth.");

		if ($("db_host").value.length===0)
			addError("Database host is empty.");

		if ($("app_name").value.length===0)
			addError("Application name is empty.");

		if ($("app_title").value.length===0)
			addError("Application title is empty.");

		if ($("app_version").value.length===0)
			addError("Application version is empty.");

		if ($("app_description").value.length===0)
			addError("Application Description is empty.");

		if ($("app_timezone").value.length===0)
			addError("Application Timezone is empty.");

		if ($("email_support").value.length===0)
			addError("Email Support is empty.");

		if ($("app_fullpath").value.length===0)
			addError("Application Fullpath is empty.");

		if ($("app_fullpath").value.length===0)
			addError("Nr of results is empty");

		if (errorMsg.length>0) alert(errorMsg);
		else {
			// Proceed with Registration
			$("form").submit();
		}
	}

</script>

	</head>

	<body>
    <img src='src/img/logo.png' />
    <H1>LazyMePHP Configuration</H1>
    <h3>Edit file on src/php/Configurations/Configurations.php or edit these fields</h3>

		<form id="form" method="POST" action="">
			<table>
				<tr>
					<td><b>Database Name:</b></td><td><input type="text" name="db_name" id="db_name" /></td>
				</tr>
				<tr>
					<td><b>Database User:</b></td><td><input type="text" name="db_user" id="db_user" /></td>
				</tr>
				<tr>
					<td><b>Database Password:</b></td><td><input type="password" name="db_password1" id="db_password1" /></td>
				</tr>
				<tr>
					<td><b>Database Password(re-type):</b></td><td><input type="password" name="db_password2" id="db_password2" /></td>
				</tr>
				<tr>
					<td><b>Database Host:</b></td><td><input type="text" name="db_host" id="db_host" /></td>
				</tr>
				<tr>
					<td><b>Database:</b></td><td><select name="db_db" id="db_db"><option value="2" checked>MySQL</option><option value="1">SQLServer</option></select></td>
				</tr>
				<tr>
					<td><b>Application Name:</b></td><td><input type="text" name="app_name" id="app_name" /></td>
				</tr>
				<tr>
					<td><b>Application Title:</b></td><td><input type="text" name="app_title" id="app_title" /></td>
				</tr>
				<tr>
					<td><b>Application Version:</b></td><td><input type="text" name="app_version" id="app_version" /></td>
				</tr>
				<tr>
					<td><b>Application Description:</b></td><td><input type="text" name="app_description" id="app_description" /></td>
				</tr>
				<tr>
					<td><b>Application Time Zone:</b></td><td><input type="text" name="app_timezone" id="app_timezone" value="Europe/Lisbon" /></td>
				</tr>
				<tr>
					<td><b>URL Encryption (arguments encryption):</b></td><td><input type="checkbox" name="url_encryption" id="url_encryption" value="1" /></td>
				</tr>
				<tr>
					<td><b>URL Encryption Secret:</b></td><td><input type="text" name="url_encryption_token" id="url_encryption_token" value="Secr3T!" /></td>
				</tr>
				<tr>
					<td><b>Email Support:</b></td><td><input type="text" name="email_support" id="email_support" /></td>
				</tr>
				<tr>
					<td><b>Application Full Path:</b></td><td><input type="text" name="app_fullpath" id="app_fullpath" value="<?php echo dirname(__FILE__); ?>" /></td>
				</tr>
				<tr>
					<td><b>Nr Results In Each Collection:</b></td><td><input type="text" name="app_nresults" id="app_nresults" value="100" /></td>
				</tr>
				<tr>
					<td><b>Enable Activity Log:</b></td><td><input type="checkbox" name="activity_log" id="activity_log" value="1" /></td>
				</tr>
				<tr>
					<td><b>Run DB Class Builder Helper:</b></td><td><input type="checkbox" name="run_db_helper" id="run_db_helper" value="1" /></td>
				</tr>
				<tr>
					<td colspan="2" align="right">
						<input type="button" name="Save" value="Save" id="" onclick="ValidateForm();" />
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<span style="font-size:8pt;">(This file will be removed if fields are completed correctly)</span>
					</td>
				</tr>
			</table>
		</form>

	</body>
</html>
