<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\ClassesBuilder;
use \LazyMePHP\Config\Internal\APP;
use \LazyMePHP\DatabaseHelper;

require_once 'DatabaseHelper.php';

/**
 * Interface for Automatic DB Classes
 */
interface IDB_CLASS
{
    public function Save();
    public function Delete();
}

/**
 * Interface for Automatic DB Classes Lists
 */
interface IDB_CLASS_LIST
{
    public function FindAll();
    public function GetList($buildForeignMembers = true);
}

/**
 * Build class tables
 */
class BuildTableClasses extends \LazyMePHP\DatabaseHelper\_DB_TABLE
{

    /**
     * Constructor
     *
     * Builds Class Files for each Table in the DataBase
     *
     * @param (string) (path)
     * @return (NULL)
     */
	function __construct($classesPath, $tablesList, $replaceInclude, $tableDescriptors)
	{
    // Create Folder if doesn't exist
    if (!is_dir($classesPath)) \LazyMePHP\Helper\MKDIR($classesPath);

    $failedIncludeFile = false;

    // Create Last File to Help on Requires
		if ($replaceInclude) {
			if (\LazyMePHP\Helper\UNLINK($classesPath."/includes.php"))
			{
				if (\LazyMePHP\Helper\TOUCH($classesPath."/includes.php"))
				{
          $classFile = fopen($classesPath."/includes.php","w+");
          fwrite($classFile,"<?php");
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile,"/**");
          fwrite($classFile, "\n");
          fwrite($classFile," * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho");
          fwrite($classFile, "\n");
          fwrite($classFile," * @author Duarte Peixinho");
          fwrite($classFile," *");
          fwrite($classFile, "\n");
          fwrite($classFile," * Source File Generated Automatically");
          fwrite($classFile, "\n");
          fwrite($classFile," */");
          fwrite($classFile, "\n");
        }
        else {
          echo "ERROR: Check your permissions to write ".$classesPath."/includes.php";
          $failedIncludeFile = true;
        }
      }
      else {
        echo "ERROR: Check your permissions to write ".$classesPath."/includes.php";
        $failedIncludeFile = true;
      }
    }

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

    APP::DB_CONNECTION()->Query($queryString, $sqlObj);
    while ($o=$sqlObj->FetchObject())
    {
      if (is_array($tablesList) && array_search($o->Table, $tablesList)>=0) {
        $db = new \LazyMePHP\DatabaseHelper\_DB_TABLE($o->Table);
        $this->ConstructClass($classesPath,$db,$tableDescriptors);
      }

      if ($replaceInclude && !$failedIncludeFile) {
        fwrite($classFile, "\n");
        fwrite($classFile, "require_once __DIR__.'/".$o->Table.".php';");
      }
    }

