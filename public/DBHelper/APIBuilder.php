<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace LazyMePHP\FormsBuilder;
use \LazyMePHP\Config\Internal\APP;

require_once 'DatabaseHelper.php';

/**
 * Build class tables
 */
class BuildTableAPI
{

    /**
     * Constructor
     *
     * Builds Form Files for each Table in the DataBase
     *
     * @param (string) (path)
     * @return (NULL)
     */
	function __construct($apiPath, $replaceRouteApi)
	{
    // SELECT Tables
    $queryString = "";
    switch (APP::DB_TYPE())
    {
      case 1: // MSSQL
            $queryString = "SELECT [Table] FROM (SELECT TABLE_NAME as [Table] FROM INFORMATION_SCHEMA.TABLES) SCH WHERE [Table] NOT LIKE '\_\_%'";
      break;
      case 2: // MYSQL
          $queryString = "SELECT `Table` FROM (SELECT DISTINCT TABLE_NAME as `Table` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".APP::DB_NAME()."') SCH WHERE `Table` NOT LIKE '\_\_%'";
      break;
    }

    // Create Folder if doesn't exist
    if (!is_dir(APP::ROOT_PATH()."/".$apiPath)) \LazyMePHP\Helper\MKDIR(APP::ROOT_PATH()."/".$apiPath);

		// Create API MASK
		if ($replaceRouteApi) {
			if (\LazyMePHP\Helper\UNLINK(APP::ROOT_PATH()."/".$apiPath."/MaskAPI.php"))
			{
				if (\LazyMePHP\Helper\TOUCH(APP::ROOT_PATH()."/".$apiPath."/MaskAPI.php"))
				{
					$routerFile = fopen(APP::ROOT_PATH()."/".$apiPath."/MaskAPI.php","w+");
					fwrite($routerFile,"<?php");
					fwrite($routerFile, "\n");
					fwrite($routerFile, "\n");
					fwrite($routerFile,"/**");
					fwrite($routerFile, "\n");
					fwrite($routerFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
					fwrite($routerFile, "\n");
					fwrite($routerFile," * @author Duarte Peixinho");
					fwrite($routerFile, "\n");
					fwrite($routerFile," *");
					fwrite($routerFile, "\n");
					fwrite($routerFile," * Source File Generated Automatically");
					fwrite($routerFile, "\n");
					fwrite($routerFile," */");
					fwrite($routerFile, "\n");
					fwrite($routerFile, "\n");

					// Generate Default Visible Fields
					fwrite($routerFile,"\$GLOBALS['API_FIELDS_AVAILABLE'] = array(");
					fwrite($routerFile, "\n");
					APP::DB_CONNECTION()->Query($queryString, $sqlObj);
					$_i = 0;
					while ($o=$sqlObj->FetchObject())
					{

						if ($_i>0) fwrite($routerFile, ",\n");
						fwrite($routerFile, "\t\"".$o->Table."\" => array(");
						$db = new \LazyMePHP\DatabaseHelper\_DB_TABLE($o->Table);
						$db->GetFieldsFromDB();
						foreach($db->GetTableFields() as $_k => $field)
						{
							if ($_k>0) fwrite($routerFile, ",");
							fwrite($routerFile, "\"".$field->GetName()."\"");
						}
						fwrite($routerFile, ")");
						$_i++;
					}
					fwrite($routerFile, "\n");
					fwrite($routerFile, ");");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "?>");
				}
        else echo "ERROR: Check your permissions to write ".$apiPath."/MaskAPI.php";
			}
      else echo "ERROR: Check your permissions to remove ".$apiPath."/MaskAPI.php";
		}
		// Create Routing Rules
		if ($replaceRouteApi) {
			if (\LazyMePHP\Helper\UNLINK(APP::ROOT_PATH()."/".$apiPath."/RouteAPI.php"))
			{
				if (\LazyMePHP\Helper\TOUCH(APP::ROOT_PATH()."/".$apiPath."/RouteAPI.php"))
				{
					$routerFile = fopen(APP::ROOT_PATH()."/".$apiPath."/RouteAPI.php","w+");
					fwrite($routerFile,"<?php");
					fwrite($routerFile, "\n");
					fwrite($routerFile, "\n");
					fwrite($routerFile,"/**");
					fwrite($routerFile, "\n");
					fwrite($routerFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
					fwrite($routerFile, "\n");
					fwrite($routerFile," * @author Duarte Peixinho");
					fwrite($routerFile, "\n");
					fwrite($routerFile," *");
					fwrite($routerFile, "\n");
					fwrite($routerFile," * Source File Generated Automatically");
					fwrite($routerFile, "\n");
					fwrite($routerFile," */");
					fwrite($routerFile, "\n");
					fwrite($routerFile, "\n");
          fwrite($routerFile, "use Pecee\SimpleRouter\SimpleRouter;");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "use \LazyMePHP\Config\Internal\APP;");
					fwrite($routerFile, "\n");
					fwrite($routerFile, "\n");

					// Generate Default Visible Fields
					APP::DB_CONNECTION()->Query($queryString, $sqlObj);
					while ($o=$sqlObj->FetchObject())
					{
            #fwrite($routerFile, "Router::Create(\"controller\", \"".$o->Table."\",APP::ROOT_PATH().\"/$apiPath/".$o->Table.".API.php\");");
            fwrite($routerFile, "/*");
            fwrite($routerFile, "\n");
            fwrite($routerFile, " * ".$o->Table." Routing");
            fwrite($routerFile, "\n");
            fwrite($routerFile, " */");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "use LazyMePHP\Forms\\".$o->Table.";");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "require_once __DIR__.\"/../Controllers/".$o->Table.".Controller.php\";");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "SimpleRouter::get('/api/".$o->Table."', function() {");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\t\$data = ".$o->Table."::Default(false, \$_GET['page']??1, APP::APP_NRESULTS());");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\techo json_encode(\$data['".$o->Table."']->Serialize(true, \$GLOBALS['API_FIELDS_AVAILABLE']));");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "});");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "SimpleRouter::get('/api/".$o->Table."/{".$db->GetPrimaryFieldName()."}', function (\$".$db->GetPrimaryFieldName().") {");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\t\$data = ".$o->Table."::Edit(\$".$db->GetPrimaryFieldName().");");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\techo json_encode(\$data['".$o->Table."']->Serialize(true, \$GLOBALS['API_FIELDS_AVAILABLE']));");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "});");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "SimpleRouter::post('/api/".$o->Table."/{id}', function (\$".$db->GetPrimaryFieldName().") {");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\t".$o->Table."::Save(\$".$db->GetPrimaryFieldName().");");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\techo json_encode(array('status' => 1));");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "});");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "SimpleRouter::post('/api/".$o->Table."', function () {");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\t".$o->Table."::Save();");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\techo json_encode(array('status' => 1));");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "});");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "SimpleRouter::delete('/api/".$o->Table."/{id}', function (\$".$db->GetPrimaryFieldName().") {");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\t".$o->Table."::Delete(\$".$db->GetPrimaryFieldName().");");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\techo json_encode(array('status' => 1));");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "});");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\n");
					}
          fwrite($routerFile, "\n");
          fwrite($routerFile, "?>");
				}
        else echo "ERROR: Check your permissions to write ".$apiPath."/RouteAPI.php";
			}
      else echo "ERROR: Check your permissions to remove ".$apiPath."/RouteAPI.php";
		}
  }
}
?>
