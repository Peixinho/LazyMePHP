<?php

namespace Tools\Forms;
use Core\LazyMePHP;

require_once __DIR__ . '/../Database';
require_once __DIR__ . '/../Helper';

class BuildControllers {
    public function __construct($controllersPath, $classesPath, $db) {
        $this->constructController($controllersPath, $classesPath, $db);
    }

    public function constructController($controllersPath, $classesPath, $db) {
        // Create Folder if doesn't exist
        if (!is_dir($controllersPath)) \Tools\Helper\MKDIR($controllersPath);

        if (\Tools\Helper\UNLINK($controllersPath."/".$db->GetTableName().".php"))
        {
            if (\Tools\Helper\TOUCH($controllersPath."/".$db->GetTableName().".php"))
            {
                $controllerFile = fopen($controllersPath."/".$db->GetTableName().".php","w+");
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
                fwrite($controllerFile, "declare(strict_types=1);");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "namespace Controllers;");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "use Core\\Validations\\ValidationsMethod;");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "use Core\\Http\\Request;");

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
                  } else $primaryKey = NULL;
                }
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "class ".$db->GetTableName()." {");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\tprotected Request \$request;");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\tpublic function __construct(Request \$request) {");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\t\t\$this->request = \$request;");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\t\tif (\$this->request->post('filter')) {");
                fwrite($controllerFile, "\t\t\$url = '';");
                fwrite($controllerFile, "\t\tforeach (\$this->request->post() as \$k => \$g) if (\$g) \$url .= (!empty(\$url) ? '&' : '?') . \$k . '=' . \$g;");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\t\theader('location: ' . \$url);");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\t}");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\t}");

                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\tfunction edit(?int \$".$field->GetName()." = null) : array {");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\t\tif (isset(\$".$field->GetName().")) \$".$db->GetTableName()." = new \Models\\".$db->GetTableName()."(\$".$field->GetName().");");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\t\telse \$".$db->GetTableName()." = new \Models\\".$db->GetTableName()."();");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "\n");

                $foreignTables = "";
                foreach ($db->GetTableFields() as $field)
                {
                  if ($field->GetForeignField()) {
                    fwrite($controllerFile, "\t\t\$".$field->GetForeignTable()." = new \Models\\".$field->GetForeignTable()."_List();");
                    fwrite($controllerFile, "\n");
                    fwrite($controllerFile, "\t\t\$".$field->GetForeignTable()."->FindAll();");
                    fwrite($controllerFile, "\n");
                    $foreignTables .= (!empty($foreignTables)?",":"")."'".$field->GetForeignTable()."' => \$".$field->GetForeignTable()."->GetList()";
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
                  fwrite($controllerFile, "\tfunction save(?int \$".$primaryKey->GetName()." = null, \$api = false) : mixed {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\$obj = new \\Models\\".$db->GetTableName()."(\$".$primaryKey->GetName().");");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\$validationRules = [");
                  fwrite($controllerFile, "\n");
                  foreach($db->GetTableFields() as $field)
                  {
                    if (!$field->IsPrimaryKey())
                    {
                      fwrite($controllerFile, "\t\t\t'".$field->GetName()."' => [");
                      fwrite($controllerFile, "\n");
                      fwrite($controllerFile, "\t\t\t\t'validations' => [");

                      // Define validations
                      switch($field->GetDataType()) {
                        case "bool":
                        case "bit":
                          fwrite($controllerFile, "ValidationsMethod::BOOLEAN");
                          break;
                        case "int":
                          fwrite($controllerFile, "ValidationsMethod::INT");
                          break;
                        case "float":
                          fwrite($controllerFile, "ValidationsMethod::FLOAT");
                          break;
                        case "date":
                        case "string":
                        default:
                          fwrite($controllerFile, "ValidationsMethod::STRING");
                          break;
                      }
                      if (!$field->AllowNull() && $field->GetDataType() != "bool" && $field->GetDataType() != "bit") fwrite($controllerFile, ",ValidationsMethod::NOTNULL");
                      fwrite($controllerFile, "],");
                      fwrite($controllerFile, "\n");
                      fwrite($controllerFile, "\t\t\t\t'required' => '".(!$field->AllowNull() && $field->GetDataType() != "bool" && $field->GetDataType() != "bit")."',");
                      fwrite($controllerFile, "\n");
                      fwrite($controllerFile, "\t\t\t\t'type' => ");
                      switch($field->GetDataType()) {
                        case "bool":
                        case "bit":
                          fwrite($controllerFile, "'bool'");
                          break;
                        case "int":
                          fwrite($controllerFile, "'int'");
                          break;
                        case "float":
                          fwrite($controllerFile, "'float'");
                          break;
                        case "date":
                        case "string":
                        default:
                          fwrite($controllerFile, "'string'");
                          break;
                      }
                      fwrite($controllerFile, "\n");
                      fwrite($controllerFile, "\t\t\t],");
                      fwrite($controllerFile, "\n");
                    }
                  }
                  fwrite($controllerFile, "\t\t];");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\tif (!\$api)");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\$validatedData = \\Core\\Validations\\Validations::ValidateFormData(\$validationRules);");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\telse");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\$validatedData = \\Core\\Validations\\Validations::ValidateJsonData(\$this->request->json(), \$validationRules);");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\tif (\$validatedData['success']) {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\tforeach (\$validatedData['validated_data'] as \$field => \$value) {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t\$setter = 'Set' . ucfirst(\$field);");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t\$obj->\$setter(\$value);");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\$obj->Save();");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t// Show success notification");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\tif (!\$api) {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\tif (\$".$primaryKey->GetName().") {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t\t\\Messages\\Messages::RecordUpdated('".$db->GetTableName()."');");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t} else {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t\t\\Messages\\Messages::RecordCreated('".$db->GetTableName()."');");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\treturn \$obj;");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t// Show validation error notification");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\tif (!\$api) {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\\Messages\\Messages::ValidationErrors(\$validatedData['errors'], 'Validation failed for ".$db->GetTableName()."');");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\treturn false;");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\treturn false;");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t}");
                  fwrite($controllerFile, "\n");

                  fwrite($controllerFile, "\tfunction delete(?int \$".$primaryKey->GetName()." = null) : void {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\tif (\$".$primaryKey->GetName().") {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\$obj = new \Models\\".$db->GetTableName()."(\$".$primaryKey->GetName().");");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\$obj->Delete();");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t// Show success notification");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\\Messages\\Messages::RecordDeleted('".$db->GetTableName()."');");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\tfunction index(?int \$page = null, ?int \$limit = null) : array {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\$".$db->GetTableName()." = new \Models\\".$db->GetTableName()."_List();");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\$filters = array();");

                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\$allowedMethods = array(");
                  // Check if there are filters
                  $c = 0;
                  foreach ($db->GetTableFields() as $field)
                  {
                      fwrite($controllerFile, ($c++>0?", ":"")."'FindBy".$field->GetName()."'");
                      fwrite($controllerFile, ($c++>0?", ":"")."'OrderBy".$field->GetName()."'");
                  }
                  fwrite($controllerFile, ");");
                  fwrite($controllerFile, "\n");

                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\$get = \$this->request->get();");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\tif (count(\$get)>0) {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\tforeach(\$get as \$method => \$val)");
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
                  fwrite($controllerFile, "\t\tif (\$page && \$limit>0) \$".$db->GetTableName()."->Limit(\$limit,(\$page-1)*\$limit);");
                  fwrite($controllerFile, "\n");
                  
                  // Load foreign key objects for dropdowns
                  $foreignTables = "";
                  foreach ($db->GetTableFields() as $field)
                  {
                    if ($field->GetForeignField()) {
                      fwrite($controllerFile, "\n");
                      fwrite($controllerFile, "\t\t\$".$field->GetForeignTable()." = new \Models\\".$field->GetForeignTable()."_List();");
                      fwrite($controllerFile, "\n");
                      fwrite($controllerFile, "\t\t\$".$field->GetForeignTable()."->FindAll();");
                      fwrite($controllerFile, "\n");
                      $foreignTables .= (!empty($foreignTables)?",":"")."'".$field->GetForeignTable()."' => \$".$field->GetForeignTable();
                    }
                  }
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\treturn ['".$db->GetTableName()."' => \$".$db->GetTableName().", 'length' => \$length");
                  foreach ($db->GetTableFields() as $field)
                  {
                    if ($field->GetForeignField()) {
                      fwrite($controllerFile, ", '".$field->GetForeignTable()."' => \$".$field->GetForeignTable());
                    }
                  }
                  fwrite($controllerFile, ", 'filters' => \$filters];");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t}");
                  fwrite($controllerFile, "\n");
                }
                fwrite($controllerFile, "}");
                fwrite($controllerFile, "\n");
                fwrite($controllerFile, "?>");
            }
            else echo "ERROR: Check your permissions to write ".$controllersPath."/".$db->GetTableName().".php\n";
        }
        else echo "ERROR: Check your permissions to remove ".$controllersPath."/".$db->GetTableName().".php\n";
    }
} 