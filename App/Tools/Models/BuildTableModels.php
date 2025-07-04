<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace Tools\Models;
use Core\LazyMePHP;
use Tools\Database\_DB_TABLE;
use function Tools\Helper\MKDIR;
use function Tools\Helper\UNLINK;
use function Tools\Helper\TOUCH;

/**
 * Interface for Automatic DB Models
 */
interface IDB
{
    public function Save();
    public function Delete();
}

/**
 * Interface for Automatic DB Lists
 */
interface IDB_LIST
{
    public function FindAll();
    public function GetList(bool $serialize = false, array $mask = array());
}

// Add this function at the top of the file (after namespace and use statements)
if (!function_exists('mapSqlTypeToPhpType')) {
    function mapSqlTypeToPhpType($sqlType) {
        $type = strtolower($sqlType);
        return match (true) {
            str_starts_with($type, 'varchar'),
            str_starts_with($type, 'char'),
            str_starts_with($type, 'text'),
            str_starts_with($type, 'date'),
            str_starts_with($type, 'time') => 'string',
            str_starts_with($type, 'int') => 'int',
            str_starts_with($type, 'bool'),
            str_starts_with($type, 'boolean') => 'bool',
            str_starts_with($type, 'float'),
            str_starts_with($type, 'double'),
            str_starts_with($type, 'decimal') => 'float',
            default => 'string',
        };
    }
}

/**
 * Build class tables
 */
class BuildTableModels extends _DB_TABLE
{
    /**
     * Constructor
     *
     * Builds Models Files for each Table in the DataBase
     *
     * @param (string) (path)
     * @return (null)
     */
	function __construct($classesPath, $tablesList, $tableDescriptors)
	{
    // Create Folder if doesn't exist
    if (!is_dir($classesPath)) MKDIR($classesPath);

    // Get all tables and views
    $allTables = \Tools\Database\_DB_TABLE::GetAllTablesAndViews();
    
    foreach ($allTables as $tableName) {
      if (is_array($tablesList) && array_search($tableName, $tablesList)!==false) {
        $db = new \Tools\Database\_DB_TABLE($tableName);
        $this->ConstructModel($classesPath,$db,$tableDescriptors);
      }
    }
  }

