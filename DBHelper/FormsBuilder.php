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
require_once 'Helper.php';

/**
 * Build class tables
 */
class BuildTableForms
{

    /**
     * Constructor
     *
     * Builds Form Files for each Table in the DataBase
     *
     * @param (string) (path)
     * @return (NULL)
     */
	function __construct($controllersPath, $viewsPath, $classesPath, $tablesList, $replaceRouteForms, $cssClass)
	{
        // SELECT Tables
        $queryString = "";
        switch (APP::DB_TYPE())
        {
            case 1: // MSSQL
                $queryString = "SELECT TABLE_NAME as [Table] FROM INFORMATION_SCHEMA.TABLES";
            break;
            case 2: // MYSQL
                $queryString = "SELECT DISTINCT TABLE_NAME as `Table` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".APP::DB_NAME()."'";
            break;
		}

    // Create Folder if doesn't exist
    if (!is_dir($controllersPath)) \LazyMePHP\Helper\MKDIR($controllersPath);

		// Create Routing Rules
		if ($replaceRouteForms) {
			if (\LazyMePHP\Helper\UNLINK($controllersPath."/RouteForms.php"))
			{
				if (\LazyMePHP\Helper\TOUCH($controllersPath."/RouteForms.php"))
				{
					$routerFile = fopen($controllersPath."/RouteForms.php","w+");
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
					fwrite($routerFile, "use LazyMePHP\Config\Internal\APP;");
					fwrite($routerFile, "\n");
					fwrite($routerFile, "use LazyMePHP\Router\Router;");
					fwrite($routerFile, "\n");
					fwrite($routerFile, "\n");
					fwrite($routerFile,"require_once APP::ROOT_PATH().\"/src/php/Router/Router.php\";");
					fwrite($routerFile, "\n");
					fwrite($routerFile, "\n");

          APP::DB_CONNECTION()->Query($queryString, $sqlObj);
          while ($o=$sqlObj->FetchObject())
          {
            $db = new \LazyMePHP\DatabaseHelper\_DB_TABLE($o->Table);
            $db->GetFieldsFromDB();
            $this->ConstructForm($viewsPath, $classesPath, $db, $cssClass);
            $this->ConstructController($controllersPath, $viewsPath, $classesPath, $db);

            fwrite($routerFile, "Router::Create(\"controller\", \"".$o->Table."\", __DIR__.\"/".$o->Table.".Controller.php\");");
            fwrite($routerFile, "\n");
          }
          fwrite($routerFile, "\n");
          fwrite($routerFile, "?>");
        }
        else echo "ERROR: Check your permissions to write ".$controllersPath."/RouteForms.php";
      }
      else echo "ERROR: Check your permissions to remove ".$controllersPath."/RouteForms.php";
    }
  }