    // Close Include file
    if ($replaceInclude && !$failedIncludeFile) {
      fwrite($classFile, "\n");
      fwrite($classFile,"?>");
    }
  }

	protected function ConstructClass($classesPath, $db, $tableDescriptors)
	{
		$db->GetFieldsFromDB();

    if (\LazyMePHP\Helper\UNLINK($classesPath."/".$db->GetTableName().".php"))
    {
      if (\LazyMePHP\Helper\TOUCH($classesPath."/".$db->GetTableName().".php"))
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
        fwrite($classFile, "namespace LazyMePHP\Classes;\n");
        fwrite($classFile, "use \LazyMePHP\ClassesBuilder\IDB_CLASS;\n");
        fwrite($classFile, "use \LazyMePHP\ClassesBuilder\IDB_CLASS_LIST;\n");
        fwrite($classFile, "use \LazyMePHP\Config\Internal\APP;\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "require_once APP::ROOT_PATH().\"/src/php/DB/IDB.php\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "require_once \"includes.php\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "class ".$db->_Tablename." implements IDB_CLASS {");
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
        fwrite($classFile, "\t * @param (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return string");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function GetDescriptor() { return \$this->Get".(key_exists($db->_Tablename, $tableDescriptors)?$tableDescriptors[$db->_Tablename]:$db->_PrimaryFieldName)."(); }");
        fwrite($classFile, "\n");
        $addToConstruct = "";
        $addToSerialize = "";
        $primaryKeyFound = false;
        foreach ($db->_Tablefields as $field) {
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t/** @var ".$field->GetName()."*/");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tprotected \$".$field->GetName()." = NULL;");
          if (!$field->IsAutoIncrement() || !$field->IsPrimaryKey())
          {
            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t/**");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Set".$field->GetName());
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Sets ".$field->GetName());
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @param (".$field->GetName().")");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return NULL");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile, "\tpublic function Set".$field->GetName()."(\$".$field->GetName()."".($field->GetForeignTable()?",\$buildForeignMembers=true":" = NULL").") {");
            if ($field->HasForeignKey() && !is_null($field->GetForeignField()))
            {
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\tif (\$buildForeignMembers && \$this->".$field->GetName()."!=\$".$field->GetName().") {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\t\$this->".$field->GetName()."_OBJ = new ".$field->GetForeignTable()."(\$".$field->GetName().");");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t}");
            }
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\$this->".$field->GetName()."=(\$".$field->GetName()."!=NULL?\$".$field->GetName().":NULL);");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t}");
          }

          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t/**");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * Get".$field->GetName());
          fwrite($classFile, "\n");
          fwrite($classFile, "\t *");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * Gets ".$field->GetName());
          fwrite($classFile, "\n");
          fwrite($classFile, "\t *");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @param (NULL)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @return ".$field->GetName());
          fwrite($classFile, "\n");
          fwrite($classFile, "\t */");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function Get".$field->GetName()."() { return \$this->".$field->GetName()."; }");

          if ($field->HasForeignKey())
          {
            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t/** @var ".$field->GetName()." protected*/");
            fwrite($classFile, "\n");
            fwrite($classFile, "\tprotected \$".$field->GetName()."_OBJ;");
            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t/**");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Get".$field->GetName()."Object");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Gets ".$field->GetName()." Object Instance");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @param (NULL)");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return (object) (".$field->GetName().")");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile, "\tpublic function Get".$field->GetName()."Object() { return \$this->".$field->GetName()."_OBJ; }");
          }

          if ($field->IsPrimaryKey())
          {
            $primaryKeyFound = true;
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
            fwrite($classFile, "\t * @param (int) (".$field->GetName().")");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return NULL");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile,"\tpublic function __construct(\$".$field->GetName()."=NULL, \$buildForeignMembers=true) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t// Get Elements from DB using Primary Key");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\tif (\$".$field->GetName()."!=NULL) {");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\$sql = \"SELECT ");
            $countFields = 0;
            $foreignTablesJoin = "";
            foreach ($db->_Tablefields as $field2)
            {
              fwrite($classFile,($countFields++>0?",":"").$db->_Tablename.".".$field2->GetName());
              if ($field2->HasForeignKey() && !is_null($field2->GetForeignField()))
              {
                fwrite($classFile, "\".(\$buildForeignMembers?\"");
                $addToConstruct.="\n\t\t\$this->".$field2->GetName()."_OBJ = new ".$field2->GetForeignTable()."(\$this->".$field2->GetName().");";
                $addToSerialize.="\n\t\t\tif (\$this->".$field2->GetName()."_OBJ) \$vars[\"".$field2->GetName()."_OBJ\"]=\$this->".$field2->GetName()."_OBJ->Serialize(\$foreign, \$mask);";

                $fTable = new \LazyMePHP\DatabaseHelper\_DB_TABLE($field2->GetForeignTable());
                $foreignTablesJoin.=(strlen($foreignTablesJoin)>0?" ":"")."LEFT JOIN ".$fTable->GetTableName()." ".$field2->GetName()."_".$fTable->GetTableName();
                $fTable->GetFieldsFromDB();
                foreach($fTable->GetTableFields() as $field3)
                {
                  if ($field3->IsPrimaryKey())
                  {
                    $foreignTablesJoin.= " ON ".$db->GetTableName().".".$field2->GetName()."=".$field2->GetName()."_".$fTable->GetTableName().".".$field3->GetName();
                  }

                  fwrite($classFile,($countFields++>0?",":"").$field2->GetName()."_".$fTable->GetTableName().".".$field3->GetName()." AS ".$field2->GetName()."_".$field3->GetName());
                }
                fwrite($classFile,"\":\"\").\"");
              }
            }
            fwrite($classFile," FROM ".$db->_Tablename.(strlen($foreignTablesJoin)>0?" ":"")."\".(\$buildForeignMembers?\"$foreignTablesJoin\":\"\").\""." WHERE ".$db->GetTableName().".".$field->GetName()."=?\";");

            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\tAPP::DB_CONNECTION()->Query(\$sql, \$rtn, array(\$".$field->GetName()."));");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\twhile(\$row = \$rtn->FetchArray())");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t{");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\$this->".$field->GetName()."=\$".$field->GetName().";");
            fwrite($classFile, "\n");
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                if ($field2->HasForeignKey() && !is_null($field2->GetForeignField()))
                {

                  $fTable = new \LazyMePHP\DatabaseHelper\_DB_TABLE($field2->GetForeignTable());
                  $fTable->GetFieldsFromDB();
                  fwrite($classFile, "\n");
                  fwrite($classFile, "\t\t\t\tif (\$buildForeignMembers)");
                  fwrite($classFile, "\n");
                  fwrite($classFile, "\t\t\t\t{");
                  fwrite($classFile, "\n");
                  fwrite($classFile, "\t\t\t\t\t\$this->".$field2->GetName()."_OBJ = new \\LazyMePHP\\Classes\\Priv\\__".$fTable->GetTableName()."();");
                  fwrite($classFile, "\n");
                  foreach($fTable->GetTableFields() as $field3)
                  {
                    fwrite($classFile,"\t\t\t\t\t\$this->".$field2->GetName()."_OBJ->_Set".$field3->GetName()."(\$row['".$field2->GetName()."_".$field3->GetName()."']);");
                    fwrite($classFile, "\n");
                  }
                  fwrite($classFile, "\t\t\t\t\t\$this->".$field2->GetName()."_OBJ->constructForeignKeysObjs();");

                  fwrite($classFile, "\n");
                  fwrite($classFile, "\t\t\t\t}");
                  fwrite($classFile, "\n");
                  fwrite($classFile, "\n");
                }
                fwrite($classFile,"\t\t\t\t\$this->".$field2->GetName()."=\$row['".$field2->GetName()."'];");
                fwrite($classFile, "\n");
              }
            }
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t}");
            fwrite($classFile, "\n");

            fwrite($classFile, "\t\t}");
            // Close Construct
            fwrite($classFile, "\n");
            fwrite($classFile, "\t}");

            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t/**");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * constructForeignKeysObjs");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * Instantiates foreignkeys members");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t *");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return NULL");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile,"\tprotected function constructForeignKeysObjs() {");
            fwrite($classFile, $addToConstruct);
            fwrite($classFile, "\n");
            fwrite($classFile, "\t}");

            // Save
            fwrite($classFile, "\n");
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
            fwrite($classFile, "\t * @param (NULL)");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return bool");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile,"\tpublic function Save() {");
            $fieldsNotNull = NULL;
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                if (!$field2->AllowNull()) $fieldsNotNull.= "\t\tif (isset(\$this->".$field2->GetName().")===false) \$fieldsNull .= (!is_null(\$fieldsNull)?\",\":\"\").\"".$field2->GetName()."\";\n";
              }
            }
            fwrite($classFile, "\t\t\$fieldsNull = NULL;");
            fwrite($classFile, "\n");
            fwrite($classFile, $fieldsNotNull);
            fwrite($classFile, "\t\tif (\$fieldsNull) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\ttrigger_error(\"NULL value not allowed!\\nFields: \$fieldsNull\\nError: \", E_USER_ERROR);");
                    fwrite($classFile, "\n");
                    fwrite($classFile, "\t\t\treturn false;");
                    fwrite($classFile, "\n");
            fwrite($classFile, "\t\t} else {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\tif (\$this->".$field->GetName()."!=NULL) {");
            // Update
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\$sql = \"UPDATE ".$db->_Tablename." SET ");
            $countFields = 0;
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                fwrite($classFile,($countFields++>0?",":"").$db->_Tablename.".".$field2->GetName()."=".(APP::DB_TYPE() == 2 && $field2->GetDataType()=="bit"?"b":"")."?");
              }
            }
            fwrite($classFile," WHERE ".$field->GetName()."=?\";");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\$sqlArgs = array();");
            fwrite($classFile, "\n");
            $countFields = 1;
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                fwrite($classFile,"\t\t\t\t\$sqlArgs[] = ".(APP::DB_TYPE() == 2 && $field2->GetDataType()=="bit"?"\$this->".$field2->GetName()."||0":"\$this->".$field2->GetName()).";");
                fwrite($classFile, "\n");
              }
            }
            fwrite($classFile,"\t\t\t\t\$sqlArgs[] = \$this->".$field->GetName().";");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\t\t\tAPP::DB_CONNECTION()->Query(\$sql, \$rtn, \$sqlArgs);");
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
                fwrite($classFile,($countFields++>0?",":"")."".(APP::DB_TYPE() == 2 && $field2->GetDataType()=="bit"?"b":"")."?");
              }
            }
            fwrite($classFile, ")\";");
            fwrite($classFile, "\n");
            $countFields = 1;
            fwrite($classFile, "\t\t\t\t\$sqlArgs = array();");
            fwrite($classFile, "\n");
            foreach ($db->_Tablefields as $field2)
            {
              if ($field!=$field2)
              {
                fwrite($classFile,"\t\t\t\t\$sqlArgs[] = ".(APP::DB_TYPE() == 2 && $field2->GetDataType()=="bit"?"\$this->".$field2->GetName()."||0":"\$this->".$field2->GetName()).";");
                fwrite($classFile, "\n");
              }
            }
            fwrite($classFile,"\t\t\t\tAPP::DB_CONNECTION()->Query(\$sql, \$rtn, \$sqlArgs);");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\$this->".$field->GetName()." = APP::DB_CONNECTION()->GetLastInsertedID('".$db->_Tablename."');");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\treturn true;");
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
            fwrite($classFile, "\t * @param (NULL)");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t * @return bool");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t */");
            fwrite($classFile, "\n");
            fwrite($classFile, "\tpublic function Delete()");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t{");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\tif (isset(\$this->".$field->GetName().")===false) return false;");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\$sql = \"DELETE FROM ".$db->_Tablename." WHERE ".$field->GetName()."=?\";");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t\tAPP::DB_CONNECTION()->Query(\$sql, \$rtn, array(\$this->".$db->_PrimaryFieldName."));");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\treturn true;");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t}");
            fwrite($classFile, "\n");
          }
        }
        if (!$primaryKeyFound)
        {
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function Save() {}");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function Delete() {}");
          fwrite($classFile, "\n");
          fwrite($classFile,"\tprotected function constructForeignKeysObjs() {}");
          fwrite($classFile, "\n");
          fwrite($classFile, "\n");
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
        fwrite($classFile, "\t * @param (bool) foreign");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param (array) mask array to show/hide fields");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return array");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function Serialize(\$foreign = false, \$mask = NULL) {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$vars = get_object_vars(\$this);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\tif (\$mask && array_key_exists('".$db->_Tablename."', \$mask))");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\t\$vars = array_intersect_key(\$vars, array_flip(\$mask['".$db->_Tablename."']));");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\tif (\$foreign) {");
        fwrite($classFile, $addToSerialize);
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\treturn \$vars;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "class ".$db->_Tablename."_List implements IDB_CLASS_LIST {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected \$_args = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected \$_list = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected \$_sql = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected \$_order = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected \$_group = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected \$_limitStart = \"0\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected \$_limitEnd = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tprotected \$_searchNeedsForeignMembers = false;");
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
        fwrite($classFile, "\t * @param (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return NULL");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function __construct() {}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t/**");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * GetCount");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t *");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * Gets element count");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (INT)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function GetCount(\$buildForeignMembers = false) {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile,"\t\t\$_sql = \"SELECT ");
        $countFields = 0;
        $foreignTablesJoin = "";
        foreach ($db->_Tablefields as $field2)
        {
          fwrite($classFile,($countFields++>0?",":"").$db->_Tablename.".".$field2->GetName());
          if ($field2->HasForeignKey() && !is_null($field2->GetForeignField()))
          {
            fwrite($classFile, "\".(\$buildForeignMembers?\"");
            $addToConstruct.="\n\t\t\$this->".$field2->GetName()."_OBJ = new ".$field2->GetForeignTable()."(\$this->".$field2->GetName().");";
            $addToSerialize.="\n\t\t\$vars[\"".$field2->GetName()."_OBJ\"]=\$this->".$field2->GetName()."_OBJ->Serialize(\$foreign)";

            $fTable = new \LazyMePHP\DatabaseHelper\_DB_TABLE($field2->GetForeignTable());
            $foreignTablesJoin.=(strlen($foreignTablesJoin)>0?" ":"")."LEFT JOIN ".$fTable->GetTableName()." ".$field2->GetName()."_".$fTable->GetTableName();
            $fTable->GetFieldsFromDB();
            foreach($fTable->GetTableFields() as $field3)
            {
              if ($field3->IsPrimaryKey())
              {
                $foreignTablesJoin.= " ON ".$db->GetTableName().".".$field2->GetName()."=".$field2->GetName()."_".$fTable->GetTableName().".".$field3->GetName();
              }

              fwrite($classFile,($countFields++>0?",":"").$field2->GetName()."_".$fTable->GetTableName().".".$field3->GetName()." AS ".$field2->GetName()."_".$field3->GetName());
            }
            fwrite($classFile,"\":\"\").\"");
          }
        }
        fwrite($classFile," FROM ".$db->_Tablename.(strlen($foreignTablesJoin)>0?" ":"")."\".(\$this->_searchNeedsForeignMembers || \$buildForeignMembers?\"$foreignTablesJoin\":\"\").\""." \".(strlen(\$_sql)>0?\"WHERE \".\$_sql:\"\");");

        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\tAPP::DB_CONNECTION()->Query(\$_sql.\" \".(strlen(\$this->_group)>0?\"GROUP BY \".\$this->_group:\"\").\" \".(strlen(\$this->_order)>0?\"ORDER BY \".\$this->_order:\"\").\" \".(\$this->_limitEnd?(strlen(\$this->_order)==0?\"ORDER BY ".$db->_Tablename.".".$db->_PrimaryFieldName." \":\"\").APP::DB_CONNECTION()->Limit(\$this->_limitEnd, \$this->_limitStart):\"\"), \$rtn, \$this->_args);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\treturn \$rtn->FetchNumberResults();");
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
        fwrite($classFile, "\t * @param (buildForeignMembers)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param (bool) if the output should be Serialized");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param (array) mask array to show/hide fields");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (array) (".$db->_Tablename.")");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function GetList(\$buildForeignMembers=true, \$serialize=false, \$mask=array()) {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_list = array();");
        fwrite($classFile, "\n");
        fwrite($classFile,"\t\t\$this->_sql = \"SELECT ");
        $countFields = 0;
        $foreignTablesJoin = "";
        foreach ($db->_Tablefields as $field2)
        {
          fwrite($classFile,($countFields++>0?",":"").$db->_Tablename.".".$field2->GetName());
          if ($field2->HasForeignKey() && !is_null($field2->GetForeignField()))
          {
            fwrite($classFile, "\".(\$buildForeignMembers?\"");
            $addToConstruct.="\n\t\t\$this->".$field2->GetName()."_OBJ = new ".$field2->GetForeignTable()."(\$this->".$field2->GetName().");";
            $addToSerialize.="\n\t\t\$vars[\"".$field2->GetName()."_OBJ\"]=\$this->".$field2->GetName()."_OBJ->Serialize(\$foreign)";

            $fTable = new \LazyMePHP\DatabaseHelper\_DB_TABLE($field2->GetForeignTable());
            $foreignTablesJoin.=(strlen($foreignTablesJoin)>0?" ":"")."LEFT JOIN ".$fTable->GetTableName()." ".$field2->GetName()."_".$fTable->GetTableName();
            $fTable->GetFieldsFromDB();
            foreach($fTable->GetTableFields() as $field3)
            {
              if ($field3->IsPrimaryKey())
              {
                $foreignTablesJoin.= " ON ".$db->GetTableName().".".$field2->GetName()."=".$field2->GetName()."_".$fTable->GetTableName().".".$field3->GetName();
              }

              fwrite($classFile,($countFields++>0?",":"").$field2->GetName()."_".$fTable->GetTableName().".".$field3->GetName()." AS ".$field2->GetName()."_".$field3->GetName());
            }
            fwrite($classFile,"\":\"\").\"");
          }
        }
        fwrite($classFile," FROM ".$db->_Tablename.(strlen($foreignTablesJoin)>0?" ":"")."\".(\$this->_searchNeedsForeignMembers || \$buildForeignMembers?\"$foreignTablesJoin\":\"\").\""." \".(strlen(\$this->_sql)>0?\"WHERE \".\$this->_sql:\"\");");

        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\tAPP::DB_CONNECTION()->Query(\$this->_sql.\" \".(strlen(\$this->_group)>0?\"GROUP BY \".\$this->_group:\"\").\" \".(strlen(\$this->_order)>0?\"ORDER BY \".\$this->_order:\"\").\" \".(\$this->_limitEnd?(strlen(\$this->_order)==0?\"ORDER BY ".$db->_Tablename.".".$db->_PrimaryFieldName." \":\"\").APP::DB_CONNECTION()->Limit(\$this->_limitEnd, \$this->_limitStart):\"\"), \$rtn, \$this->_args);");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\twhile(\$row = \$rtn->FetchArray())");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t{");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\t\t\$obj = new Priv\__".$db->_Tablename."(\$buildForeignMembers);");
        fwrite($classFile, "\n");
        foreach ($db->_Tablefields as $field2)
        {
          if ($field2->HasForeignKey() && !is_null($field2->GetForeignField()))
          {
            $fTable = new \LazyMePHP\DatabaseHelper\_DB_TABLE($field2->GetForeignTable());
            $fTable->GetFieldsFromDB();
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\tif (\$buildForeignMembers)");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t{");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\t\$_OBJ = new \\LazyMePHP\\Classes\\Priv\\__".$fTable->GetTableName()."();");
            fwrite($classFile, "\n");

            foreach($fTable->GetTableFields() as $field3)
            {
              fwrite($classFile,"\t\t\t\t\t\$_OBJ->_Set".$field3->GetName()."(\$row['".$field2->GetName()."_".$field3->GetName()."']);");
              fwrite($classFile, "\n");
            }
            fwrite($classFile, "\t\t\t\t\t\$_OBJ->constructForeignKeysObjs();");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\t\t\t\$obj->SetMember".$field2->GetName()."_OBJ(\$_OBJ);");
            fwrite($classFile, "\n");

            fwrite($classFile, "\t\t\t\t}");
            fwrite($classFile, "\n");
            fwrite($classFile, "\n");
          }
          fwrite($classFile,"\t\t\t\t\$obj->_Set".$field2->GetName()."(\$row['".$field2->GetName()."']".($field2->GetForeignTable()?",\$buildForeignMembers":"").");");
          fwrite($classFile, "\n");
        }
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\tif (\$serialize) \$this->_list[]=\$obj->Serialize(\$buildForeignMembers, \$mask);");
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
        fwrite($classFile, "\t * @param (buildForeignMembers)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @param (array) mask array to show/hide fields");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (array)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function Serialize(\$buildForeignMembers=true, \$mask = NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t{");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\treturn \$this->GetList(\$buildForeignMembers, true, \$mask);");
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
        fwrite($classFile, "\t * @param (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function ClearFind()");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t{");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_args = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_list = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_sql = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_order = array();");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_group = '';");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_searchNeedsForeignMembers = false;");
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
        fwrite($classFile, "\t * @param (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function FindAll()");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t{");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_sql = \"\";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");

        foreach ($db->_Tablefields as $field)
        {
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
          fwrite($classFile, "\t * @param (string) (\$var)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @operator (string) (\$operator)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @return (NULL)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t */");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function FindBy".$field->GetName()."(\$var, \$operator = '=') {");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t\t\$this->_args[]=\$var;");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t\t\$this->_sql .= \" \".(strlen(\$this->_sql)>0?\"AND\":\"\").\" ".$db->GetTableName().".".$field->GetName()." \$operator \".(\$var || \$operator=='='?\"?\":\"\").\"\";");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t}");
          fwrite($classFile, "\n");

          fwrite($classFile, "\n");
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
          fwrite($classFile, "\t * @param (string) (\$var)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @return (NULL)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t */");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function FindBy".$field->GetName()."Like(\$var) {");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t\t\$this->_args[] = \"%\$var%\";");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t\t\$this->_sql .= \" \".(strlen(\$this->_sql)>0?\"AND\":\"\").\" ".$db->GetTableName().".".$field->GetName()." LIKE ?\";");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t}");
          fwrite($classFile, "\n");
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
          fwrite($classFile, "\t * @param (string) (\$var)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t * @return (NULL)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t */");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function OrderBy".$field->GetName()."(\$var) {");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t\t\$this->_order=\$this->_order.(strlen(\$this->_order)>0?\",\":\"\").\" ".$field->GetName()." \$var\";");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t}");
          fwrite($classFile, "\n");
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
          fwrite($classFile, "\t * @return (NULL)");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t */");
          fwrite($classFile, "\n");
          fwrite($classFile, "\tpublic function GroupBy".$field->GetName()."() {");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t\t\$this->_group=\$this->_group.(strlen(\$this->_group)>0?\",\":\"\").\" ".$field->GetName()."\";");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t}");
          fwrite($classFile, "\n");
        }

        // Find by foreign tables values
        foreach($db->_Tablefields as $field)
        {
          if ($field->GetForeignTable())
          {
            $fTable = new \LazyMePHP\DatabaseHelper\_DB_TABLE($field->GetForeignTable());
            $fTable->GetFieldsFromDB();
            foreach ($fTable->_Tablefields as $field2)
            {
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
              fwrite($classFile, "\t * @param (string) (\$var)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @operator (string) (\$operator)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @return (NULL)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t */");
              fwrite($classFile, "\n");
              fwrite($classFile, "\tpublic function FindBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."(\$var, \$operator = '=') {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_searchNeedsForeignMembers=true;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_args[]=\$var;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_sql .= \" \".(strlen(\$this->_sql)>0?\"AND\":\"\").\" ".$field->GetName()."_".$fTable->GetTableName().".".$field2->GetName()." \$operator ?\";");
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
              fwrite($classFile, "\t * @param (string) (\$var)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @return (NULL)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t */");
              fwrite($classFile, "\n");
              fwrite($classFile, "\tpublic function FindBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."Like(\$var) {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_searchNeedsForeignMembers=true;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_args[] = \"%\$var%\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_sql .= \" \".(strlen(\$this->_sql)>0?\"AND\":\"\").\" ".$field->GetName()."_".$fTable->GetTableName().".".$field2->GetName()." LIKE ?\";");
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
              fwrite($classFile, "\t * @param (string) (\$var)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @return (NULL)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t */");
              fwrite($classFile, "\n");
              fwrite($classFile, "\tpublic function OrderBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."(\$var) {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_searchNeedsForeignMembers=true;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_order=\$this->_order.(strlen(\$this->_order)>0?\",\":\"\").\" ".$field->GetName()."_".$fTable->GetTableName().".".$field2->GetName()." \$var\";");
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
              fwrite($classFile, "\t * Groups last find by ".$fTable->GetTableName().$field2->GetName()." \$var\"");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t *");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t * @return (NULL)");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t */");
              fwrite($classFile, "\n");
              fwrite($classFile, "\tpublic function GroupBy".$field->GetName()."_".$fTable->GetTableName()."_".$field2->GetName()."() {");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_searchNeedsForeignMembers=true;");
              fwrite($classFile, "\n");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t\t\$this->_group=\$this->_group.(strlen(\$this->_group)>0?\",\":\"\").\" ".$field->GetName()."_".$fTable->GetTableName().".".$field2->GetName()." \$var\";");
              fwrite($classFile, "\n");
              fwrite($classFile, "\t}");
              fwrite($classFile, "\n");
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
        fwrite($classFile, "\t * @param (string) (sql)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function CustomFind(\$args) {");
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
        fwrite($classFile, "\t * @param (int) (end)");
        fwrite($classFile, "\t * @param (int) (start)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t * @return (NULL)");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t */");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function Limit(\$end, \$start = 0) {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_limitEnd = \$end;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\t\$this->_limitStart = \$start;");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t}");
        fwrite($classFile, "\n");
        fwrite($classFile,"}");

        fwrite($classFile, "\n");
        fwrite($classFile, "/**");
        fwrite($classFile, "\n");
        fwrite($classFile, " * This is a class that is only used to access protected members (internal use only)");
        fwrite($classFile, "\n");
        fwrite($classFile, " */");
        fwrite($classFile, "\n");
        fwrite($classFile, "namespace LazyMePHP\Classes\Priv;");
        fwrite($classFile, "\n");
        fwrite($classFile, "use \LazyMePHP\Classes\\".$db->_Tablename.";");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "class __".$db->_Tablename." extends ".$db->_Tablename." {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        fwrite($classFile, "\tfunction __construct() {}");
        fwrite($classFile, "\n");
        fwrite($classFile, "\n");
        foreach ($db->_Tablefields as $_field) {
          fwrite($classFile, "\tpublic function _Set".$_field->GetName()."(\$var) {");
          fwrite($classFile, "\n");
          fwrite($classFile, "\t\t\$this->".$_field->GetName()." = \$var;");
          fwrite($classFile, "\n");
          fwrite($classFile,"\t}");
          fwrite($classFile, "\n");
        }
        fwrite($classFile, "\n");
        foreach ($db->_Tablefields as $_field) {
          if ($_field->GetForeignTable())
          {
            fwrite($classFile, "\tpublic function SetMember".$_field->GetName()."_OBJ(\$obj) {");
            fwrite($classFile, "\n");
            fwrite($classFile, "\t\t\$this->".$_field->GetName()."_OBJ=\$obj;");
            fwrite($classFile, "\n");
            fwrite($classFile,"\t}");
          }
        }
        fwrite($classFile, "\n");
        fwrite($classFile, "\tpublic function constructForeignKeysObjs() {");
        fwrite($classFile, "\n");
        fwrite($classFile, "\t\tparent::constructForeignKeysObjs();");
        fwrite($classFile, "\n");
        fwrite($classFile,"\t}");
        fwrite($classFile, "\n");
        fwrite($classFile, "}");
        fwrite($classFile, "\n");
        fwrite($classFile,"?>");
        fclose($classFile);
      }
			else echo "ERROR: Check your permissions to write ".$classesPath."/includes.php";
		}
		else echo "ERROR: Check your permissions to remove ".$classesPath."/includes.php";
	}
}
?>
