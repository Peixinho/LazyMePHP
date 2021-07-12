<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace LazyMePHP\DatabaseHelper;
use \LazyMePHP\Config\Internal\APP;

require_once "Helper.php";

class _DB_FIELD {

	/** @var bool Primary Key */
	protected $_Field_Primary_Key;

	/** @var string Field Name */
	protected $_Field_Name;

    /** @var string Field Data Type */
	protected $_Field_Data_Type;

    /** @var bool Allow Null */
	protected $_Field_Allow_Null;

    /** @var bool AutoIncrement */
	protected $_Field_Auto_Increment;

	/** @var int Index */
	protected $_Field_Index;

	/** @var bool Has Foreign Key */
	protected $_Field_Has_Foreign_Key;

	/** @var string Foreign Key Table */
	protected $_Field_Foreign_Table;

	/** @var string Foreign Key Field */
	protected $_Field_Foreign_Field;

	/** @var int Field data length */
	protected $_Field_Data_Length;

	/**
     * Constructor
     *
     * Class Constructor
     *
     * @param (string) (Field Name)
     * @param (string) (Field Data Type)
     * @return (NULL)
     */
	function __construct($fieldname, $fielddatatype = NULL)
	{
		$this->_Field_Data_Type 		= ($fielddatatype!=NULL?$fielddatatype:NULL);
		$this->_Field_Name 				= $fieldname;
		$this->_Field_Allow_Null		= false;
		$this->_Field_Primary_Key		= false;
		$this->_Field_Auto_Increment	= false;
		$this->_Field_Index				= false;
		$this->_Field_Has_Foreign_Key 	= false;
		$this->_Field_Data_Length		= NULL;
	}

	/**
     * GetName
     *
     * Get Field's Name
     *
     * @param (NULL)
     * @return (string) (Field Name)
     */
	function GetName()
	{
		return $this->_Field_Name;
	}

    /**
     * SetAutoIncrement
     *
     * Set if field is Auto Increment
     *
     * @param (bool) (autoincrement)
     * @return (NULL)
     */
	function SetAutoIncrement($autoincrement)
	{
		$this->_Field_Auto_Increment = $autoincrement;
	}

	/**
     * SetAllowNull
     *
     * Set if Field Allow Null
     *
     * @param (bool) (null)
     * @return (NULL)
     */
	function SetAllowNull($null)
	{
		$this->_Field_Allow_Null = $null;
	}

    /**
     * Allow Null
     *
     * Return if field Allow Null
     *
     * @param (NULL)
     * @return (bool) (null)
     */
	function AllowNull()
	{
		return $this->_Field_Allow_Null;
	}

	/**
     * IsAutoIncrement
     *
     * Returns if field is Auto Increment
     *
     * @param (NULL)
     * @return (bool) (autoincrement)
     */
	function IsAutoIncrement()
	{
		return $this->_Field_Auto_Increment;
	}

    /**
     * SetPrimaryKey
     *
     * Set if Field is Primary Key
     *
     * @param (bool) (primary)
     * @return (NULL)
     */
	function SetPrimaryKey($primary)
	{
		$this->_Field_Primary_Key = $primary;
	}

     /** IsPrimaryKey
     *
     * Returns if field is Primary Key
     *
     * @param (NULL)
     * @return (bool) (primary)
     */
	function IsPrimaryKey()
	{
		return $this->_Field_Primary_Key;
	}

	/**
     * SetDataType
     *
     * Set Field's Data Type
     *
     * @param (string) (data)
     * @return (NULL)
     */
	function SetDataType($data) { $this->_Field_Data_Type = $data; }

    /**
     * GetDataType
     *
     * Get Field's Data Type
     *
     * @param (NULL)
     * @return (string) (data)
     */
	function GetDataType() { return $this->_Field_Data_Type; }

	/**
     * SetDataLength
     *
     * Set Field's Data Length
     *
     * @param (int) (length)
     * @return (NULL)
     */
	function SetDataLength($length) { $this->_Field_Data_Length = $length; }

