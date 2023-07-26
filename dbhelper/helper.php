<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

use \LazyMePHP\Config\Internal\APP;

require_once __DIR__.'/DatabaseHelper.php';
require_once __DIR__.'/../src/Configurations/Configurations.php';
require_once __DIR__.'/ClassesBuilder.php';
require_once __DIR__.'/FormsBuilder.php';
require_once __DIR__.'/APIBuilder.php';

function clear() {
  system('clear');
  echo "***************************************************************************************\n";
  echo "************************************* LazyMePHP ***************************************\n";
  echo "***************************************************************************************\n";
  echo "\n";
}

function printtables($title, $options, $selected, $addinfo = null) {
  clear();
  echo "$title\n\n";
  echo str_pad("Table List", 40)."\t\t\tDescriptor\n";
  foreach($options as $i => $o) echo str_pad(($i+1).": ".$o.($selected && $o==$selected?" *":""),40).($addinfo?" \t\t\t[".$addinfo[$o]."] ":"")."\n";

}

function select($title, $options, $selected = null, $multiSelection = false, $addinfo = null) {
  printtables($title, $options, $selected, $addinfo);
  $selected = false;
  while(strtolower($selected)!='q')
  {
    if ($multiSelection) echo "\nSelect the appropriate numbers separated by commas and/or spaces, or leave input blank to select all options shown (Enter 'q' to cancel): ";
    else echo "\nPlease, select the appropriate number ('q' to cancel): ";
    $selected = read();
    if (intval($selected) || ($multiSelection && (strpos($selected,',') || strtolower($selected) == 'a' || strlen($selected)==0)))
    {
      if ($multiSelection) {
        if (strlen($selected)==0) $selected = 'a';
        if (strpos($selected,',')) {
          $selection = array();
          foreach(explode(',',$selected) as $s) {
            if (intval($s) && intval($s)-1<count($options))
            $selection[] = ['value' => intval($s)-1, 'name' => $options[intval($s)-1]];
            else echo "Invalid option $s\n";
          }
        } else {
          if (strtolower($selected)=='a') {
            $selection = array();
            foreach($options as $i => $o) $selection[] = ['value' => intval($i), 'name' => $o];
            return $selection;
          } else {
            if (intval($selected)) return array(['value' => (intval($selected)-1), 'name' => $options[(intval($selected)-1)]]);
          }
        }
        return $selection;
      } else {
        if (intval($selected)) return ['value' => intval($selected)-1, 'name' => $options[intval($selected)-1]];
      }
    } else if (strtolower($selected) != 'q') $selected = false;
  }
  return false;
}

function promptYesNo($message) {
  $o = false;
  while(strtolower($o)!='y' && strtolower($o)!='n') {
    echo $message." [Y/n]: ";
    $o = read();
    if (strlen($o)==0) return true;
    switch(strtolower($o)) {
      case 'y':
        return true;
        break;
      case 'n':
        return false;
        break;
      default:
      $o = false;
      break;
    }
  }
}

function read() {
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  return trim($line);
}

function enableLogging() {
  // Create Activity Log Structure if Needed
  if (APP::APP_ACTIVITY_LOG()) {
    $queryString = "
    CREATE TABLE IF NOT EXISTS `__LOG_ACTIVITY` (
    `id` int(255) NOT NULL,
    `date` datetime NOT NULL,
    `user` varchar(255) DEFAULT NULL,
    `method`varchar(10) DEFAULT NULL);
    CREATE TABLE IF NOT EXISTS `__LOG_ACTIVITY_OPTIONS` (
    `id` int(255) NOT NULL,
    `id_log_activity` int(255) NOT NULL,
    `subOption` varchar(255) NOT NULL,
    `value` varchar(255) NOT NULL);
    CREATE TABLE IF NOT EXISTS `__LOG_DATA` (
    `id` int(255) NOT NULL,
    `id_log_activity` int(255) NOT NULL,
    `table` varchar(255) NOT NULL,
    `pk` varchar(255) NULL,
    `method` varchar(1) NULL,
    `field` varchar(255) NOT NULL,
    `dataBefore` varchar(255) NULL,
    `dataAfter` varchar(255) NULL);
    ALTER TABLE `__LOG_ACTIVITY`
    ADD PRIMARY KEY (`id`);
    ALTER TABLE `__LOG_ACTIVITY_OPTIONS`
    ADD PRIMARY KEY (`id`),
    ADD KEY `id_log_activity` (`id_log_activity`);
    ALTER TABLE `__LOG_DATA`
    ADD PRIMARY KEY (`id`),
    ADD KEY `id_log_activity` (`id_log_activity`);
    ALTER TABLE `__LOG_ACTIVITY`
    MODIFY `id` int(255) NOT NULL AUTO_INCREMENT;
    ALTER TABLE `__LOG_ACTIVITY_OPTIONS`
    MODIFY `id` int(255) NOT NULL AUTO_INCREMENT;
    ALTER TABLE `__LOG_DATA`
    MODIFY `id` int(255) NOT NULL AUTO_INCREMENT;
    ALTER TABLE `__LOG_ACTIVITY_OPTIONS`
    ADD CONSTRAINT `__LOG_ACTIVITY_OPTIONS_ibfk_1` FOREIGN KEY (`id_log_activity`) REFERENCES `__LOG_ACTIVITY` (`id`);
    ALTER TABLE `__LOG_DATA`
    ADD CONSTRAINT `__LOG_DATA_ibfk_1` FOREIGN KEY (`id_log_activity`) REFERENCES `__LOG_ACTIVITY` (`id`);
    ";
    APP::DB_CONNECTION()->Query($queryString, $sqlObj);
    APP::DB_CONNECTION()->Close();
    echo "Done enabling logging, press enter to continue...";
    read();
  }
}

