<?php

namespace Tools\Forms;
use Core\LazyMePHP;

require_once __DIR__ . '/../Database';
require_once __DIR__ . '/../Helper';

class BuildControllers {
    public function __construct($controllersPath, $classesPath, $db) {
        $this->constructController($controllersPath, $classesPath, $db);
        $this->constructValidationService($controllersPath, $db);
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
                fwrite($controllerFile, "use App\\Services\\".$db->GetTableName()."ValidationService;");
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
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t// Use validation service");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\tif (!\$api) {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t// Use partial validation for updates, full validation for creates");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\tif (\$".$primaryKey->GetName().") {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t\$validatedData = ".$db->GetTableName()."ValidationService::validateUpdate(\$this->request->post(), \$".$primaryKey->GetName().");");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t} else {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t\$validatedData = ".$db->GetTableName()."ValidationService::validateFormData(\$this->request->post());");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t} else {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t// Use partial validation for updates, full validation for creates");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\tif (\$".$primaryKey->GetName().") {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t\$validatedData = ".$db->GetTableName()."ValidationService::validateUpdate(\$this->request->json(), \$".$primaryKey->GetName().");");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t} else {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\t\$validatedData = ".$db->GetTableName()."ValidationService::validateJsonData(\$this->request->json());");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t}");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t}");
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
                  fwrite($controllerFile, "\t\t\t\\Messages\\Messages::ValidationErrors(\$validatedData['errors'], ['type' => '".$db->GetTableName()."']);");
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
                  fwrite($controllerFile, "\t\t} else {");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t\t\$".$db->GetTableName()."->FindAll();");
                  fwrite($controllerFile, "\n");
                  fwrite($controllerFile, "\t\t}");
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

    protected function constructValidationService($controllersPath, $db)
    {
        // Create Services Folder if doesn't exist
        $servicesPath = dirname($controllersPath) . '/Services';
        if (!is_dir($servicesPath)) \Tools\Helper\MKDIR($servicesPath);

        if (\Tools\Helper\UNLINK($servicesPath."/".$db->GetTableName()."ValidationService.php")) {
            if (\Tools\Helper\TOUCH($servicesPath."/".$db->GetTableName()."ValidationService.php")) {
                $serviceFile = fopen($servicesPath."/".$db->GetTableName()."ValidationService.php","w+");
                
                fwrite($serviceFile,"<?php");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile,"/**");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile," * @author Duarte Peixinho");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile," *");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile," * Source File Generated Automatically");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile," */");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile,"declare(strict_types=1);");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile,"namespace App\\Services;");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile,"use Core\\Validations\\ValidationsMethod;");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile,"use Core\\Validations\\Validations;");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile,"class ".$db->GetTableName()."ValidationService");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile,"{");
                fwrite($serviceFile, "\n");
                
                // Generate validation rules
                fwrite($serviceFile, "\t/**");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * Get validation rules for ".$db->GetTableName());
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t *");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @return array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t */");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\tpublic static function getValidationRules(): array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t{");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\treturn [");
                fwrite($serviceFile, "\n");
                
                foreach($db->GetTableFields() as $field)
                {
                  fwrite($serviceFile, "\t\t\t'".$field->GetName()."' => [\n");
                  fwrite($serviceFile, "\t\t\t\t'validations' => [\n");

                  // Define validations with improved logic
                  $validations = [];
                  
                  // Add type-specific validations
                  switch($field->GetDataType()) {
                    case "bool":
                    case "bit":
                      $validations[] = "ValidationsMethod::BOOLEAN";
                      break;
                    case "int":
                      $validations[] = "ValidationsMethod::INT";
                      break;
                    case "float":
                      $validations[] = "ValidationsMethod::FLOAT";
                      break;
                    case "date":
                      $validations[] = "ValidationsMethod::DATE";
                      break;
                    case "datetime":
                    case "timestamp":
                      $validations[] = "ValidationsMethod::DATETIME";
                      break;
                    case "string":
                    default:
                      // Check if field name suggests email
                      if (stripos($field->GetName(), 'email') !== false) {
                        $validations[] = "ValidationsMethod::EMAIL";
                      }
                      // Check if field name suggests phone number
                      elseif (stripos($field->GetName(), 'phone') !== false || stripos($field->GetName(), 'telefone') !== false || stripos($field->GetName(), 'tel') !== false) {
                        $validations[] = "ValidationsMethod::STRING";
                        // Add max_length param for STRING validation immediately after STRING
                        if ($field->GetDataLength() > 0) {
                          $validations[] = "['max_length' => " . $field->GetDataLength() . "]";
                        }
                        $validations[] = "ValidationsMethod::REGEXP";
                        $validations[] = "/^[+]?[0-9\\s\\-\\(\\)]+$/";
                      }
                      // Check if field name suggests URL
                      elseif (stripos($field->GetName(), 'url') !== false || stripos($field->GetName(), 'link') !== false || stripos($field->GetName(), 'website') !== false) {
                        $validations[] = "ValidationsMethod::STRING";
                        // Add max_length param for STRING validation immediately after STRING
                        if ($field->GetDataLength() > 0) {
                          $validations[] = "['max_length' => " . $field->GetDataLength() . "]";
                        }
                        $validations[] = "ValidationsMethod::REGEXP";
                        $validations[] = "/^https?:\\/\\/.+/";
                      }
                      else {
                        $validations[] = "ValidationsMethod::STRING";
                        // Add max_length param for STRING validation
                        if ($field->GetDataLength() > 0) {
                          $validations[] = "['max_length' => " . $field->GetDataLength() . "]";
                        }
                      }
                      break;
                  }
                  
                  // When outputting validations, wrap strings in single quotes
                  $validations_quoted = array_map(function($v) {
                    if (is_string($v) && !str_starts_with($v, 'ValidationsMethod::')) {
                      // Don't quote array-like strings (they should be output as-is)
                      if (str_starts_with($v, '[') && str_ends_with($v, ']')) {
                        return $v;
                      }
                      $v = str_replace("'", "\\'", $v);
                      return "'" . $v . "'";
                    }
                    return $v;
                  }, $validations);
                  foreach ($validations_quoted as $v) {
                    fwrite($serviceFile, "\t\t\t\t\t$v,\n");
                  }
                  fwrite($serviceFile, "\t\t\t\t],\n");
                  
                  // Determine if field is required
                  // Primary keys with auto-increment are never required for inserts
                  // Other fields are required if they don't allow null
                  $isRequired = !$field->AllowNull() && $field->GetDataType() != "bool" && $field->GetDataType() != "bit" && !($field->IsPrimaryKey() && $field->IsAutoIncrement());
                  fwrite($serviceFile, "\t\t\t\t'required' => ".($isRequired ? "true" : "false").",\n");
                  
                  fwrite($serviceFile, "\t\t\t\t'type' => ");
                  switch($field->GetDataType()) {
                    case "bool":
                    case "bit":
                      fwrite($serviceFile, "'bool'\n");
                      break;
                    case "int":
                      fwrite($serviceFile, "'int'\n");
                      break;
                    case "float":
                      fwrite($serviceFile, "'float'\n");
                      break;
                    case "date":
                    case "datetime":
                    case "timestamp":
                      fwrite($serviceFile, "'iso_date'\n");
                      break;
                    case "string":
                    default:
                      fwrite($serviceFile, "'string'\n");
                      break;
                  }
                  fwrite($serviceFile, "\t\t\t],\n");
                }
                
                fwrite($serviceFile, "\t\t];");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");

                // Generate validate form data method
                fwrite($serviceFile, "\t/**");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * Validate form data from \$_POST");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t *");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @param array \$data (ignored - always uses \$_POST)");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @return array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t */");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\tpublic static function validateFormData(array \$data): array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t{");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\treturn Validations::ValidateFormData(self::getValidationRules());");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");

                // Generate validate data method
                fwrite($serviceFile, "\t/**");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * Validate arbitrary data array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t *");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @param array \$data");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @return array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t */");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\tpublic static function validateData(array \$data): array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t{");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\treturn Validations::ValidateJsonData(\$data, self::getValidationRules());");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");

                // Generate validate JSON method
                fwrite($serviceFile, "\t/**");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * Validate JSON data");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t *");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @param array \$data");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @return array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t */");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\tpublic static function validateJsonData(array \$data): array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t{");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\treturn Validations::ValidateJsonData(\$data, self::getValidationRules());");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");

                // Generate partial validation method
                fwrite($serviceFile, "\t/**");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * Validate only the fields that are present in the data");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t *");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @param array \$data");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @return array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t */");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\tpublic static function validatePartialData(array \$data): array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t{");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\t// Filter validation rules to only include fields present in data");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\t\$partialRules = [];");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\tforeach (\$data as \$field => \$value) {");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\t\tif (isset(self::getValidationRules()[\$field])) {");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\t\t\t\$partialRules[\$field] = self::getValidationRules()[\$field];");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\t\t}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\t}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\treturn Validations::ValidateJsonData(\$data, \$partialRules);");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");

                // Generate update validation method
                fwrite($serviceFile, "\t/**");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * Validate data for updating existing record (partial validation)");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t *");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @param array \$data");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @param int \$id");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t * @return array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t */");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\tpublic static function validateUpdate(array \$data, int \$id): array");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t{");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\t// Use partial validation for updates");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t\treturn self::validatePartialData(\$data);");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\t}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "\n");

                fwrite($serviceFile, "}");
                fwrite($serviceFile, "\n");
                fwrite($serviceFile, "?>");
                
                fclose($serviceFile);
            }
            else echo "ERROR: Check your permissions to write ".$servicesPath."/".$db->GetTableName()."ValidationService.php\n";
        }
        else echo "ERROR: Check your permissions to remove ".$servicesPath."/".$db->GetTableName()."ValidationService.php\n";
    }
} 