	protected function ConstructModel($classesPath, $db, $tableDescriptors)
	{
		$db->GetFieldsFromDB();

    if (UNLINK($classesPath."/".$db->GetTableName().".php"))
    {
      if (TOUCH($classesPath."/".$db->GetTableName().".php"))
      {
        $classFile = fopen($classesPath."/".$db->GetTableName().".php","w+");
        fwrite($classFile,"<?php");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile,"/**");
        fwrite($classFile, "\n");
        fwrite($classFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
        fwrite($classFile, "\n");
        fwrite($classFile," * @author Duarte Peixinho");
        fwrite($classFile, "\n");
        fwrite($classFile," *");
        fwrite($classFile, "\n");
        fwrite($classFile," * Source File Generated Automatically");
        fwrite($classFile, "\n");
        fwrite($classFile," */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "declare(strict_types=1);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "namespace Models;\n");
        fwrite($classFile, "use Core\LazyMePHP;\n");
        fwrite($classFile, "use Core\Helpers\ErrorUtil;\n");

        fwrite($classFile, "\n");
        fwrite($classFile, "class ".$db->_Tablename." extends \\Core\\ModelBase implements \\Core\\DB\\IDB {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Log Data");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprivate \$__log = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Descriptor");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Get table descriptor");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return mixed");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function GetDescriptor() : mixed { return \$this->".(key_exists($db->_Tablename, $tableDescriptors)?$tableDescriptors[$db->_Tablename]:($db->IsView() ? $db->_Tablefields[0]->GetName() : $db->_PrimaryFieldName))."; }");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * PrimaryKey");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Get table PrimaryKey value");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return int|null");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function GetPrimaryKey() : ?int { return \$this->".($db->IsView() ? $db->_Tablefields[0]->GetName() : $db->_PrimaryFieldName)."; }");
        fwrite($classFile, "\n");
        $addToConstruct = "";
        $addToSerialize = "";
        
        $generatedMethods = [];
        $saveAndDeleteGenerated = false;
        foreach ($db->_Tablefields as $field) {
            $methodName = ucfirst($field->GetName());
            if (isset($generatedMethods[$methodName])) {
                continue;
            }
          $phpType = mapSqlTypeToPhpType($field->GetDataType());
          fwrite($classFile, "\n");
          fwrite($classFile, "\t/** @var ".$field->GetDataType()."|null ".$field->GetName()."*/");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tprotected ?$phpType $".$field->GetName()." = null;");
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t/**");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * Get ".$field->GetName());
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @return ".$phpType."|null");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t */");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function Get".ucfirst($field->GetName())."() : ?$phpType {");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t\treturn \$this->".$field->GetName().";");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t}");
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t/**");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * Set ".$field->GetName());
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @param ".$phpType."|null $".$field->GetName());
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @return void");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t */");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function Set".ucfirst($field->GetName())."(?$phpType $".$field->GetName().") : void {");
          fwrite($classFile, "\n");
          if ($field->HasForeignKey() && $field->GetForeignField())
          {
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\tif (\$this->".$field->GetName()." !== \$".$field->GetName().") {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\t\$this->".$field->GetName()."_OBJ = null;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t}");
          }
          fwrite($classFile, "\n");
          switch ($field->GetDataType())
          {
              case "bool":
              case "float":
              case "int":
                  if ($field->AllowNull())
                      fwrite($classFile, "\t\tif (!empty(\$this->".$field->GetName().") && LazyMePHP::ACTIVITY_LOG() && \$this->".$field->GetName()."!=\$".$field->GetName().")\n\t\t\t\$this->__log['".$field->GetName()."']=array(\$this->".$field->GetName().", \$".$field->GetName().");");
                  else
                      fwrite($classFile, "\t\tif (LazyMePHP::ACTIVITY_LOG() && \$this->".$field->GetName()."!=\$".$field->GetName().")\n\t\t\t\$this->__log['".$field->GetName()."']=array(\$this->".$field->GetName().", \$".$field->GetName().");");
                  break;
              case "string":
                  if ($field->AllowNull())
                      fwrite($classFile, "\t\tif (!empty(\$this->".$field->GetName().") && LazyMePHP::ACTIVITY_LOG() && \$this->".$field->GetName()."!=\$".$field->GetName().")\n\t\t\t\$this->__log['".$field->GetName()."']=array(substr(\$this->".$field->GetName()." ?? '',0,255), substr(\$".$field->GetName()." ?? '',0,255));");
                  else
                      fwrite($classFile, "\t\tif (LazyMePHP::ACTIVITY_LOG() && \$this->".$field->GetName()."!=\$".$field->GetName().")\n\t\t\t\$this->__log['".$field->GetName()."']=array(substr(\$this->".$field->GetName()." ?? '',0,255), substr(\$".$field->GetName()." ?? '',0,255));");
                  break;
          }
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          if ($field->AllowNull())
              fwrite($classFile, "\t\t\$this->".$field->GetName()."=(!empty(\$".$field->GetName().")?".($field->GetDataLength()>0?"substr(\$".$field->GetName().",0,".$field->GetDataLength().")":"\$".$field->GetName()).":".($field->GetDefaultValue??($field->Allownull()?"null":"''")).");");
          else
              fwrite($classFile, "\t\t\$this->".$field->GetName()."=\$".$field->GetName().";");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t}");
          fwrite($classFile, "\n");

          if ($field->IsPrimaryKey() && !$saveAndDeleteGenerated)
          {
            // Add foreign key object property declarations
            foreach ($db->_Tablefields as $field2)
            {
              $fkMethodName = 'get' . ucfirst($field2->GetName()) . '_OBJ';
              if ($field2->HasForeignKey() && $field2->GetForeignField() && !isset($generatedMethods[$fkMethodName]))
              {
                fwrite($classFile, "\n");
                fwrite($classFile, "\t/** @var ".$field2->GetForeignTable()."|null ".$field2->GetName()."_OBJ */");
                fwrite($classFile, "\n");
                fwrite($classFile, "\tprotected ?".$field2->GetForeignTable()." \$".$field2->GetName()."_OBJ = null;");
                fwrite($classFile, "\n");
                // Add lazy loading getter
                fwrite($classFile, "\tpublic function ".$fkMethodName."(): ?".$field2->GetForeignTable()." {");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\tif (\$this->".$field2->GetName()."_OBJ === null && \$this->".$field2->GetName()." !== null) {");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\t\$this->".$field2->GetName()."_OBJ = new ".$field2->GetForeignTable()."(\$this->".$field2->GetName().");");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t}");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\treturn \$this->".$field2->GetName()."_OBJ;");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t}\n");
                $generatedMethods[$fkMethodName] = true;
              }
            }
            
            fwrite($classFile, "\n");
            fwrite($classFile, "\t/**");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Constructor");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Class Constructor");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @param mixed \$".$field->GetName());
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return null");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile,"\tpublic function __construct(mixed \$".$field->GetName()."=null) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t/* Array Constructor */");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\tif (is_array(\$".$field->GetName().")) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\$this->initialize();");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\tforeach(\$".$field->GetName()." as \$key => \$value) {\n");
            fwrite($classFile, "\t\t\t\tif (property_exists(\$this, \$key)) {\n");
            fwrite($classFile, "\t\t\t\t\t\$this->\$key = \$value;\n");
            fwrite($classFile, "\t\t\t\t}\n");
            fwrite($classFile, "\t\t\t}\n");
            fwrite($classFile, "\t\t} else {");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t// Get Elements from DB using Primary Key");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\tif (isset(\$".$field->GetName().")) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\$this->initialize();");
            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\$sql = \"SELECT ");
            $countFields = 0;
            foreach ($db->_Tablefields as $field2)
            {
              fwrite($classFile,($countFields++>0?",":"").$db->_Tablename.".".$field2->GetName());
            }
            fwrite($classFile," FROM ".$db->_Tablename." WHERE ".$db->GetTableName().".".$field->GetName()."=?\";");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\$rtn = LazyMePHP::DB_CONNECTION()->Query(\$sql, array(\$".$field->GetName()."));");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\twhile(\$row = \$rtn->FetchArray())");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t{");
            fwrite($classFile, "\n");
            foreach ($db->_Tablefields as $field2)
            {
              switch (mapSqlTypeToPhpType($field2->GetDataType()))
              {
                case "bool":
                  fwrite($classFile,"\t\t\t\t\$this->".$field2->GetName()."=(bool)\$row['".$field2->GetName()."'];");
                break;
                case "float":
                  fwrite($classFile,"\t\t\t\t\$this->".$field2->GetName()."=(float)\$row['".$field2->GetName()."'];");
                break;
                case "int":
                  fwrite($classFile,"\t\t\t\t\$this->".$field2->GetName()."=(int)\$row['".$field2->GetName()."'];");
                break;
                case "string":
                default:
                  fwrite($classFile,"\t\t\t\t\$this->".$field2->GetName()."=(string)\$row['".$field2->GetName()."'];");
                break;
              }
              fwrite($classFile, "\n");
            }
            fwrite($classFile, "\t\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t}");

            // Save
            fwrite($classFile, "\n");
            fwrite($classFile, "\t/**");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Save");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Insert Or Update Record Based on Primary Key Existence");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return bool");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile,"\tpublic function Save() : mixed {");
            fwrite($classFile, "\n");
            $fieldsNotnull = null;
            fwrite($classFile, "\t\t\$fieldsnull = '';");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\$nullFields = [];");
            foreach ($db->_Tablefields as $field2)
            {
              // Skip auto-increment primary keys for null checks (they can be null for new records)
              if (!$field2->Allownull() && !($field2->IsPrimaryKey() && $field2->IsAutoIncrement())) {
                $fieldsNotnull.= "\t\tif (!isset(\$this->".$field2->GetName().")) \$nullFields[] = '".$field2->GetName()."';\n";
              }
            }
            fwrite($classFile, "\n");
            if (isset($fieldsNotnull)) {
              fwrite($classFile, $fieldsNotnull);
              fwrite($classFile, "\t\t\$fieldsnull = implode(',', \$nullFields);");
            }
            fwrite($classFile, "\n");

            fwrite($classFile, "\t\tif (\$fieldsnull) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\tErrorUtil::trigger_error(\"null value not allowed!\\nFields: \$fieldsnull\", E_USER_ERROR);");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\treturn false;");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t} else {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\tif (\$this->isInitialized()) {");
            // Update
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\$sql = \"UPDATE ".$db->_Tablename." SET ");
            $countFields = 0;
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                fwrite($classFile,($countFields++>0?",":"")."`".$field2->GetName()."`=:".$field2->GetName());
              }
            }
            fwrite($classFile," WHERE ".$field->GetName()."=:".$field->GetName()."\";");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\$params = [");
            fwrite($classFile, "\n");
            $countFields = 0;
            foreach ($db->_Tablefields as $field2)
            {
                if ($field!=$field2)
                {
                    fwrite($classFile,"\t\t\t\t\t':".$field2->GetName()."' => ".(LazyMePHP::DB_TYPE() == 2 && $field2->GetDataType()=="bit"?"\$this->".$field2->GetName()."||0":"\$this->".$field2->GetName()).",");
                    fwrite($classFile, "\n");
                }
            }
            fwrite($classFile,"\t\t\t\t\t':".$field->GetName()."' => \$this->".$field->GetName().",");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t];");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\t\$method = \"U\";");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\t\$ret = LazyMePHP::DB_CONNECTION()->Query(\$sql, \$params);");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t} else {");
            // Insert
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\$sql = \"INSERT INTO ".$db->_Tablename." (");
            $countFields = 0;
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                fwrite($classFile,($countFields++>0?"`,":"")."`".$field2->GetName())."`";
              }
            }
            fwrite($classFile, ($countFields>0?"`":"").") VALUES (");
            $countFields = 0;
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                fwrite($classFile,($countFields++>0?",":"").":".$field2->GetName());
              }
            }
            fwrite($classFile, ")\";");
            fwrite($classFile, "\n");
            $countFields = 1;
            fwrite($classFile, "\t\t\t\t\$params = [");
            fwrite($classFile, "\n");
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                fwrite($classFile,"\t\t\t\t\t':".$field2->GetName()."' => ".(LazyMePHP::DB_TYPE() == 2 && $field2->GetDataType()=="bit"?"\$this->".$field2->GetName()."||0":"\$this->".$field2->GetName()).",");
                fwrite($classFile, "\n");
              }
            }
            fwrite($classFile, "\t\t\t\t];");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\t\$method = \"I\";");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\t\$ret = LazyMePHP::DB_CONNECTION()->Query(\$sql, \$params);");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\tif (\$ret) \$this->".$field->GetName()." = LazyMePHP::DB_CONNECTION()->GetLastInsertedID('".$db->_Tablename."');");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t// Use LoggingHelper for proper change logging");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\tif (LazyMePHP::ACTIVITY_LOG() && !empty(\$this->__log)) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\tif (\$method === 'I') {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\t\\Core\\Helpers\\LoggingHelper::logInsert('".$db->_Tablename."', \$this->__log, (string)\$this->".$field->GetName().");");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t} else {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\t\\Core\\Helpers\\LoggingHelper::logUpdate('".$db->_Tablename."', \$this->__log, '".$field->GetName()."', (string)\$this->".$field->GetName().");");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\treturn \$ret;");
            fwrite($classFile, "\n");
            // End Save
            fwrite($classFile, "\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t}");
            // Delete
            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t/**");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Delete");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Deletes Record from Database based Primary Key");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return bool");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile, "\tpublic function Delete() : bool");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t{");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\tif (!isset(\$this->".$field->GetName().")) return false;");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t// Log deletion using LoggingHelper");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\tif (LazyMePHP::ACTIVITY_LOG()) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\\Core\\Helpers\\LoggingHelper::logDelete('".$db->_Tablename."', '".$field->GetName()."', (string)\$this->".$field->GetName().");");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\$sql = \"DELETE FROM ".$db->_Tablename." WHERE ".$field->GetName()."=?\";");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\$rtn = LazyMePHP::DB_CONNECTION()->Query(\$sql, array(\$this->".$db->_PrimaryFieldName."));");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\treturn true;");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t}");
            fwrite($classFile, "\n");
            $saveAndDeleteGenerated = true;
          }
          $generatedMethods[$methodName] = true;
        }
        if (!$saveAndDeleteGenerated)
        {
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function Save() : mixed { return false; }");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function Delete() : bool { return false; }");
        }
        
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Serialize");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Returns object in an array");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param array|null \$mask array to show/hide fields");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return array");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function Serialize(?array \$mask = null) {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$vars = get_object_vars(\$this);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\tif (\$mask && array_key_exists('".$db->_Tablename."', \$mask))");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\t\$vars = array_intersect_key(\$vars, array_flip(\$mask['".$db->_Tablename."']));");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\treturn \$vars;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "}");
      }
			else echo "ERROR: Check your permissions to write ".$classesPath."/".$db->_Tablename.".php\n";
		}
		else echo "ERROR: Check your permissions to remove ".$classesPath."/".$db->_Tablename.".php\n";

    if (UNLINK($classesPath."/".$db->GetTableName()."_List.php"))
    {
      if (TOUCH($classesPath."/".$db->GetTableName()."_List.php"))
      {
        $classFile = fopen($classesPath."/".$db->GetTableName()."_List.php","w+");
        fwrite($classFile,"<?php");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile,"/**");
        fwrite($classFile, "\n");
        fwrite($classFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
        fwrite($classFile, "\n");
        fwrite($classFile," * @author Duarte Peixinho");
        fwrite($classFile, "\n");
        fwrite($classFile," *");
        fwrite($classFile, "\n");
        fwrite($classFile," * Source File Generated Automatically");
        fwrite($classFile, "\n");
        fwrite($classFile," */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "declare(strict_types=1);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "namespace Models;\n");
        fwrite($classFile, "use Core\LazyMePHP;\n");
        fwrite($classFile, "use Core\Helpers\ErrorUtil;\n");

        fwrite($classFile, "\n");

        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "class ".$db->_Tablename."_List implements \\Core\\DB\\IDB_LIST {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected array \$_args = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected array \$_list = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected bool \$_listHasCondition = false;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected string \$_sql = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected string \$_sqlToAdd = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected string \$_order = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected string \$_group = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected int \$_limitStart = 0;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected int \$_limitEnd = 0;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Constructor");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Class Constructor");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return null");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function __construct() {}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Add Left Parentesis");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (void)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function AddLeftParentesis() : void {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_sqlToAdd=\" ( \";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Add Right Parentesis");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (void)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function AddRightParentesis() : void {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_sql.=\" ) \";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function GetCount() : int {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile,"\t\t\$_sql = \"SELECT ");
        $countFields = 0;
        foreach ($db->_Tablefields as $field2)
        {
          fwrite($classFile,($countFields++>0?",":"").$db->_Tablename.".".$field2->GetName());
        }
        fwrite($classFile," FROM ".$db->_Tablename."\".(!empty(\$this->_sql)?\" WHERE \".\$this->_sql:\"\");");

        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$rtn = LazyMePHP::DB_CONNECTION()->Query(\$_sql.\" \".(!empty(\$this->_group)?\"GROUP BY \".\$this->_group:\"\").\" \".(!empty(\$this->_order)?\"ORDER BY \".\$this->_order:\"\").\" \".(\$this->_limitEnd?(empty(\$this->_order)?\"ORDER BY ".$db->_Tablename.".".$db->_PrimaryFieldName." \":\"\").LazyMePHP::DB_CONNECTION()->Limit(\$this->_limitEnd, \$this->_limitStart):\"\"), \$this->_args);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\treturn \$rtn->getRowCount();");
        fwrite($classFile, "\n");
        fwrite($classFile,"\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * GetList");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Gets List of Pre Selected Objects");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param bool \$serialize if the output should be Serialized");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param array \$mask array to show/hide fields");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return array");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function GetList(bool \$serialize=false, array \$mask=array()) : array {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_list = array();");
        fwrite($classFile, "\n");
        fwrite($classFile,"\t\t\$this->_sql = \"SELECT ");
        $countFields = 0;
        foreach ($db->_Tablefields as $field2)
        {
          fwrite($classFile,($countFields++>0?",":"").$db->_Tablename.".".$field2->GetName());
        }
        fwrite($classFile," FROM ".$db->_Tablename."\".(!empty(\$this->_sql)?\" WHERE \".\$this->_sql:\"\");");

        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$rtn = LazyMePHP::DB_CONNECTION()->Query(\$this->_sql.\" \".(!empty(\$this->_group)?\"GROUP BY \".\$this->_group:\"\").\" \".(!empty(\$this->_order)?\"ORDER BY \".\$this->_order:\"\").\" \".(\$this->_limitEnd?(empty(\$this->_order)?\"ORDER BY ".$db->_Tablename.".".$db->_PrimaryFieldName." \":\"\").LazyMePHP::DB_CONNECTION()->Limit(\$this->_limitEnd, \$this->_limitStart):\"\"),\$this->_args);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\twhile(\$row = \$rtn->FetchArray())");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t{");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\t\$obj = new ".$db->_Tablename."(null);");
        fwrite($classFile, "\n");
        foreach ($db->_Tablefields as $field2)
        {
          switch (mapSqlTypeToPhpType($field2->GetDataType()))
          {
            case "bool":
              fwrite($classFile,"\t\t\t\t\$obj->Set".ucfirst($field2->GetName())."((bool)\$row['".$field2->GetName()."']);");
            break;
            case "float":
              fwrite($classFile,"\t\t\t\t\$obj->Set".ucfirst($field2->GetName())."((float)\$row['".$field2->GetName()."']);");
            break;
            case "int":
              fwrite($classFile,"\t\t\t\t\$obj->Set".ucfirst($field2->GetName())."((int)\$row['".$field2->GetName()."']);");
            break;
            case "string":
            default:
              fwrite($classFile,"\t\t\t\t\$obj->Set".ucfirst($field2->GetName())."((string)\$row['".$field2->GetName()."']);");
            break;
          }
          fwrite($classFile, "\n");
        }
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\t\t// Initialize the object so Save() knows it's an existing record");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\t\t\$obj->initialize();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\tif (\$serialize) \$this->_list[]=\$obj->Serialize(\$mask);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\telse \$this->_list[]=\$obj;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_sql=\"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\treturn \$this->_list;");
        fwrite($classFile, "\n");
        fwrite($classFile,"\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Serialize");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Serialize List");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param array|null \$mask array to show/hide fields");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return array");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function Serialize(?array \$mask = null)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t{");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\treturn \$this->GetList(true, \$mask);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * ClearFind");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Clears Find Filters and Orders");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return void");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function ClearFind() : void {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_args = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_list = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_sql = '';");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_order = '';");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_group = '';");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");

        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * FindAll");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Fetch all results");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return void");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function FindAll() : void {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_sql = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");

        $generatedMethods = [];
        foreach ($db->_Tablefields as $field)
        {
            $fieldName = $field->GetName();

            // FindBy
            $methodName = 'FindBy' . ucfirst($fieldName);
            if (!isset($generatedMethods[$methodName])) {
                fwrite($classFile, "\n");
                fwrite($classFile, "\t/**");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * FindBy".$field->GetName());
                fwrite($classFile, "\n");
                fwrite($classFile, "\t *");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * Finds result where ".$field->GetName()." == \$var");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t *");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * @param ".$field->GetDataType()." \$var");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * @return void");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t */");
                fwrite($classFile, "\n");
                fwrite($classFile, "\tpublic function FindBy".$field->GetName()."(".mapSqlTypeToPhpType($field->GetDataType())." \$var, string \$operator = '=', string \$operator2 = 'AND') : void {");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_args[]=\$var;");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_sql .= \" \".(!empty(\$this->_listHasCondition)?\"\$operator2\":\"\").\" \".\$this->_sqlToAdd.\" ".$db->GetTableName().".".$field->GetName()." \$operator ?\";");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_listHasCondition = true;");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_sqlToAdd = \"\";");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t}");
                fwrite($classFile, "\n");
                $generatedMethods[$methodName] = true;
            }

            if ($field->AllowNull()) {
                // IsNULL
                $methodName = ucfirst($fieldName) . 'IsNULL';
                if (!isset($generatedMethods[$methodName])) {
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t/**");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t * ".$field->GetName()."IsNULL");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t *");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t * Finds result where ".$db->GetTableName().".".$field->GetName()." IS NULL");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t *");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t * @return void");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t */");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\tpublic function ".$field->GetName()."IsNULL(string \$operator = 'AND') : void {");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t\t\$this->_sql .= \" \".(!empty(\$this->_listHasCondition)?\"\$operator\":\"\").\" \".\$this->_sqlToAdd.\" ".$db->GetTableName().".".$field->GetName()." IS NULL\";");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t\t\$this->_listHasCondition = true;");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t}");
                    fwrite($classFile, "\n");
                    $generatedMethods[$methodName] = true;
                }

                // IsNotNULL
                $methodName = ucfirst($fieldName) . 'IsNotNULL';
                if (!isset($generatedMethods[$methodName])) {
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t/**");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t * ".$field->GetName()."IsNotNULL");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t *");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t * Finds result where ".$db->GetTableName().".".$field->GetName()." IS NOT NULL");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t *");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t * @return void");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t */");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\tpublic function ".$field->GetName()."IsNotNULL(string \$operator = 'AND') : void {");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t\t\$this->_sql .= \" \".(!empty(\$this->_listHasCondition)?\"\$operator\":\"\").\" \".\$this->_sqlToAdd.\" ".$db->GetTableName().".".$field->GetName()." IS NOT NULL\";");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t\t\$this->_listHasCondition = true;");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t}");
                    fwrite($classFile, "\n");
                    $generatedMethods[$methodName] = true;
                }
            }

            // FindByLike
            $methodName = 'FindBy' . ucfirst($fieldName) . 'Like';
            if (!isset($generatedMethods[$methodName])) {
                fwrite($classFile, "\t/**");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * FindBy".$field->GetName()."Like");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t *");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * Finds result where ".$db->GetTableName().".".$field->GetName()." LIKE %\$var%");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t *");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * @param string \$var");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * @return void");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t */");
                fwrite($classFile, "\n");
                fwrite($classFile, "\tpublic function FindBy".$field->GetName()."Like(string \$var, string \$operator = 'AND') : void {");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_args[] = \"%\$var%\";");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_sql .= \" \".(!empty(\$this->_listHasCondition)?\"\$operator\":\"\").\" \".\$this->_sqlToAdd.\" ".$db->GetTableName().".".$field->GetName()." LIKE ?\";");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_listHasCondition = true;");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t}");
                fwrite($classFile, "\n");
                $generatedMethods[$methodName] = true;
            }

            // OrderBy
            $methodName = 'OrderBy' . ucfirst($fieldName);
            if (!isset($generatedMethods[$methodName])) {
                /* Add sorting */
                fwrite($classFile, "\n");
                fwrite($classFile, "\t/**");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * OrderBy".$field->GetName());
                fwrite($classFile, "\n");
                fwrite($classFile, "\t *");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * Orders last find by ".$field->GetName()." \$var (ASC | DESC)");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t *");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * @param string \$var");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * @return void");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t */");
                fwrite($classFile, "\n");
                fwrite($classFile, "\tpublic function OrderBy".$field->GetName()."(string \$var) : void {");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_order=\$this->_order.(!empty(\$this->_order)?\",\":\"\").\" ".$field->GetName()." \$var\";");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t}");
                fwrite($classFile, "\n");
                $generatedMethods[$methodName] = true;
            }

            // GroupBy
            $methodName = 'GroupBy' . ucfirst($fieldName);
            if (!isset($generatedMethods[$methodName])) {
                /* Add grouping */
                fwrite($classFile, "\n");
                fwrite($classFile, "\t/**");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * GroupBy".$field->GetName());
                fwrite($classFile, "\n");
                fwrite($classFile, "\t *");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * Groups last find by ".$field->GetName());
                fwrite($classFile, "\n");
                fwrite($classFile, "\t *");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t * @return void");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t */");
                fwrite($classFile, "\n");
                fwrite($classFile, "\tpublic function GroupBy".$field->GetName()."() : void {");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t\t\$this->_group=\$this->_group.(!empty(\$this->_group)?\",\":\"\").\" ".$field->GetName()."\";");
                fwrite($classFile, "\n");
                fwrite($classFile, "\t}");
                fwrite($classFile, "\n");
                $generatedMethods[$methodName] = true;
            }
        }

        // Find by foreign tables values
        foreach($db->_Tablefields as $field)
        {
          if ($field->GetForeignTable())
          {
            $fTable = new \Tools\Database\_DB_TABLE($field->GetForeignTable());
            $fTable->GetFieldsFromDB();
            foreach ($fTable->_Tablefields as $field2)
            {
              $methodName = 'FindBy'.$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName();
              if(isset($generatedMethods[$methodName])) continue;

              fwrite($classFile, "\n");
              fwrite($classFile, "\t/**");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * FindBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName());
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * Finds result where ".$fTable->GetTableName()."_".$field2->GetName()." == \$var");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @param string \$var");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @return void");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t */");
              fwrite($classFile, "\n");
              fwrite($classFile, "\tpublic function FindBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."(string \$var, string \$operator = '=', string \$operator2 = 'AND') : void {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_args[]=\$var;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_sql .= \" \".(!empty(\$this->_sql)?\"\$operator2\":\"\").\" \".\$this->_sqlToAdd.\" ".$field->GetName()."_".$fTable->GetTableName().".".$field2->GetName()." \$operator ?\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_listHasCondition = true;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_sqlToAdd = \"\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t}");
              fwrite($classFile, "\n");

              fwrite($classFile, "\n");
              fwrite($classFile, "\t/**");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * FindBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."Like");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * Finds result where ".$fTable->GetTableName()."_".$field2->GetName()." LIKE %\$var%");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @param string \$var");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @return void");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t */");
              fwrite($classFile, "\n");
              fwrite($classFile, "\tpublic function FindBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."Like(string \$var, string \$operator = 'AND') : void {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_args[] = \"%\$var%\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_sql .= \" \".(!empty(\$this->_sql)?\"\$operator\":\"\").\" \".\$this->_sqlToAdd.\" ".$field->GetName()."_".$fTable->GetTableName().".".$field2->GetName()." LIKE ?\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_listHasCondition = true;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_sqlToAdd = \"\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t}");
              fwrite($classFile, "\n");
              /* Add sorting */
              fwrite($classFile, "\n");
              fwrite($classFile, "\t/**");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * OrderBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName());
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * Orders last find by ".$fTable->GetTableName().$field2->GetName()." \$var (ASC | DESC)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @param string \$var");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @return void");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t */");
              fwrite($classFile, "\n");
              fwrite($classFile, "\tpublic function OrderBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."(string \$var) : void {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_order=\$this->_order.(!empty(\$this->_order)?\",\":\"\").\" ".$field->GetName()."_".$fTable->GetTableName().".".$field2->GetName()." \$var\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t}");
              fwrite($classFile, "\n");
              /* Add Grouping */
              fwrite($classFile, "\n");
              fwrite($classFile, "\t/**");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * GroupBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName());
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * Groups last find by ".$fTable->GetTableName().$field2->GetName());
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @return void");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t */");
              fwrite($classFile, "\n");
              fwrite($classFile, "\tpublic function GroupBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."() : void {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_group=\$this->_group.(!empty(\$this->_group)?\",\":\"\").\" ".$field->GetName()."_".$fTable->GetTableName().".".$field2->GetName()."\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t}");
              fwrite($classFile, "\n");

              $generatedMethods[$methodName] = true;
            }
          }
        }

        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * CustomFind");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Customize Find with SQL - haystack LIKE '%needle%'");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param string \$args");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return void");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function CustomFind(string \$args) : void {");
        fwrite($classFile, "\n");

        fwrite($classFile, "\t\t\$this->_order = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_group = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_sql = \$args;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");

        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Limit");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Limit results to user defined number");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param int \$end");
        fwrite($classFile, "\t * @param int \$start");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return void");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function Limit(int \$end, int \$start = 0) : void {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_limitEnd = \$end;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_limitStart = \$start;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");
        fwrite($classFile,"}");
      }
			else echo "ERROR: Check your permissions to write ".$classesPath."/".$db->_Tablename.".php\n";
		}
		else echo "ERROR: Check your permissions to remove ".$classesPath."/".$db->_Tablename.".php\n";
	}
}
?>