    /**
     * GetDataLength
     *
     * Get Field's Data Length
     *
     * @param (NULL)
     * @return (int) (length)
     */
	function GetDataLength() { return $this->_Field_Data_Length; }

	/**
     * SetIndex
     *
     * Set Field's Index
     *
     * @param (bool) (index)
     * @return (NULL)
     */
	function SetIndex($index) { $this->_Field_Index = $index; }

    /**
     * GetIndex
     *
     * Get Field's Index
     *
     * @param (NULL)
     * @return (bool) (index)
     */
	function GetIndex() { return $this->_Field_Index; }

	/**
     * SetForeignKey
     *
     * Set Field's ForeignKey
     *
     * @param (string) (foreigntable)
     * @param (string) (foreignfield)
     * @return (NULL)
     */
	function SetForeignKey($foreigntable,$foreignfield)
	{
		$this->_Field_Has_Foreign_Key = true;
		$this->_Field_Foreign_Table = $foreigntable;
		$this->_Field_Foreign_Field = $foreignfield;
	}

    /**
     * HasForeignKey
     *
     * Get if Field has ForeignKey
     *
     * @param (NULL)
     * @return (bool) (foreignkey)
     */
	function HasForeignKey()
	{
		return $this->_Field_Has_Foreign_Key;
	}

    /**
     * GetForeignTable
     *
     * Get Field's Foreign Table
     *
     * @param (NULL)
     * @return (string) (foreigntable)
     */
	function GetForeignTable()
	{
		return $this->_Field_Foreign_Table;
	}

    /**
     * GetForeignField
     *
     * Get Field's Foreign Field
     *
     * @param (NULL)
     * @return (string) (foreignfield)
     */
	function GetForeignField()
	{
		return $this->_Field_Foreign_Field;
	}
}

class _DB_TABLE {

    /** @var array Table Fields */
	protected $_Tablefields = array();

    /** @var string Table Name */
	protected $_Tablename;

    /** @var string Primary Field Name */
	protected $_PrimaryFieldName;

    /**
     * Constructor
     *
     * Class Constructor
     *
     * @param (string) (tablename)
     * @return (NULL)
     */
	function __construct($tablename)
	{
		$this->_Tablename = $tablename;
	}

	/**
	 * GetTableName
	 *
	 * Returns Tablename
	 *
	 */
	function GetTableName()
	{
		return $this->_Tablename;
	}

	/**
	 * GetPrimaryFieldName
	 *
	 * Returns Table Primary Key
	 *
	 */
	function GetPrimaryFieldName()
	{
		return $this->_PrimaryFieldName;
	}

	/**
	 * GetTableFields
	 *
	 * Returns Table Fields
	 *
	 */
	function GetTableFields()
	{
		return $this->_Tablefields;
	}