// Read All Tables From Database
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

// Fetch all tables descriptors
APP::DB_CONNECTION()->Query($queryString, $sqlObj);
$tables = array();
$tablesDescriptors = array();
$i = 1;
while ($o=$sqlObj->FetchObject()) {
  $table = new \LazyMePHP\DatabaseHelper\_DB_TABLE($o->Table);
  $table->GetFieldsFromDB();
  $tableDescriptorSelected = false;
  foreach($table->GetTableFields() as $field)
  {
    if (!$field->IsPrimaryKey() && !$field->HasForeignKey()) {
      $tableDescriptorSelected = $field->GetName();
      break;
    }
  }
  if (!$tableDescriptorSelected) $tableDescriptorSelected = $table->GetPrimaryFieldName();
  $tables[] = $o->Table;
  $tablesDescriptors[$o->Table] = $tableDescriptorSelected;
}


$option = -1;
while (strlen($option)>0) {
  printtables("Tables", $tables, null, $tablesDescriptors);
  echo "Select your option:\n\n";
  echo "Change Descriptor [c]\n";
  echo "Enable Logging [e]\n";
  echo "Proceed with build [enter]\n";
  $option = read();
  if (strtolower($option) == 'c') {
    $descriptor = select("Select Table to change descriptor", $tables, null, false, $tablesDescriptors);
    if ($descriptor) {
      $table = new \LazyMePHP\DatabaseHelper\_DB_TABLE($descriptor['name']);
      $table->GetFieldsFromDB();
      $fields = array();
      foreach($table->GetTableFields() as $f) $fields[] = $f->GetName();
      $sel = select("Select field", $fields, $tablesDescriptors[$descriptor['name']],false);
      $tablesDescriptors[$descriptor['name']] = $sel['name'];
    }
  } else if (strtolower($option) == 'e') {
    enableLogging();
  }
}

// Classes
clear();
$classes = select("Select Tables to build classes", $tables, null, true, $tablesDescriptors);
if ($classes) {
  clear();
  echo "Tables selected for building classes:\n\n";
  echo "\nTable name\n";
  foreach($classes as $c) echo $c['name']."\n";
  $replaceI = false;
  while(strtoupper($replaceI) != "Y" && strtoupper($replaceI) != 'N') {
    echo "\nReplace includes.php? Y\\n: ";
    $proceed = trim(fgets(STDIN));
    if (!$replaceI) $replaceI = 'Y';
  }
  $proceed = false;
  while(strtoupper($proceed) != "Y" && strtoupper($proceed) != 'N') {
    echo "\nProceed? Y\\n: ";
    $proceed = trim(fgets(STDIN));
    if (!$proceed) $proceed = 'Y';
  }
  if ($proceed) new \LazyMePHP\ClassesBuilder\BuildTableClasses('src/Classes', $classes, $replaceI=='Y', $tablesDescriptors);
}
// Forms
clear();
$forms = select("Select Tables to build forms", $tables, null, true, $tablesDescriptors);
if ($forms) {
  clear();
  echo "Tables selected for building forms:\n\n";
  echo "\nTable name\n";
  foreach($forms as $f) echo $f['name']."\n";
  echo "\nInsert custom button class: ";
  $cssbutton = trim(fgets(STDIN));
  echo "\nInsert custom input class: ";
  $cssinput = trim(fgets(STDIN));
  echo "\nInsert custom anchor link class: ";
  $cssanchor = trim(fgets(STDIN));
  echo "\nInsert custom table class: ";
  $csstable = trim(fgets(STDIN));
  $replaceR = promptYesNo("Replace RouteForms");
  $proceed = promptYesNo("Proceed");
  if ($proceed) new \LazyMePHP\FormsBuilder\BuildTableForms('src/Controllers', 'src/Views', 'src/Classes', $forms, $replaceR == 'Y',array("button"=>$cssbutton, "input"=>$cssinput, "anchor"=>$cssanchor, "table"=>$csstable));
}
// API
clear();
$api = select("Select Tables to build API", $tables, null, true, $tablesDescriptors);
if ($api) {
  clear();
  echo "Tables selected for building API:\n\n";
  echo "\nTable name\n";
  foreach($api as $a) echo "\t".$a['name']."\n";
  $replaceA = false;
  while(strtoupper($replaceA) != "Y" && strtoupper($replaceA) != 'N') {
    echo "\nReplace RouteAPI.php? Y\\n: ";
    $proceed = trim(fgets(STDIN));
    if (!$replaceA) $replaceA = 'Y';
  }
  $proceed = false;
  while(strtoupper($proceed) != "Y" && strtoupper($proceed) != 'N') {
    echo "\nProceed? Y\\n: ";
    $proceed = trim(fgets(STDIN));
    if (!$proceed) $proceed = 'Y';
  }
  if ($proceed) new \LazyMePHP\FormsBuilder\BuildTableAPI('src/api', $replaceA=='Y');
}
echo "\nDone, press enter to finish...";
read();

?>

