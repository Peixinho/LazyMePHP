<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace LazyMePHP\FormsBuilder;
use \LazyMePHP\Config\Internal\APP;
use \LazyMePHP\DatabaseHelper;

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
	function __construct($apiPath, $classesPath, $tablesList, $replaceRouteApi)
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
    if (!is_dir($apiPath)) \LazyMePHP\Helper\MKDIR($apiPath);

		// Create Routing Rules
		if ($replaceRouteApi) {
			if (\LazyMePHP\Helper\UNLINK($apiPath."/RouteAPI.php"))
			{
				if (\LazyMePHP\Helper\TOUCH($apiPath."/RouteAPI.php"))
				{
					$routerFile = fopen($apiPath."/RouteAPI.php","w+");
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
					fwrite($routerFile, "use \LazyMePHP\Core\Router\Router;");
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
					APP::DB_CONNECTION()->Query($queryString, $sqlObj);
					while ($o=$sqlObj->FetchObject())
					{
            fwrite($routerFile, "Router::Create(\"controller\", \"".$o->Table."\",\"src/".$o->Table.".API.php\");");
            fwrite($routerFile, "\n");
					}
          fwrite($routerFile, "\n");
          fwrite($routerFile, "?>");
				}
        else echo "ERROR: Check your permissions to write ".$apiPath."/RouteAPI.php";
			}
      else echo "ERROR: Check your permissions to remove ".$apiPath."/RouteAPI.php";
		}

		APP::DB_CONNECTION()->Query($queryString, $sqlObj);
		while ($o=$sqlObj->FetchObject())
		{
			if (is_array($tablesList))
			foreach($tablesList as $__table)
			{
        if (array_search($o->Table, $tablesList)>=0) {
					$db = new \LazyMePHP\DatabaseHelper\_DB_TABLE($o->Table);
					$db->GetFieldsFromDB();
					$this->ConstructAPI($apiPath, $classesPath, $db);
				}
			}
		}
	}

	protected function ConstructAPI($apiPath, $classesPath, $db)
	{
    // Create Folder if doesn't exist
    if (!is_dir($apiPath)) \LazyMePHP\Helper\MKDIR($apiPath);

		if (\LazyMePHP\Helper\UNLINK($apiPath."/".$db->GetTableName().".API.php"))
		{
			if (\LazyMePHP\Helper\TOUCH($apiPath."/".$db->GetTableName().".API.php"))
			{
				$apiFile = fopen($apiPath."/".$db->GetTableName().".API.php","w+");
				fwrite($apiFile,"<?php");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\n");
				fwrite($apiFile,"/**");
				fwrite($apiFile, "\n");
				fwrite($apiFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
				fwrite($apiFile, "\n");
				fwrite($apiFile," * @author Duarte Peixinho");
				fwrite($apiFile, "\n");
				fwrite($apiFile," *");
				fwrite($apiFile, "\n");
				fwrite($apiFile," * Source File Generated Automatically");
				fwrite($apiFile, "\n");
				fwrite($apiFile," */");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "namespace LazyMePHP\API;\n");
				fwrite($apiFile, "use \LazyMePHP\Config\Internal\APP;\n");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "require_once APP::ROOT_PATH().\"/".$classesPath."/includes.php\";");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "require_once __DIR__.\"/../APIRequest.php\";");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "require_once __DIR__.\"/RouteAPI.php\";");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "class ".$db->GetTableName()." extends APIRequest {");
				fwrite($apiFile, "\n");

				// Get Primary Key
				$primaryKey = NULL;
				foreach($db->GetTableFields() as $field)
				{
					if ($field->IsPrimaryKey())
					{
						$primaryKey = $field;
						fwrite($apiFile, "\n");
						fwrite($apiFile, "\tprivate \$pk = \"".$field->GetName()."\";");
						fwrite($apiFile, "\n");
						fwrite($apiFile, "\n");
						break;
					}
				}
				fwrite($apiFile, "\tfunction POST(\$query, \$body)");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\$_".$db->GetTableName()." = new \\LazyMePHP\\Classes\\".$db->GetTableName()."();");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\$data = json_decode(\$body);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\tif (!\$query['pk'])");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t{");
				fwrite($apiFile, "\n");
				foreach ($db->GetTableFields() as $field) {
					if (!$field->IsPrimaryKey())
					{
						fwrite($apiFile, "\t\t\t\$_".$db->GetTableName()."->Set".$field->GetName()."(\$data->".$field->GetName().");");
						fwrite($apiFile, "\n");
					}
				}
				fwrite($apiFile, "\t\t\t\$pk = \"Get\".\$this->pk;");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\tif (@\$_".$db->GetTableName()."->Save() && \$_".$db->GetTableName()."->\$pk()) {");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\theader(\"HTTP/1.1 200 OK\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\treturn array(\"status\" => 1);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\theader(\"HTTP/1.1 204 OK\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\treturn array(\"status\" => 0);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\tfunction GET(\$query, \$body)");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\tif (array_key_exists('pk', \$query) && \$query['pk'])");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\$_".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$query['pk']);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\$pk = \"Get\".\$this->pk;");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\tif (\$_".$db->GetTableName()."->\$pk())");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\theader(\"HTTP/1.1 200 OK\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\treturn \$_".$db->GetTableName()."->Serialize(true, \$GLOBALS['API_FIELDS_AVAILABLE']);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t} else {");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\theader(\"HTTP/1.1 204 NO CONTENT\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\treturn array(\"status\" => 0);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t} else {");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\$_".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."_List();");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\tif (count(\$query)>0)");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\tforeach(\$query as \$key => \$arg)");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\t\tif (\$arg)");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\t\t\tcall_user_func_array(array(\$_".$db->GetTableName().",\$key), explode(',', \$arg));");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\telse");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\t\$_".$db->GetTableName()."->FindAll();");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\theader(\"HTTP/1.1 200 OK\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\treturn \$_".$db->GetTableName()."->Serialize(true, \$GLOBALS['API_FIELDS_AVAILABLE']);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\theader(\"HTTP/1.1 204 NO CONTENT\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\treturn array(\"status\" => 0);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\tfunction PUT(\$query, \$body)");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\$data = json_decode(\$body);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\tif (array_key_exists('pk', \$query) && \$query['pk'])");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\$_".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$query['pk']);");
				fwrite($apiFile, "\n");
				foreach ($db->GetTableFields() as $field) {
					if (!$field->IsPrimaryKey())
					{
						fwrite($apiFile, "\t\t\tif (\$data->".$field->GetName().")");
						fwrite($apiFile, "\n");
						fwrite($apiFile, "\t\t\t\t\$_".$db->GetTableName()."->Set".$field->GetName()."(\$data->".$field->GetName().");");
						fwrite($apiFile, "\n");
					}
				}
				fwrite($apiFile, "\t\t\tif (@\$_".$db->GetTableName()."->Save()) {");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\theader(\"HTTP/1.1 200 OK\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\treturn array(\"status\" => 1);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\theader(\"HTTP/1.1 204 OK\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\treturn array(\"status\" => 0);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\tfunction DELETE(\$query, \$body)");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\$data = json_decode(\$body);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\tif (\$query['pk'])");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\$_".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$query['pk']);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\$pk = \"Get\".\$this->pk;");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\tif (\$_".$db->GetTableName()."->\$pk())");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t{");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\t\$_".$db->GetTableName()."->Delete();");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\theader(\"HTTP/1.1 200 OK\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t\treturn array(\"status\" => 1);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\theader(\"HTTP/1.1 204 OK\");");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t\treturn array(\"status\" => 0);");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "\t}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "}");
				fwrite($apiFile, "\n");
				fwrite($apiFile, "?>");
			}
			else echo "ERROR: Check your permissions to write ".$apiPath."/".$db->GetTableName().".php";
		}
		else echo "ERROR: Check your permissions to remove ".$apiPath."/".$db->GetTableName().".php";
	}
}
?>
