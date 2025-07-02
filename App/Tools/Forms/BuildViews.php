<?php

namespace Tools\Forms;
use Core\LazyMePHP;

require_once __DIR__ . '/../Database';
require_once __DIR__ . '/../Helper';

class BuildViews {
    
    public function __construct($viewsPath, $db) {
        $this->constructForm($viewsPath, $db);
    }

    protected function constructForm($viewsPath, $db)
    {
        // Create Folder if doesn't exist
        if (!is_dir($viewsPath)) \Tools\Helper\MKDIR($viewsPath);

        // Create Table Folder
        if (!is_dir($viewsPath."/".$db->GetTableName())) \Tools\Helper\MKDIR($viewsPath."/".$db->GetTableName());

        if (
        \Tools\Helper\UNLINK($viewsPath."/".$db->GetTableName()."/template.blade.php") &&
          \Tools\Helper\UNLINK($viewsPath."/".$db->GetTableName()."/index.blade.php") &&
          \Tools\Helper\UNLINK($viewsPath."/".$db->GetTableName()."/edit.blade.php")
      ) {
            if (
            \Tools\Helper\TOUCH($viewsPath."/".$db->GetTableName()."/template.blade.php") &&
              \Tools\Helper\TOUCH($viewsPath."/".$db->GetTableName()."/index.blade.php") &&
              \Tools\Helper\TOUCH($viewsPath."/".$db->GetTableName()."/edit.blade.php")
            ) {

                $viewFile = fopen($viewsPath."/".$db->GetTableName()."/template.blade.php","w+");
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
                fwrite($viewFile, "declare(strict_types=1);");
                fwrite($viewFile, "\n");
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

                $viewFile = fopen($viewsPath."/".$db->GetTableName()."/edit.blade.php","w+");
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
                fwrite($viewFile, "declare(strict_types=1);");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "\n");
                fwrite($viewFile," ?>");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "@include('".$db->GetTableName().".template')\n");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "@component('_Components.Anchor',array('href' => '/".$db->GetTableName()."/new', 'link' => 'Add New'))\n");
                fwrite($viewFile, "@endcomponent");
                fwrite($viewFile, "\t<br/>");
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
                fwrite($viewFile, "<form id='".$db->GetTableName()."Form' method='POST' action='/".$db->GetTableName()."/{!!\$".$db->GetTableName()."->Get".ucfirst($field->GetName())."()!!}'>");
                foreach ($db->GetTableFields() as $field) {
                  if (!$field->IsAutoIncrement() || !$field->IsPrimaryKey())
                  {
                    if ($field->HasForeignKey() && !is_null($field->GetForeignField()))
                    {
                      fwrite($viewFile, "\n");
                      fwrite($viewFile, "\t@component('_Components.Select',array('name'=> '".$field->GetName()."', 'fieldname' => '".$field->GetName()."', 'defaultValueEmpty' => true, 'options' => \$".$field->GetForeignTable()."->GetList(), 'selected' => \$".$db->GetTableName()."->Get".ucfirst($field->GetName())."() ".(!$field->AllowNull()?", 'validation' => 'notnull', 'validationfail' => '".$field->GetName()." cannot be empty'":"")." ))");
                    } else {
                      fwrite($viewFile, "\n");
                      switch ($field->GetDataType())
                      {
                        case "bool":
                        case "bit":
                          fwrite($viewFile, "\t@component('_Components.Checkbox',array('name'=> '".$field->GetName()."', 'fieldname' => '".$field->GetName()."', 'placeholder' => '".$field->GetName()."', 'type' => 'checkbox', 'value' => '1', 'checked' => (\$".$db->GetTableName()."->Get".ucfirst($field->GetName())."()==1?'checked':'')))");
                          break;
                        case "date":
                          fwrite($viewFile, "\t@component('_Components.TextInput',array('name'=> '".$field->GetName()."', 'fieldname' => '".$field->GetName()."', 'placeholder' => '".$field->GetName()."', 'type' => 'date', 'value' => \$".$db->GetTableName()."->Get".ucfirst($field->GetName())."() ".(!$field->AllowNull()?", 'validation' => 'notnull', 'validationfail' => '".$field->GetName()." cannot be empty'":"")." ))");
                          break;
                        case "float":
                        case "int":
                          fwrite($viewFile, "\t@component('_Components.TextInput',array('name'=> '".$field->GetName()."', 'fieldname' => '".$field->GetName()."', 'placeholder' => '".$field->GetName()."', 'type' => 'number', 'value' => \$".$db->GetTableName()."->Get".ucfirst($field->GetName())."() ".(!$field->AllowNull()?", 'validation' => 'notnull', 'validationfail' => '".$field->GetName()." cannot be empty'":"")." ".($field->GetDataLength()?", 'maxlength' => '".$field->GetDataLength()."'":"")."))");
                          break;
                        case "string":
                        default:
                          fwrite($viewFile, "\t@component('_Components.TextInput',array('name'=> '".$field->GetName()."', 'fieldname' => '".$field->GetName()."', 'placeholder' => '".$field->GetName()."', 'type' => 'text', 'value' => \$".$db->GetTableName()."->Get".ucfirst($field->GetName())."() ".(!$field->AllowNull()?", 'validation' => 'notnull', 'validationfail' => '".$field->GetName()." cannot be empty'":"")."".($field->GetDataLength()?", 'maxlength' => '".$field->GetDataLength()."'":"")."))");
                          break;
                      }
                    }
                    fwrite($viewFile, "\n");
                    fwrite($viewFile, "\t@endcomponent");
                    fwrite($viewFile, "\n");
                  }
                }
                fwrite($viewFile, "\t<br/>");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "@component('_Components.CSRF') @endcomponent");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "@component('_Components.Button',array('type' => 'submit', 'name' => 'Save'))");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "@endcomponent");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "@component('_Components.Button',array('type' => 'button', 'name' => 'Cancel', 'onclick' => 'window.open(\"/".$db->GetTableName()."\",\"_self\");'))");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "@endcomponent");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "</form>");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "<script>");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "function Init() { LazyMePHP.InitFormValidation('#".$db->GetTableName()."Form'); }");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "</script>");
                fclose($viewFile);

                // Show List
                $viewFile = fopen($viewsPath."/".$db->GetTableName()."/index.blade.php","w+");
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
                fwrite($viewFile, "@component('_Components.Anchor',array('href' => '/".$db->GetTableName()."/new', 'link' => 'Add New'))");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "@endcomponent");
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
                    fwrite($viewFile, "\t@component('_Components.Select',array('name'=> 'FindBy".$field->GetName()."', 'fieldname' => '".$field->GetName()."', 'defaultValueEmpty' => true, 'options' => \$".$field->GetForeignTable()."->GetList(), 'selected' => (isset(\$filters['FindBy".$field->GetName()."'])?\$filters['FindBy".$field->GetName()."']:'')))");
                    fwrite($viewFile, "\n");
                    fwrite($viewFile, "\t@endcomponent");
                    fwrite($viewFile, "\n");

                    $haveForeignMembers = true;
                  }
                }

                if ($haveForeignMembers)
                {
                  fwrite($viewFile, "\t<br/>");
                  fwrite($viewFile, "\n");
                  fwrite($viewFile, "\t@component('_Components.Button',array('type' => 'submit', 'name' => 'Filter'))");
                  fwrite($viewFile, "\n");
                  fwrite($viewFile, "\t@endcomponent</td>");
                  fwrite($viewFile, "\n");
                  fwrite($viewFile, "\n");
                  fwrite($viewFile, "\t@component('_Components.CSRF') @endcomponent");
                  fwrite($viewFile, "\n");
                  fwrite($viewFile, "\n");
                  fwrite($viewFile, "</form>");
                  fwrite($viewFile, "\n");
                  fwrite($viewFile, "<br/>");
                }

                fwrite($viewFile, "\n");
                fwrite($viewFile, "<table>");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "\t<tr>");
                fwrite($viewFile, "\n");
                if ($primaryKey) {
                  fwrite($viewFile, "\t\t<th><b>edit</b></th>");
                  fwrite($viewFile, "\n");
                  fwrite($viewFile, "\t\t<th><b>delete</b></th>");
                }
                fwrite($viewFile, "\n");
                $processedFields = [];
                foreach ($db->GetTableFields() as $field) {
                  if (!in_array($field->GetName(), $processedFields)) {
                    fwrite($viewFile, "\t\t<th><b>".$field->GetName()."</b></th>");
                    fwrite($viewFile, "\n");
                    $processedFields[] = $field->GetName();
                  }
                }
                fwrite($viewFile, "\t</tr>");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "\t@foreach(\$".$db->GetTableName()."->GetList() as \$member)");
                fwrite($viewFile, "\n");
                fwrite($viewFile, "\t<tr>");
                fwrite($viewFile, "\n");
                if ($primaryKey) {
                  fwrite($viewFile, "\t\t<td><a href='/".$db->GetTableName()."/{{ \$member->Get".ucfirst($primaryKey->GetName())."() }}/edit'>");
                  fwrite($viewFile, "@component('_Components.Button', ['type' => 'button', 'name' => 'edit'])");
                  fwrite($viewFile, "@endcomponent</a></td>\n");
                  fwrite($viewFile, "\t\t<td>");
                  fwrite($viewFile, "<form method='POST' action='/".$db->GetTableName()."/{{ \$member->Get".ucfirst($primaryKey->GetName())."() }}/delete' style='display: inline;'>\n");
                  fwrite($viewFile, "\t\t\t@component('_Components.CSRF') @endcomponent\n");
                  fwrite($viewFile, "\t\t\t@component('_Components.Button',\n");
                  fwrite($viewFile, "\t\t\t\t['type' => 'submit',\n");
                  fwrite($viewFile, "\t\t\t\t'name' => 'delete',\n");
                  fwrite($viewFile, "\t\t\t\t'onclick' => 'return confirm(\"Are you sure you want to delete this item?\")'])\n");
                  fwrite($viewFile, "\t\t\t@endcomponent\n");
                  fwrite($viewFile, "\t\t</form></td>\n");
                }
                fwrite($viewFile, "\n");
                $processedDataFields = [];
                foreach ($db->GetTableFields() as $field) {
                  if ($primaryKey && !in_array($field->GetName(), $processedDataFields)) { 
                    if ($field->HasForeignKey() && !$field->IsPrimaryKey()) 
                    fwrite($viewFile, "\t\t<td>{{\$member->get".ucfirst($field->GetName())."_OBJ() ? \$member->get".ucfirst($field->GetName())."_OBJ()->GetDescriptor() : ''}}</td>");
                    else
                    fwrite($viewFile, "\t\t<td>{{\$member->Get".ucfirst($field->GetName())."()}}</td>");

                    fwrite($viewFile, "\n");
                    $processedDataFields[] = $field->GetName();
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
                fwrite($viewFile, "@endcomponent");
                fwrite($viewFile, "\n");
            }
            else echo "ERROR: Check your permissions to write ".$viewsPath."/".$db->GetTableName().".blade files\n";
        }
        else echo "ERROR: Check your permissions to remove ".$viewsPath."/".$db->GetTableName().".blade filesn";
    }
} 