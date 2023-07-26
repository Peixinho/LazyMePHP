<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\FormsBuilder;
use \LazyMePHP\Config\Internal\APP;

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
    // Create Folder if doesn't exist
    if (!is_dir(APP::ROOT_PATH()."/".$controllersPath)) \LazyMePHP\Helper\MKDIR(APP::ROOT_PATH()."/".$controllersPath);

    $failedRouterFile = false;

    // Create Last File to Help on Requires
    if ($replaceRouteForms) {

      if (\LazyMePHP\Helper\UNLINK(APP::ROOT_PATH()."/".$controllersPath."/RouteForms.php"))
      {
        if (\LazyMePHP\Helper\TOUCH(APP::ROOT_PATH()."/".$controllersPath."/RouteForms.php"))
        {
          $routerFile = fopen(APP::ROOT_PATH()."/".$controllersPath."/RouteForms.php","w+");
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
          fwrite($routerFile, "use Pecee\SimpleRouter\SimpleRouter;");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "use \LazyMePHP\Config\Internal\APP;");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\n");
        }
        else {
          echo "ERROR: Check your permissions to write the router file on $controllersPath/RouteForms.php";
          $failedRouterFile = true;
        }
      }
      else {
        echo "ERROR: Check your permissions to remove the router file on $controllersPath/RouteForms.php";
        $failedRouterFile = true;
      }
    }

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

    APP::DB_CONNECTION()->Query($queryString, $sqlObj);
    $routes = array();
    while ($o=$sqlObj->FetchObject())
    {
      if (is_array($tablesList) && array_search($o->Table, $tablesList)>=0) {
        $db = new \LazyMePHP\DatabaseHelper\_DB_TABLE($o->Table);
        $db->GetFieldsFromDB();
        $this->ConstructForm($viewsPath, $db, $cssClass);
        $this->ConstructController($controllersPath, $classesPath, $db, $cssClass);
      }

      if ($replaceRouteForms && !$failedRouterFile) {
        $routes[] = $db->GetTableName();
        fwrite($routerFile, "/*");
        fwrite($routerFile, "\n");
        fwrite($routerFile, " * ".$db->GetTableName()." Routing");
        fwrite($routerFile, "\n");
        fwrite($routerFile, " */");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "use LazyMePHP\Forms\\".$db->GetTableName().";");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "require_once __DIR__.\"/../Controllers/".$db->GetTableName().".Controller.php\";");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "SimpleRouter::get('/".$db->GetTableName()."', function() {");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t\$data = ".$db->GetTableName()."::Default(true, \$_GET['page']??1, APP::APP_NRESULTS());");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\tglobal \$blade;");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\techo \$blade->run(\"".$db->GetTableName().".list\", ['controller' => '".$db->GetTableName()."', '".$db->GetTableName()."' => \$data['".$db->GetTableName()."']");
        foreach ($db->GetTableFields() as $field)
        {
          if ($field->GetForeignField()) {
            fwrite($routerFile, ", '".$field->GetForeignTable()."' => \$data['".$field->GetForeignTable()."']");
          }
        }
        fwrite($routerFile, ", 'filters' => \$data['filters'], 'length' => \$data['length'], 'current' => \$_GET['page']??1, 'limit' => APP::APP_NRESULTS()]);");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "});");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "SimpleRouter::get('/".$db->GetTableName()."/{".$db->GetPrimaryFieldName()."}/edit', function (\$".$db->GetPrimaryFieldName().") {");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t\$data = ".$db->GetTableName()."::Edit(\$".$db->GetPrimaryFieldName().");");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\tglobal \$blade;");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\techo \$blade->run(\"".$db->GetTableName().".edit\", ['controller' => '".$db->GetTableName()."', '".$db->GetTableName()."' => \$data['".$db->GetTableName()."']");
        foreach ($db->GetTableFields() as $field)
        {
          if ($field->GetForeignField()) {
            fwrite($routerFile, "\n");
            fwrite($routerFile, ", '".$field->GetForeignTable()."' => \$data['".$field->GetForeignTable()."']");
          }
        }
        fwrite($routerFile, "]);");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "});");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "SimpleRouter::get('/".$db->GetTableName()."/new', function () {");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t\$data = ".$db->GetTableName()."::Edit();");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\tglobal \$blade;");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\techo \$blade->run(\"".$db->GetTableName().".edit\", ['controller' => '".$db->GetTableName()."', '".$db->GetTableName()."' => \$data['".$db->GetTableName()."']");
        foreach ($db->GetTableFields() as $field)
        {
          if ($field->GetForeignField()) {
            fwrite($routerFile, "\n");
            fwrite($routerFile, ", '".$field->GetForeignTable()."' => \$data['".$field->GetForeignTable()."']");
          }
        }
        fwrite($routerFile, "]);");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "});");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "SimpleRouter::post('/".$db->GetTableName()."/{id}/save', function (\$".$db->GetPrimaryFieldName().") {");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t".$db->GetTableName()."::Save(\$".$db->GetPrimaryFieldName().");");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t\\LazyMePHP\\Helper\\redirect('/".$db->GetTableName()."?success=1');");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "});");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "SimpleRouter::post('/".$db->GetTableName()."/save', function () {");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t".$db->GetTableName()."::Save();");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t\\LazyMePHP\\Helper\\redirect('/".$db->GetTableName()."?success=1');");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "});");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "SimpleRouter::get('/".$db->GetTableName()."/{id}/delete', function (\$".$db->GetPrimaryFieldName().") {");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t".$db->GetTableName()."::Delete(\$".$db->GetPrimaryFieldName().");");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\t\\LazyMePHP\\Helper\\redirect('/".$db->GetTableName()."?success=2');");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "});");
        fwrite($routerFile, "\n");
        fwrite($routerFile, "\n");
      }
    }

    fwrite($routerFile, "\$routes = array(");
    foreach($routes as $r) fwrite($routerFile, "'$r',");
    fwrite($routerFile, ")");


    // Close Include file
    if ($replaceRouteForms && !$failedRouterFile) {
      fwrite($routerFile, "\n");
      fwrite($routerFile,"?>");
      fclose($routerFile);
    }
  }

  protected function ConstructForm($viewsPath, $db, $cssClass)
  {
    $buttonClass = $cssClass['button'];
    $inputClass = $cssClass['input'];
    $anchorClass = $cssClass['anchor'];
    $tableClass = $cssClass['table'];

    // Create Folder if doesn't exist
    if (!is_dir(APP::ROOT_PATH()."/".$viewsPath)) \LazyMePHP\Helper\MKDIR(APP::ROOT_PATH()."/".$viewsPath);

    // Create Table Folder
    if (!is_dir(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName())) \LazyMePHP\Helper\MKDIR(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName());

    if (
    \LazyMePHP\Helper\UNLINK(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/template.blade.php") &&
      \LazyMePHP\Helper\UNLINK(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/list.blade.php") &&
      \LazyMePHP\Helper\UNLINK(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/edit.blade.php")
  ) {
      if (
      \LazyMePHP\Helper\TOUCH(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/template.blade.php") &&
        \LazyMePHP\Helper\TOUCH(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/list.blade.php") &&
        \LazyMePHP\Helper\TOUCH(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/edit.blade.php")
    ) {

        $viewFile = fopen(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/template.blade.php","w+");
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
        fwrite($viewFile," ?>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "<h3>{{\$controller}}</h3>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "<script>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\tfunction Init() {}");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "</script>");
        fclose($viewFile);

        $viewFile = fopen(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/edit.blade.php","w+");
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
        fwrite($viewFile," ?>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "@include('".$db->GetTableName().".template')");
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
          } else $primaryKey = NULL;
        }
        fwrite($viewFile, "@if (\$".$db->GetTableName()."->Get".$field->GetName()."()) <form method='POST' action='/".$db->GetTableName()."/{{\$".$db->GetTableName()."->Get".$field->GetName()."()}}/save' onsubmit='return LazyMePHP.ValidateForm(this);'>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "@else <form method='POST' action='/".$db->GetTableName()."/save' onsubmit='return LazyMePHP.ValidateForm(this);'>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t@endif");
        foreach ($db->GetTableFields() as $field) {
          if (!$field->IsAutoIncrement() || !$field->IsPrimaryKey())
          {
            fwrite($viewFile, "\t<b>".$field->GetName().":</b>");
            if ($field->HasForeignKey() && !is_null($field->GetForeignField()))
            {
              fwrite($viewFile, "\n");
              fwrite($viewFile, "\t<select name='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." ".(!$field->AllowNull()?"validation='NOTNULL' validation-fail='".$field->GetName()." cannot be empty'":"")." />");
              fwrite($viewFile, "\n");
              fwrite($viewFile, "\t\t<option value=''>-</option>");
              fwrite($viewFile, "\n");
              fwrite($viewFile, "\t\t@foreach(\$".$field->GetForeignTable()."->GetList(false) as \$v)");
              fwrite($viewFile, "\n");
              fwrite($viewFile, "\t\t<option value='{{\$v->Get".$field->GetForeignField()."()}}' {{\$".$db->GetTableName()."->Get".$field->GetName()."()==\$v->Get".$field->GetForeignField()."()?\"selected\":\"\"}}>{{\$v->GetDescriptor()}}</option>");
              fwrite($viewFile, "\n");
              fwrite($viewFile, "\t\t@endforeach");
              fwrite($viewFile, "\n");
              fwrite($viewFile, "\t</select>");
              fwrite($viewFile, "\n");
            } else {
              fwrite($viewFile, "\n");
              switch ($field->GetDataType())
              {
                case "bit":
                  fwrite($viewFile, "\t<input type='checkbox' name='".$field->GetName()."' id='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." value='1' {{\$".$db->GetTableName()."->Get".$field->GetName()."()==1?\"checked\":\"\"}} />");
                  break;
                case "date":
                  fwrite($viewFile, "\t<input type='date' name='".$field->GetName()."' id='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." value='{{\$".$db->GetTableName()."->Get".$field->GetName()."()}}' ".(!$field->AllowNull()?"validation='NOTNULL' validation-fail='".$field->GetName()." cannot be empty'":"")." ".($field->GetDataLength()?"maxlength='".$field->GetDataLength()."'":"")." />");
                  break;
                case "float":
                case "int":
                  fwrite($viewFile, "\t<input type='number' name='".$field->GetName()."' id='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." value='{{\$".$db->GetTableName()."->Get".$field->GetName()."()}}' ".(!$field->AllowNull()?"validation='NOTNULL' validation-fail='".$field->GetName()." cannot be empty'":"")." ".($field->GetDataLength()?"maxlength='".$field->GetDataLength()."'":"")." />");
                  break;
                case "varchar":
                  fwrite($viewFile, "\t<input type='text' name='".$field->GetName()."' id='".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." value='{{\$".$db->GetTableName()."->Get".$field->GetName()."()}}' ".(!$field->AllowNull()?"validation='NOTNULL' validation-fail='".$field->GetName()." cannot be empty'":"")." ".($field->GetDataLength()?"maxlength='".$field->GetDataLength()."'":"")." />");
                  break;
              }
              fwrite($viewFile, "\n");
            }
          }
          fwrite($viewFile, "\t<br/>");
          fwrite($viewFile, "\n");
        }
        fwrite($viewFile, "\t<br/>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t<input type='submit' name='submit' ".($buttonClass?"class='".$buttonClass."'":"")." value='Save' />");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t<input type='button' name='cancel' ".($buttonClass?"class='".$buttonClass."'":"")." value='Cancel' onclick='window.open(\"/".$db->GetTableName()."\",\"_self\");' />");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "</form>");
        fclose($viewFile);

        // Show List
        $viewFile = fopen(APP::ROOT_PATH()."/".$viewsPath."/".$db->GetTableName()."/list.blade.php","w+");
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
        fwrite($viewFile," ?>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "@include('".$db->GetTableName().".template')");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "<a ".($anchorClass?"class='".$anchorClass."'":"")." href='/".$db->GetTableName()."/new'>Add New</a>");
        fwrite($viewFile, "\t<br/>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\n");

        $haveForeignMembers = false;
        foreach ($db->GetTableFields() as $field)
        {
          if ($field->HasForeignKey() && !is_null($field->GetForeignField()))
          {
            // Write form for filter
            if (!$haveForeignMembers)
            {
              fwrite($viewFile, "<form method='GET' action='/{{\$controller}}'>");
              fwrite($viewFile, "\n");
            }
            fwrite($viewFile, "\t<br/>");
            fwrite($viewFile, "\n");
            fwrite($viewFile, "\t<b>".$field->GetName().":</b>");
            fwrite($viewFile, "\n");
            fwrite($viewFile, "\t<select name='FindBy".$field->GetName()."' ".($inputClass?"class='".$inputClass."'":"")." />");
            fwrite($viewFile, "\n");
            fwrite($viewFile, "\t\t<option value=''>-</option>");
            fwrite($viewFile, "\n");
            fwrite($viewFile, "\t\t@foreach(\$".$field->GetForeignTable()."->GetList(false) as \$v)");
            fwrite($viewFile, "\n");
            fwrite($viewFile, "\t\t<option value='{{\$v->Get".$field->GetForeignField()."()}}' {{isset(\$filters['FindBy".$field->GetName()."']) && \$filters['FindBy".$field->GetName()."']==\$v->Get".$field->GetForeignField()."()?\"selected\":\"\"}}>{{\$v->GetDescriptor()}}</option>");
            fwrite($viewFile, "\n");
            fwrite($viewFile, "\t\t@endforeach");
            fwrite($viewFile, "\n");
            fwrite($viewFile, "\t</select>");
            fwrite($viewFile, "\n");

            $haveForeignMembers = true;
          }
        }

        if ($haveForeignMembers)
        {
          fwrite($viewFile, "\t<br/>");
          fwrite($viewFile, "\n");
          fwrite($viewFile, "\t<input type='submit' ".($buttonClass?"class='".$buttonClass."'":"")." />");
          fwrite($viewFile, "\n");
          fwrite($viewFile, "</form>");
          fwrite($viewFile, "\n");
          fwrite($viewFile, "<br/>");
        }

        fwrite($viewFile, "<table ".($tableClass?"class='".$tableClass."'":"").">");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t<tr>");
        fwrite($viewFile, "\n");
        if ($primaryKey) {
          fwrite($viewFile, "\t\t<th><b>edit</b></th>");
          fwrite($viewFile, "\n");
          fwrite($viewFile, "\t\t<th><b>delete</b></th>");
        }
        fwrite($viewFile, "\n");
        foreach ($db->GetTableFields() as $field) {
          fwrite($viewFile, "\t\t<th><b>".$field->GetName()."</b></th>");
          fwrite($viewFile, "\n");
        }
        fwrite($viewFile, "\t</tr>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t@foreach(\$".$db->GetTableName()."->GetList() as \$member)");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t<tr>");
        fwrite($viewFile, "\n");
        if ($primaryKey) fwrite($viewFile, "\t\t<td><a ".($anchorClass?"class='".$anchorClass."'":"")." href='/".$db->GetTableName()."\{{\$member->Get".$primaryKey->GetName()."()}}/edit'>edit</a></td>\n\t\t<td><a ".($anchorClass?"class='".$anchorClass."'":"")." href='/".$db->GetTableName()."\{{\$member->Get".$primaryKey->GetName()."()}}/delete'>delete</a></td>");
        fwrite($viewFile, "\n");
        foreach ($db->GetTableFields() as $field) {
          if ($primaryKey) { 
            if ($field->HasForeignKey()) 
            fwrite($viewFile, "\t\t<td>{{\$member->Get".$field->GetName()."Object()->GetDescriptor()}}</td>");
            else
            fwrite($viewFile, "\t\t<td>{{\$member->Get".$field->GetName()."()}}</td>");

            fwrite($viewFile, "\n");
          }
        }
        fwrite($viewFile, "\t</tr>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t@endforeach");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "</table>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "@component('_Components.Pagination',array('total'=> \$length, 'current' => \$current, 'limit' => \$limit))");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "\t<strong>Failed to load pagination component!</strong>");
        fwrite($viewFile, "\n");
        fwrite($viewFile, "@endcomponent");
        fwrite($viewFile, "\n");
      }
      else echo "ERROR: Check your permissions to write ".$viewsPath."/".$db->GetTableName().".View.php";
    }
    else echo "ERROR: Check your permissions to remove ".$viewsPath."/".$db->GetTableName().".View.php";
  }

  function ConstructController($controllersPath, $classesPath, $db, $cssClass)
  {

    $buttonClass = $cssClass['button'];
    $inputClass = $cssClass['input'];
    $anchorClass = $cssClass['anchor'];
    $tableClass = $cssClass['table'];

    // Create Folder if doesn't exist
    if (!is_dir(APP::ROOT_PATH()."/".$controllersPath)) \LazyMePHP\Helper\MKDIR(APP::ROOT_PATH()."/".$controllersPath);

    if (\LazyMePHP\Helper\UNLINK(APP::ROOT_PATH()."/".$controllersPath."/".$db->GetTableName().".Controller.php"))
    {
      if (\LazyMePHP\Helper\TOUCH(APP::ROOT_PATH()."/".$controllersPath."/".$db->GetTableName().".Controller.php"))
      {
        $controllerFile = fopen(APP::ROOT_PATH()."/".$controllersPath."/".$db->GetTableName().".Controller.php","w+");
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
        fwrite($controllerFile, "use \\LazyMePHP\Config\Internal\APP;\n");
        fwrite($controllerFile, "use \\eftec\bladeone\BladeOne;\n");
        fwrite($controllerFile, "\$views = __DIR__ . '/../Views/';");
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "\$cache = __DIR__ . '/../Views/compiled/';");
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "\$blade = new BladeOne(\$views,\$cache);");
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "require_once __DIR__.\"/../Configurations/Configurations.php\";");
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "require_once __DIR__.\"/../Classes/includes.php\";");
        fwrite($controllerFile, "\n");

        // reload page with _get params from filter post
        fwrite($controllerFile, "if (\array_key_exists(\"filter\",\$_POST)) {\n");
        fwrite($controllerFile, "\t\$url=\"\";\n");
        fwrite($controllerFile, "\tforeach(\$_POST as \$k=>\$g) if (\$g) \$url.=(strlen(\$url)>0?\"&\":\"?\").\"\$k=\$g\";\n");
        fwrite($controllerFile, "\theader(\"location: \".\$url);\n");
        fwrite($controllerFile, "}\n");

        // Get Primary Key
        $primaryKey = NULL;
        foreach($db->GetTableFields() as $field)
        {
          if ($field->IsPrimaryKey())
          {
            $primaryKey = $field;
            break;
          } else $primaryKey = NULL;
        }
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "class ".$db->GetTableName()." {");
        fwrite($controllerFile, "\n");

        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "\tstatic function Edit(\$".$field->GetName()." = null) {");
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "\t\tif (isset(\$".$field->GetName().")) \$".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$".$field->GetName().");");
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "\t\telse \$".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."();");
        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "\n");

        $foreignTables = "";
        foreach ($db->GetTableFields() as $field)
        {
          if ($field->GetForeignField()) {
            fwrite($controllerFile, "\t\t\$".$field->GetForeignTable()." = new \LazyMePHP\Classes\\".$field->GetForeignTable()."_List();");
            fwrite($controllerFile, "\n");
            fwrite($controllerFile, "\t\t\$".$field->GetForeignTable()."->FindAll();");
            fwrite($controllerFile, "\n");
            $foreignTables .= (strlen($foreignTables)>0?",":"")."'".$field->GetForeignTable()."' => \$".$field->GetForeignTable()."->GetList(false)";
            fwrite($controllerFile, "\n");
          }
        }
        fwrite($controllerFile, "\t\treturn ['".$db->GetTableName()."' => \$".$db->GetTableName());
        foreach ($db->GetTableFields() as $field)
        {
          if ($field->GetForeignField()) {
            fwrite($controllerFile, ", '".$field->GetForeignTable()."' => \$".$field->GetForeignTable());
          }
        }
        fwrite($controllerFile, "];");

        fwrite($controllerFile, "\n");
        fwrite($controllerFile, "\t}");
        fwrite($controllerFile, "\n");

        if ($primaryKey)
        {
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\tstatic function Save(\$".$primaryKey->GetName()." = null) {");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\$obj = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$".$primaryKey->GetName().");");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\$fieldsNull = NULL;");
          fwrite($controllerFile, "\n");
          foreach($db->GetTableFields() as $field)
          {
            if (!$field->IsPrimaryKey())
            {
              if (!$field->AllowNull())
              {
                fwrite($controllerFile, "\t\tif (filter_input(INPUT_POST, \"".$field->GetName()."\")===false) \$fieldsNull .= (!is_null(\$fieldsNull)?\",\":\"\").\"".$field->GetName()."\";");
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
          fwrite($controllerFile, "\t}");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\tstatic function Delete(\$".$primaryKey->GetName()." = null) {");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\tif (\$".$primaryKey->GetName().") {");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\t\$obj = new \LazyMePHP\Classes\\".$db->GetTableName()."(\$".$primaryKey->GetName().");");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\t\$obj->Delete();");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t}");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t}");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\tstatic function Default(\$foreignTables = true, \$page = null, \$limit = null) {");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\$".$db->GetTableName()." = new \LazyMePHP\Classes\\".$db->GetTableName()."_List();");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\$filters = array();");
          
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\$allowedMethods = array(");
          // Check if there are filters
          $c = 0;
          foreach ($db->GetTableFields() as $field)
          {
            if ($field->GetForeignField()) {
              if ($field->HasForeignKey() && !is_null($field->GetForeignField()))
              {
                fwrite($controllerFile, ($c++>0?", ":"")."'FindBy".$field->GetName()."'");
              }
            }
          }
          fwrite($controllerFile, ");");
          fwrite($controllerFile, "\n");

          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\tif (count(\$_GET)>0) {");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\tforeach(\$_GET as \$method => \$val)");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\t{");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\t\tif (\$method && \$val && is_int(array_search(\$method, \$allowedMethods)) && method_exists(\$".$db->GetTableName().", \$method)) {");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\t\t\tcall_user_func_array(array(\$".$db->GetTableName().",\$method), explode(',', \$val));");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\t\t\t\$filters[\$method] = \$val;");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\t\t}");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\t}");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t}");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\telse \$".$db->GetTableName()."->FindAll();");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\$length = \$".$db->GetTableName()."->GetCount();");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\tif (\$page && \$limit) \$".$db->GetTableName()."->Limit(\$limit,(\$page-1)*\$limit);");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\tif (\$foreignTables) {");
          $foreignTables = "";
          foreach ($db->GetTableFields() as $field)
          {
            if ($field->GetForeignField()) {
              fwrite($controllerFile, "\n");
              fwrite($controllerFile, "\t\t\t\$".$field->GetForeignTable()." = new \LazyMePHP\Classes\\".$field->GetForeignTable()."_List();");
              fwrite($controllerFile, "\n");
              fwrite($controllerFile, "\t\t\t\$".$field->GetForeignTable()."->FindAll();");
              fwrite($controllerFile, "\n");
              $foreignTables .= (strlen($foreignTables)>0?",":"")."'".$field->GetForeignTable()."' => \$".$field->GetForeignTable()."->GetList(false)";
            }
          }
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\treturn ['".$db->GetTableName()."' => \$".$db->GetTableName().", 'length' => \$length");
          foreach ($db->GetTableFields() as $field)
          {
            if ($field->GetForeignField()) {
              fwrite($controllerFile, ", '".$field->GetForeignTable()."' => \$".$field->GetForeignTable());
            }
          }
          fwrite($controllerFile, ", 'filters' => \$filters];");

          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t} else {");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t\treturn ['".$db->GetTableName()."' => \$".$db->GetTableName().", 'filters' => \$filters];");

          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t\t}");
          fwrite($controllerFile, "\n");
          fwrite($controllerFile, "\t}");
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
