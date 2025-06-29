<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Tools\API; // TODO: Should ideally be LazyMePHP\ApiBuilder
use Core\LazyMePHP;

// Include helper functions
require_once(__DIR__.'/../Helper');

// Include database classes
require_once(__DIR__.'/../Database');

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
  function __construct($apiPath, $replaceRouteApi, $tablesList)
  {
    // SELECT Tables
    $queryString = "";
    switch (LazyMePHP::DB_TYPE())
    {
      case 'mssql': // MSSQL
        $queryString = "SELECT [Table] FROM (SELECT TABLE_NAME as [Table] FROM INFORMATION_SCHEMA.TABLES) SCH WHERE [Table] NOT LIKE '\_\_%'";
        break;
      case 'mysql': // MYSQL
        $queryString = "SELECT `Table` FROM (SELECT DISTINCT TABLE_NAME as `Table` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".LazyMePHP::DB_NAME()."') SCH WHERE `Table` NOT LIKE '\_\_%'";
        break;
      case 'sqlite': // SQLITE
        $queryString = "SELECT name as `Table` FROM sqlite_master WHERE type='table' and name not like '#__%' ESCAPE '#' ORDER BY name";
        break;
    }

    // Create Folder if doesn't exist
    if (!is_dir($apiPath)) \Tools\Helper\MKDIR($apiPath);

    // Create API MASK
    if ($replaceRouteApi) {
      if (\Tools\Helper\UNLINK($apiPath."/../ApiFieldMask.php"))
      {
        if (\Tools\Helper\TOUCH($apiPath."/../ApiFieldMask.php"))
        {
          $routerFile = fopen($apiPath."/../ApiFieldMask.php", "w+");
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
          fwrite($routerFile, "declare(strict_types=1);");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "namespace App;");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "class ApiFieldMask {");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\tprivate static array \$fields = [");
          fwrite($routerFile, "\n");
          $sqlObj = LazyMePHP::DB_CONNECTION()->Query($queryString);
          $_i = 0;
          while ($o=$sqlObj->FetchObject())
          {
            if (is_array($tablesList) && array_search($o->Table, $tablesList)!==false) {
              if ($_i>0) fwrite($routerFile, ",\n");
              fwrite($routerFile, "\t\t\"".$o->Table."\" => [");
              $db = new \Tools\Database\_DB_TABLE($o->Table);
              $db->GetFieldsFromDB();
              if ($db->IsView()) continue;
              foreach($db->GetTableFields() as $_k => $field)
              {
                if ($_k>0) fwrite($routerFile, ",");
                fwrite($routerFile, "\"".$field->GetName()."\"");
              }
              fwrite($routerFile, "]");
              $_i++;
            }
          }
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\t];");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\tpublic static function get(string \$entity): array {");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\t\treturn self::\$fields[\$entity] ?? [];");
          fwrite($routerFile, "\n");  
          fwrite($routerFile, "\t}");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\tpublic static function apply(string \$entity, array \$data): array {");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\t\t\$allowed = self::get(\$entity);");
          fwrite($routerFile, "\n");  
          fwrite($routerFile, "\t\treturn array_intersect_key(\$data, array_flip(\$allowed));");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "\t}");
          fwrite($routerFile, "\n");
          fwrite($routerFile, "}");
        }
      }
    }
    // Create Routing Rules
    if ($replaceRouteApi) {

      // Generate Default Visible Fields
      $sqlObj = LazyMePHP::DB_CONNECTION()->Query($queryString);
      while ($o=$sqlObj->FetchObject())
      {
        $db = new \Tools\Database\_DB_TABLE($o->Table);
        $db->GetFieldsFromDB();
        if ($db->IsView()) continue;
        $targetFile = $apiPath."/".$o->Table.".php";
        // echo "[GENERATOR] Attempting to create API file for table: {$o->Table} at {$targetFile}\n";
        if (\Tools\Helper\UNLINK($targetFile))
        {
          if (\Tools\Helper\TOUCH($targetFile))
          {
            // echo "[GENERATOR] Created file: {$targetFile}\n";
            $routerFile = fopen($targetFile,"w+");
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
            fwrite($routerFile, "declare(strict_types=1);");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "namespace App\Api;");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "use Controllers\\".$o->Table.";");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "use App\ApiFieldMask;");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "use Pecee\SimpleRouter\SimpleRouter;");
            fwrite($routerFile, "\n");
            fwrite($routerFile, "use Core\Http\Request;");
            fwrite($routerFile, "\n");

            #fwrite($routerFile, "Router::Create(\"controller\", \"".$o->Table."\",$apiPath/".$o->Table.".API.php\");");
            if (is_array($tablesList) && array_search($o->Table, $tablesList)!==false) {
              fwrite($routerFile, "/*");
              fwrite($routerFile, "\n");
              fwrite($routerFile, " * ".$o->Table." Routing");
              fwrite($routerFile, "\n");
              fwrite($routerFile, " */");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "SimpleRouter::get('/api/".$o->Table."', function() {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$request = new Request();");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$controller = new ".$o->Table."(\$request);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\ttry {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$data = \$controller->index((int)(\$request->get('page') ?? 1), (int)(\$request->get('itemsPerPage') ?? \Core\LazyMePHP::NRESULTS()));");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t// Apply mask to each item in the list");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$list = \$data['".$o->Table."']->GetList();");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$maskedList = array_map(function(\$item) {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\treturn ApiFieldMask::apply('".$o->Table."', \$item->Serialize());");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t}, \$list);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => true,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'data' => \$maskedList,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'length' => \$data['length'],");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'filters' => \$data['filters']");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t} catch (\Throwable \$e) {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\thttp_response_code(500);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => false,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'error' => \$e->getMessage(),");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'code' => 500");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t}");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "});");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "SimpleRouter::get('/api/".$o->Table."/{".$db->GetPrimaryFieldName()."}', function (\$".$db->GetPrimaryFieldName().") {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$request = new Request();");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$controller = new ".$o->Table."(\$request);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\ttry {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$data = \$controller->edit((int)\$".$db->GetPrimaryFieldName().");");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$masked = ApiFieldMask::apply('".$o->Table."', \$data['".$o->Table."']->Serialize());");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => true,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'data' => \$masked");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t} catch (\Throwable \$e) {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\thttp_response_code(404);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => false,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'error' => \$e->getMessage(),");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'code' => 404");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t}");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "});");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "SimpleRouter::post('/api/".$o->Table."/{".$db->GetPrimaryFieldName()."}', function (\$".$db->GetPrimaryFieldName().") {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$request = new Request();");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$controller = new ".$o->Table."(\$request);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\ttry {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$data = \$controller->save((int)\$".$db->GetPrimaryFieldName().", true);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$masked = \$data ? ApiFieldMask::apply('".$o->Table."', \$data->Serialize()) : null;");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => (bool)\$data,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'data' => \$masked");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t} catch (\Throwable \$e) {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\thttp_response_code(400);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => false,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'error' => \$e->getMessage(),");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'code' => 400");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t}");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "});");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "SimpleRouter::post('/api/".$o->Table."', function () {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$request = new Request();");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$controller = new ".$o->Table."(\$request);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\ttry {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$data = \$controller->save(null, true);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$masked = \$data ? ApiFieldMask::apply('".$o->Table."', \$data->Serialize()) : null;");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => (bool)\$data,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'data' => \$masked");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t} catch (\Throwable \$e) {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\thttp_response_code(400);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => false,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'error' => \$e->getMessage(),");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'code' => 400");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t}");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "});");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "SimpleRouter::delete('/api/".$o->Table."/{".$db->GetPrimaryFieldName()."}', function (\$".$db->GetPrimaryFieldName().") {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$request = new Request();");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\$controller = new ".$o->Table."(\$request);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\ttry {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\$controller->delete((int)\$".$db->GetPrimaryFieldName().");");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => true");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t} catch (\Throwable \$e) {");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\thttp_response_code(400);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\techo json_encode([");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'success' => false,");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'error' => \$e->getMessage(),");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t\t'code' => 400");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t\t]);");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "\t}");
              fwrite($routerFile, "\n");
              fwrite($routerFile, "});");
            }
          }
        }
      }
    }
  }

  /**
   * Helper function to get validation type and method based on database field type
   */
  private function getValidationTypeAndMethod($field) {
    $dataType = $field->GetDataType();
    switch($dataType) {
      case 'int':
        return [
          'type' => 'int',
          'validation' => 'ValidationsMethod::INT',
          'message' => 'must be a valid integer'
        ];
      case 'float':
        return [
          'type' => 'float', 
          'validation' => 'ValidationsMethod::FLOAT',
          'message' => 'must be a valid number'
        ];
      case 'bool':
        return [
          'type' => 'bool',
          'validation' => 'ValidationsMethod::BOOLEAN', 
          'message' => 'must be a valid boolean'
        ];
      case 'string':
      default:
        return [
          'type' => 'string',
          'validation' => 'ValidationsMethod::STRING',
          'message' => 'must be a valid string'
        ];
    }
  }
}
?>