    /**
     * GetFieldsFromDB
     *
     * Gets Fields from Database
     *
     * @param (NULL)
     * @return (NULL)
     */
	function GetFieldsFromDB()
	{

        // For Each Table DO:
        switch (APP::DB_TYPE())
        {
            case 1: // MSSQL
                $queryString = "SELECT
                                    INFORMATION_SCHEMA.COLUMNS.COLUMN_NAME as [Field],
                                    CASE WHEN INFORMATION_SCHEMA.COLUMNS.IS_NULLABLE = 'YES' THEN 1 ELSE 0 END AS [Null],
                                    INFORMATION_SCHEMA.COLUMNS.COLUMN_DEFAULT as [Default],
                                    INFORMATION_SCHEMA.COLUMNS.DATA_TYPE as [Type],
                                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME as [CNAME],
                                    CASE WHEN INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME LIKE 'PK%' THEN 1 ELSE 0 END AS [PK],
                                    CASE WHEN INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME LIKE 'FK%' THEN 1 ELSE 0 END AS [FK],
                                    COLUMNPROPERTY(object_id(INFORMATION_SCHEMA.COLUMNS.TABLE_NAME),INFORMATION_SCHEMA.COLUMNS.COLUMN_NAME, 'IsIdentity') as [Identity],
									INFORMATION_SCHEMA.COLUMNS.character_maximum_length as [Data_Length]
                                FROM
                                    INFORMATION_SCHEMA.COLUMNS
                                    LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                                        ON
                                            INFORMATION_SCHEMA.COLUMNS.TABLE_NAME=INFORMATION_SCHEMA.KEY_COLUMN_USAGE.TABLE_NAME
                                        AND
                                            INFORMATION_SCHEMA.COLUMNS.COLUMN_NAME=INFORMATION_SCHEMA.KEY_COLUMN_USAGE.COLUMN_NAME
                                WHERE
                                    INFORMATION_SCHEMA.COLUMNS.TABLE_NAME='".$this->_Tablename."'
                                ORDER BY
                                    INFORMATION_SCHEMA.COLUMNS.ORDINAL_POSITION";
            break;
            case 2: // MYSQL
				$queryString = "SELECT DISTINCT `Field`, `Null`, `Default`, `Type`, `CNAME`, `PK`, `FK`, `Identity`, `Data_Length`, `ORDINAL_POSITION` FROM
									(SELECT
										information_schema.COLUMNS.COLUMN_NAME as `Field`,
										CASE WHEN information_schema.COLUMNS.IS_NULLABLE = 'YES' THEN 1 ELSE 0 END AS `Null`,
										information_schema.COLUMNS.COLUMN_DEFAULT as `Default`,
										information_schema.COLUMNS.DATA_TYPE as `Type`,
										information_schema.KEY_COLUMN_USAGE.CONSTRAINT_NAME AS `CNAME`,
										CASE WHEN information_schema.KEY_COLUMN_USAGE.CONSTRAINT_NAME = 'PRIMARY' THEN 1 ELSE 0 END AS `PK`,
										CASE WHEN information_schema.KEY_COLUMN_USAGE.CONSTRAINT_NAME IS NOT NULL AND information_schema.KEY_COLUMN_USAGE.CONSTRAINT_NAME != 'PRIMARY' THEN 1 ELSE 0 END AS `FK`,
										CASE WHEN EXTRA = 'auto_increment' THEN 1 ELSE 0 END as `Identity`,
										information_schema.COLUMNS.CHARACTER_MAXIMUM_LENGTH AS `Data_Length`,
										information_schema.COLUMNS.ORDINAL_POSITION as `ORDINAL_POSITION`
									FROM
										information_schema.COLUMNS
										LEFT JOIN
										information_schema.KEY_COLUMN_USAGE
										ON
										information_schema.KEY_COLUMN_USAGE.COLUMN_NAME = information_schema.COLUMNS.COLUMN_NAME
										AND information_schema.COLUMNS.TABLE_NAME=information_schema.KEY_COLUMN_USAGE.TABLE_NAME
									WHERE
										information_schema.COLUMNS.TABLE_SCHEMA='".APP::DB_NAME()."' AND
										information_schema.COLUMNS.TABLE_NAME='".$this->_Tablename."') SCH ORDER BY `ORDINAL_POSITION` ASC";
            break;
        }
        APP::DB_CONNECTION()->Query($queryString, $sqlObj2);
        while ($f=$sqlObj2->FetchObject())
		{
			$__type = $f->Type;
			switch (APP::DB_TYPE()) {
				case 1:
					switch(strtolower($f->Type))
					{
						case "bigint":
						case "binary":
						case "int":
						case "tinyint":
						case "smallint":
							$__type = "int";
							break;
						case "char":
						case "varchar":
						case "text":
						case "sql_variant":
						case "varbinary":
						case "ntext":
						case "nchar":
						case "nvarchar":
						case "xml":
						case "uniqueidentifier":
						case "sysname":
						case "image":
							$__type = "varchar";
							break;
						case "numeric":
						case "decimal":
						case "money":
						case "real":
						case "smallmoney":
							$__type = "float";
							break;
						case "bit":
							$__type = "bit";
							break;
						case "datetime":
						case "smalldatetime":
						case "timestamp":
							$__type = "date";
					}
					break;
				case 2:
					switch(strtolower($f->Type))
					{
						case "bigint":
						case "int":
						case "tinyint":
						case "smallint":
						case "mediumint":
							$__type = "int";
							break;
						case "char":
						case "varchar":
						case "text":
						case "blob":
						case "tinyblob":
						case "tinytext":
						case "mediumblob":
						case "mediumtext":
						case "longblob":
						case "longtext":
						case "enum":
							$__type = "varchar";
							break;
						case "float":
						case "double":
						case "decimal":
							$__type = "float";
							break;
						case "bit":
							$__type = "bit";
							break;
						case "date":
						case "datetime":
						case "timestamp":
						case "time":
						case "year":
							$__type = "date";
					}
					break;
			}

            $field = new _DB_FIELD($f->Field,$__type);
            $field->SetAllowNull($f->Null);
            $field->SetAutoIncrement($f->Identity);
            $field->SetDataLength($f->Data_Length);
            if ($f->PK)
            {
                $field->SetPrimaryKey(true);
                $this->_PrimaryFieldName = $f->Field;
            }
            if ($f->FK)
            {
                switch (APP::DB_TYPE()) {
                    case 1:

                            $queryStringFK = "  SELECT
                                                     KCU1.CONSTRAINT_NAME AS 'FK_CONSTRAINT_NAME'
                                                   , KCU1.TABLE_NAME AS 'FK_TABLE_NAME'
                                                   , KCU1.COLUMN_NAME AS 'FK_COLUMN_NAME'
                                                   , KCU2.CONSTRAINT_NAME AS 'UQ_CONSTRAINT_NAME'
                                                   , KCU2.TABLE_NAME AS 'UQ_TABLE_NAME'
                                                   , KCU2.COLUMN_NAME AS 'UQ_COLUMN_NAME'
                                                FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS RC
                                                JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE KCU1
                                                ON KCU1.CONSTRAINT_CATALOG = RC.CONSTRAINT_CATALOG
                                                   AND KCU1.CONSTRAINT_SCHEMA = RC.CONSTRAINT_SCHEMA
                                                   AND KCU1.CONSTRAINT_NAME = RC.CONSTRAINT_NAME
                                                JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE KCU2
                                                ON KCU2.CONSTRAINT_CATALOG =
                                                RC.UNIQUE_CONSTRAINT_CATALOG
                                                   AND KCU2.CONSTRAINT_SCHEMA =
                                                RC.UNIQUE_CONSTRAINT_SCHEMA
                                                   AND KCU2.CONSTRAINT_NAME =
                                                RC.UNIQUE_CONSTRAINT_NAME
                                                   AND KCU2.ORDINAL_POSITION = KCU1.ORDINAL_POSITION
                                                WHERE KCU1.TABLE_NAME='$this->_Tablename' AND KCU1.COLUMN_NAME='$f->Field'";
                    break;
                    case 2:
                            $queryStringFK = "  SELECT
                                                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE.REFERENCED_TABLE_NAME AS `UQ_TABLE_NAME`,
                                                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE.REFERENCED_COLUMN_NAME AS `UQ_COLUMN_NAME`
                                                FROM
                                                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                                                WHERE
                                                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE.TABLE_NAME='".$this->_Tablename."' AND
                                                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE.COLUMN_NAME='".$f->Field."'";
                    break;
                }
                APP::DB_CONNECTION()->Query($queryStringFK, $sqlObj3);
                while($fk = $sqlObj3->FetchObject())
                {
                    $field->SetForeignKey($fk->UQ_TABLE_NAME, $fk->UQ_COLUMN_NAME);
                }
            }
            $this->_Tablefields[] = $field;
        }
	}

    /**
     * DebugFields
     *
     * Writes Table Schema for Debugging
     *
     * @param (NULL)
     * @return (NULL)
     */
	function DebugFields()
	{
		echo "<b>".$this->_Tablename."</b><br>";
		foreach ($this->_Tablefields as $field) {
			echo $field->GetName()." - ".$field->GetDataType();
			if ($field->HasForeignKey()) echo " - ForeignKeyTable: ".$field->GetForeignTable()." - ForeignKey Field: ".$field->GetForeignField();
			echo "<br>";
		}
	}
}

?>