	protected function ConstructForm($controllersPath, $classesPath, $db, $cssClass)
	{
    // Create Folder if doesn't exist
    if (!is_dir($controllersPath)) \LazyMePHP\Helper\MKDIR($controllersPath);

		if (\LazyMePHP\Helper\UNLINK($controllersPath."/".$db->GetTableName().".View.php"))
		{
			if (\LazyMePHP\Helper\TOUCH($controllersPath."/".$db->GetTableName().".View.php"))
			{
        $buttonClass = $cssClass['button'];
        $inputClass = $cssClass['input'];
        $anchorClass = $cssClass['anchor'];
        $tableClass = $cssClass['table'];

				$viewFile = fopen($controllersPath."/".$db->GetTableName().".View.php","w+");
				fwrite($viewFile,"<?php");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\n");
				fwrite($viewFile,"/**");
				fwrite($viewFile, "\n");
				fwrite($viewFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
				fwrite($viewFile, "\n");
				fwrite($viewFile," * @author Duarte Peixinho");
				fwrite($viewFile, "\n");
				fwrite($viewFile," *");
				fwrite($viewFile, "\n");
				fwrite($viewFile," * Source File Generated Automatically");
				fwrite($viewFile, "\n");
				fwrite($viewFile," */");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "namespace LazyMePHP\Forms;\n");
				fwrite($viewFile, "use \LazyMePHP\Config\Internal\APP;\n");
				fwrite($viewFile, "use \LazyMePHP\Classes;\n");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "require_once __DIR__.\"/../Configurations/Configurations.php\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "require_once \"".$classesPath."/includes.php\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\n");

				// Get Primary Key
				$primaryKey = NULL;
				foreach($db->GetTableFields() as $field)
				{
					if ($field->IsPrimaryKey())
					{
						$primaryKey = $field;
						break;
					}
				}
				fwrite($viewFile, "echo \"<h3>".$db->GetTableName()."</h3>\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "switch((array_key_exists('action', \$_GET)?\$_GET['action']:NULL))");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "{");
				fwrite($viewFile, "\n");

				if ($primaryKey)
				{
					fwrite($viewFile, "\tcase \"new\":");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\tcase \"edit\":");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\t\$_".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."();");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\tif (array_key_exists('".$primaryKey->GetName()."', \$_GET) && \$_GET[\"".$primaryKey->GetName()."\"]) {");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\t\t\$_".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$_GET[\"".$primaryKey->GetName()."\"]);");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\t}");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\techo \"<form method='POST' action='\".APP::URLENCODE(\"?controller=".$db->GetTableName()."&action=save&id=\".(array_key_exists('".$primaryKey->GetName()."', \$_GET)?\$_GET['".$primaryKey->GetName()."']:'')).\"' onsubmit='return LazyMePHP.ValidateForm(this);'>\";");
					fwrite($viewFile, "\n");
					foreach ($db->GetTableFields() as $field) {
						if (!$field->IsAutoIncrement() || !$field->IsPrimaryKey())
						{
							fwrite($viewFile, "\t\techo \"<b>".$field->GetName().":</b> \";");
							if ($field->HasForeignKey() && !is_null($field->GetForeignField()))
							{
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\techo \"<select name='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." ".(!$field->AllowNull()?"validation='NOTNULL' validation-fail='".$field->GetName()." cannot be empty'":"")." />\";");
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\t\techo \"<option value=''>-</option>\";");

								// fetchview
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\t\t\$list = new \LazyMePHP\Classes\\".$field->GetForeignTable()."_List();");
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\t\t\$list->FindAll();");
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\t\t\$list = \$list->GetList(false);");
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\t\tif (sizeof(\$list)>0)");
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\t\t\tforeach(\$list as \$member) {");
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\t\t\t\techo \"<option value='\".\$member->Get".$field->GetForeignField()."().\"' \".(\$_".$db->GetTableName()."->Get".$field->GetName()."()==\$member->Get".$field->GetForeignField()."()?\"selected\":\"\").\">\".\$member->GetDescriptor().\"</option>\";");
								fwrite($viewFile, "\n");
								fwrite($viewFile, "\t\t\t\t}");
								fwrite($viewFile, "\n");


								fwrite($viewFile, "\t\techo \"</select>\";");
								fwrite($viewFile, "\n");
							} else {
								fwrite($viewFile, "\n");
								switch ($field->GetDataType())
								{
									case "bit":
										fwrite($viewFile, "\t\techo \"<input type='checkbox' name='".$field->GetName()."' id='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." value='1' \".(\$_".$db->GetTableName()."->Get".$field->GetName()."()==1?\"checked\":\"\").\" />\";");
										break;
									case "float":
									case "date":
									case "int":
									case "varchar":
										fwrite($viewFile, "\t\techo \"<input type='text' name='".$field->GetName()."' id='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." value='\".\$_".$db->GetTableName()."->Get".$field->GetName()."().\"' ".(!$field->AllowNull()?"validation='NOTNULL' validation-fail='".$field->GetName()." cannot be empty'":"")." ".($field->GetDataLength()?"maxlength='".$field->GetDataLength()."'":"")." />\";");
									break;
								}
								fwrite($viewFile, "\n");
							}
						}
						fwrite($viewFile, "\t\techo \"<br/>\";");
						fwrite($viewFile, "\n");
					}
					fwrite($viewFile, "\n");
          fwrite($viewFile, "\t\techo \"<br>\";");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\techo \"<input type='submit' name='submit' ".($buttonClass?"class='".$buttonClass."'":"")." value='Save' />\";");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\techo \"<input type='button' name='cancel' ".($buttonClass?"class='".$buttonClass."'":"")." value='Cancel' onclick='window.open(\\\"\".APP::URLENCODE(\"?controller=".$db->GetTableName()."\").\"\\\", \\\"_self\\\");' />\";");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\tbreak;");
					fwrite($viewFile, "\n");
				}
				// Show List
				fwrite($viewFile, "\tdefault:");

				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t\$page = (\array_key_exists('page', \$_GET)?\$_GET['page']:1);");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t\$limit = APP::APP_NRESULTS();");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\techo \"<a ".($anchorClass?"class='".$anchorClass."'":"")." href='\".APP::URLENCODE(\"?controller=".$db->GetTableName()."&action=new\").\"'>Add New</a>\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\techo \"<br>\";");
				fwrite($viewFile, "echo \"<br>\";");
				fwrite($viewFile, "\n");

				$haveForeignMembers = false;
				foreach ($db->GetTableFields() as $field)
				{
					if ($field->HasForeignKey() && !is_null($field->GetForeignField()))
					{
						// Write form for filter
						if (!$haveForeignMembers)
						{
							fwrite($viewFile, "\t\techo \"<form method='POST' action='\".APP::URLENCODE(\"?controller=".$db->GetTableName()."\").\"'>\";");
							fwrite($viewFile, "\n");
							fwrite($viewFile, "\t\techo \"<input type='hidden' name='controller' value='".$db->GetTableName()."' />\";");
							fwrite($viewFile, "\n");
						}
						fwrite($viewFile, "\t\techo \"<br>\";");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\techo \"<b>".$field->GetName().":</b> \";");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\techo \"<select name='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." />\";");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\t\techo \"<option value=''>-</option>\";");

						// fetch from db
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\t\t\$list = new \LazyMePHP\Classes\\".$field->GetForeignTable()."_List();");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\t\t\$list->FindAll();");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\t\t\$list = \$list->GetList(false);");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\t\tif (sizeof(\$list)>0)");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\t\tforeach(\$list as \$member) {");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\t\t\techo \"<option value='\".\$member->Get".$field->GetForeignField()."().\"' \".(array_key_exists('".$field->GetName()."', \$_POST) && \$_POST[\"".$field->GetName()."\"]==\$member->Get".$field->GetForeignField()."()?\"selected\":\"\").\">\".\$member->GetDescriptor().\"</option>\";");
						fwrite($viewFile, "\n");
						fwrite($viewFile, "\t\t\t}");
						fwrite($viewFile, "\n");

						fwrite($viewFile, "\t\techo \"</select>\";");
						fwrite($viewFile, "\n");

						$haveForeignMembers = true;
					}
				}

				if ($haveForeignMembers)
				{
					fwrite($viewFile, "\t\techo \"<br>\";");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\techo \"<input type='submit' ".($buttonClass?"class='".$buttonClass."'":"")." value='Filter'/>\";");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\techo \"</form>\";");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\techo \"<br>\";");
				}
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t\$list = new \LazyMePHP\Classes\\".$db->GetTableName()."_List();");
				fwrite($viewFile, "\n");

				// Check if there are filters
				if ($haveForeignMembers)
				{
					fwrite($viewFile, "\t\t\$haveFilter = false;");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\t\$filter = \"\";");
					fwrite($viewFile, "\n");
					foreach ($db->GetTableFields() as $field)
					{
						if ($field->HasForeignKey() && !is_null($field->GetForeignField()))
						{
							fwrite($viewFile, "\t\tif (array_key_exists('".$field->GetName()."', \$_POST) && \$_POST[\"".$field->GetName()."\"]) { \$filter.= (strlen(\$filter)>0?\" AND \":\"\").\"".$db->GetTableName().".".$field->GetName()." = '\".\$_POST[\"".$field->GetName()."\"].\"'\"; \$haveFilter = true; }");
							fwrite($viewFile, "\n");
						}
					}

          fwrite($viewFile, "\n");
          fwrite($viewFile, "\t\tif (\$haveFilter) {");
          fwrite($viewFile, "\n");
          fwrite($viewFile, "\t\t\t\$list->CustomFind(\$filter);");
          fwrite($viewFile, "\n");
          fwrite($viewFile, "\t\t} else {");
          fwrite($viewFile, "\n");
          fwrite($viewFile, "\t\t\t\$list->FindAll();");
          fwrite($viewFile, "\n");
          fwrite($viewFile, "\t\t}");
        }
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t\$count = \$list->GetCount();");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t\$countPage = (\$count / \$limit);");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t\$countPage+=((\$count % \$limit) != 0 ? 1:0);");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t\$list->Limit(\$limit,(\$page-1)*\$limit);");
				fwrite($viewFile, "\n");

				fwrite($viewFile, "\t\tif (\$page > 1) echo \"<a ".($anchorClass?"class='".$anchorClass."'":"")." href='\".APP::URLENCODE(\"?controller=".$db->GetTableName()."&page=\".(\$page-1)).\"'>&lt;&lt;</a>\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\tfor (\$i=1;\$i<=\$countPage;\$i++) {");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t\t\tif (\$i!=\$page)");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t\t\t\techo \" <a ".($anchorClass?"class='".$anchorClass."'":"")." href='\".APP::URLENCODE(\"?controller=".$db->GetTableName()."&page=\$i\").\"'>\". \$i .\"</a> \";");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t\t\telse");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t\t\t\techo \" [\".\$i.\"] \";");
        fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t}");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\tif ((\$page+1) < \$countPage) echo \"<a ".($anchorClass?"class='".$anchorClass."'":"")." href='\".APP::URLENCODE(\"?controller=".$db->GetTableName()."&page=\".(\$page+1)).\"'>&gt;&gt;</a>\";");
				fwrite($viewFile, "echo \"<br>\";");
				fwrite($viewFile, "echo \"<br>\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\techo \"<table ".($tableClass?"class='".$tableClass."'":"").">");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t<tr>\";");
				fwrite($viewFile, "\n");
				if ($primaryKey) {
					fwrite($viewFile, "\t\t\techo \"<th><b>edit</b></th>\";");
					fwrite($viewFile, "\n");
					fwrite($viewFile, "\t\t\techo \"<th><b>delete</b></th>\";");
				}
				fwrite($viewFile, "\n");
				foreach ($db->GetTableFields() as $field) {
					fwrite($viewFile, "\t\t\techo \"<th><b>".$field->GetName()."</b></th>\";");
					fwrite($viewFile, "\n");
				}
				fwrite($viewFile, "\t\techo \"</tr>\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\tforeach(\$list->GetList() as \$member) {");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t\techo \"<tr>\";");
				fwrite($viewFile, "\n");
				if ($primaryKey) fwrite($viewFile, "\t\t\techo \"<td><a ".($anchorClass?"class='".$anchorClass."'":"")." href='\".APP::URLENCODE(\"?controller=".$db->GetTableName()."&action=edit&".$primaryKey->GetName()."=\".\$member->Get".$primaryKey->GetName()."()).\"'>edit</a></td>\";\n\t\t\techo \"<td><a ".($anchorClass?"class='".$anchorClass."'":"")." href='\".APP::URLENCODE(\"?controller=".$db->GetTableName()."&action=delete&".$primaryKey->GetName()."=\".\$member->Get".$primaryKey->GetName()."()).\"'>delete</a></td>\";");
				fwrite($viewFile, "\n");
				foreach ($db->GetTableFields() as $field) {
					if ($primaryKey) { 
            if ($field->HasForeignKey()) 
              fwrite($viewFile, "\t\t\techo \"<td>\".\$member->Get".$field->GetName()."Object()->GetDescriptor().\"</td>\";");
            else
              fwrite($viewFile, "\t\t\techo \"<td>\".\$member->Get".$field->GetName()."().\"</td>\";");

            fwrite($viewFile, "\n");
          }
				}
				fwrite($viewFile, "\t\t\techo \"</tr>\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\t}");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\t\techo \"</table>\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "\tbreak;");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "}");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "echo \"</form>\";");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "?>");
				fwrite($viewFile, "\n");
				fwrite($viewFile, "<script>\nfunction Init() {}\n</script>");
				fclose($viewFile);
			}
			else echo "ERROR: Check your permissions to write ".$controllersPath."/".$db->GetTableName().".View.php";
		}
		else echo "ERROR: Check your permissions to remove ".$controllersPath."/".$db->GetTableName().".View.php";
	}

	function ConstructController($controllersPath, $viewsPath, $classesPath, $db)
	{
    // Create Folder if doesn't exist
    if (!is_dir($controllersPath)) \LazyMePHP\Helper\MKDIR($controllersPath);

		if (\LazyMePHP\Helper\UNLINK($controllersPath."/".$db->GetTableName().".Controller.php"))
		{
			if (\LazyMePHP\Helper\TOUCH($controllersPath."/".$db->GetTableName().".Controller.php"))
			{
				$controllerFile = fopen($controllersPath."/".$db->GetTableName().".Controller.php","w+");
				fwrite($controllerFile,"<?php");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile,"/**");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile," * @author Duarte Peixinho");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile," *");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile," * Source File Generated Automatically");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile," */");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile, "namespace LazyMePHP\Forms;\n");
				fwrite($controllerFile, "use \LazyMePHP\Config\Internal\APP;\n");
				fwrite($controllerFile, "use \LazyMePHP\Classes;\n");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile, "require_once __DIR__.\"/../Configurations/Configurations.php\";");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile, "require_once \"".$classesPath."/includes.php\";");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile, "\n");

				// Get Primary Key
				$primaryKey = NULL;
				foreach($db->GetTableFields() as $field)
				{
					if ($field->IsPrimaryKey())
					{
						$primaryKey = $field;
						break;
					}
				}
				fwrite($controllerFile, "switch((array_key_exists('action', \$_GET)?\$_GET['action']:NULL))");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile, "{");
				fwrite($controllerFile, "\n");

				if ($primaryKey)
				{
					fwrite($controllerFile, "\tcase \"save\":");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\t\$obj = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$_GET[\"".$field->GetName()."\"]);");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\t\$fieldsNull = NULL;");
					fwrite($controllerFile, "\n");
					foreach($db->GetTableFields() as $field)
					{
						if (!$field->IsPrimaryKey())
						{
							if (!$field->AllowNull())
							{
								fwrite($controllerFile, "\t\tif (filter_input(INPUT_POST, \"".$field->GetName()."\")==false) \$fieldsNull .= (!is_null(\$fieldsNull)?\",\":\"\").\"".$field->GetName()."\";");
								fwrite($controllerFile, "\n");
							}
							fwrite($controllerFile, "\t\t".(!$field->AllowNull()?"else ":"")."\$obj->Set".$field->GetName()."(filter_input(INPUT_POST, \"".$field->GetName()."\"));");
							fwrite($controllerFile, "\n");
						}
					}
					fwrite($controllerFile, "\t\tif (\$fieldsNull)");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\t\ttrigger_error(\"NULL value not allowed!\\nFields: \$fieldsNull\\nError: \", E_USER_ERROR);");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\telse");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\t\t\$obj->Save();");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\theader(\"location: \".APP::URLENCODE(\"?controller=".$db->GetTableName()."&success=1\"));");

					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\tbreak;");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\tcase \"delete\":");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\tif (\$_GET[\"".$primaryKey->GetName()."\"]) {");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\t\t\$obj = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$_GET[\"".$primaryKey->GetName()."\"]);");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\t\t\$obj->Delete();");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\t}");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\theader(\"location: \".APP::URLENCODE(\"?controller=".$db->GetTableName()."&success=2\"));");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\tbreak;");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\tdefault:");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\t\trequire_once __DIR__.\"/../Views/".$db->GetTableName().".View.php\";");
					fwrite($controllerFile, "\n");
					fwrite($controllerFile, "\tbreak;");
					fwrite($controllerFile, "\n");
				}
				fwrite($controllerFile, "}");
				fwrite($controllerFile, "\n");
				fwrite($controllerFile, "?>");
			}
			else echo "ERROR: Check your permissions to write ".$controllersPath."/".$db->GetTableName().".Controller.php";
		}
		else echo "ERROR: Check your permissions to remove ".$controllersPath."/".$db->GetTableName().".Controller.php";
	}
}
?>
