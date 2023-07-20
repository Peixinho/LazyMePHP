<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

use \LazyMePHP\Config\Internal\APP;
session_start();

require_once 'DatabaseHelper.php';
require_once '../../src/Configurations/Configurations.php';
require_once 'ClassesBuilder.php';
require_once 'FormsBuilder.php';
require_once 'APIBuilder.php';

if ((!array_key_exists('username', $_SESSION) || !array_key_exists('password', $_SESSION)) && ($_POST && $_POST['username'] && $_POST['password'] && $_POST['username'] == APP::DB_USER() && $_POST['password'] == APP::DB_PASSWORD()))
{
    $_SESSION['username'] = $_POST['username'];
    $_SESSION['password'] = $_POST['password'];
}

// If user is logged with database credentials
if ($_SESSION && $_SESSION['username'] == APP::DB_USER() && $_SESSION['password'] == APP::DB_PASSWORD()) {

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

    if (!filter_input(INPUT_POST, 'step1')) {
        echo "<img src='../img/logo.png'/>";
        echo "<br>";
        echo "<br>";
        echo "<h3>Tables Found in Database</h3>";
        echo "<form method='post' action=''>";
        $tableText = "<table><tr><td><b>Table Name</b></td><td><b>Table Fields (check what is the default field)</b></td><td width='100'><input id='allClass' type='checkbox' checked onclick='toggleAllClass(this)'><b>Class</b></td><td width='100'><input id='allForm' type='checkbox' checked onclick='toggleAllForm(this)'><b>Form</b></td><td width='100'><input id='allApi' type='checkbox' checked onclick='toggleAllApi(this)'><b>API</b></td></tr>";
        $tableCount = 0;
        APP::DB_CONNECTION()->Query($queryString, $sqlObj);
        while ($o=$sqlObj->FetchObject())
        {
            $table = new \LazyMePHP\DatabaseHelper\_DB_TABLE($o->Table);
            $table->GetFieldsFromDB();
            $havePrinmaryKey = false;
            $tableTextFields = "";
            $tableText .= "<tr ".($tableCount%2==0?"style='background-color:#EEEEEE;'":"")."><td>".$table->GetTableName()."</td>";
            $tableText .= "<td>";
            $fieldCount = 0;
            $tableDescriptorSelected = false;
            foreach($table->GetTableFields() as $i => $field)
            {
                if ($field->IsPrimaryKey()) $havePrimaryKey = true;
                $tableText .= (($fieldCount++)>0?", ":"")."<input type='radio' name='tableDescriptors[".$table->GetTableName()."]' value='".$field->GetName()."' ".(!$field->IsPrimaryKey() && !$field->HasForeignKey() && !$tableDescriptorSelected?"checked":"")."/> ".$field->GetName();
                if (!$field->IsPrimaryKey() && !$field->HasForeignKey()) $tableDescriptorSelected = true;
            }
            echo "</td>";
            if ($havePrimaryKey) $tableText .= "<td><input type='checkbox' class='class' name='class[$tableCount]' value='".$table->GetTableName()."' checked onclick='toggleClass(this)'/></td><td><input type='checkbox' class='form' name='form[".($tableCount++)."]' value='".$table->GetTableName()."' checked onclick='toggleForm(this)' /></td><td><input type='checkbox' class='api' name='api[".($tableCount++)."]' value='".$table->GetTableName()."' checked onclick='toggleApi(this)' /></td>";
        }
        $tableText .= "</table>";
        $tableText .= "<span style='font-size:8pt;'>(check the ones you want to have forms/classes built automatically for you</span>";
        $tableText .= "<br>";
        $tableText .= "<br>";
        $tableText .= "Path defined in config: ".APP::ROOT_PATH()."/";
        $tableText .= "<br>";
        $tableText .= "Classes Path: <input name='classes_path' type='text' value='src/Classes' readonly />";
        $tableText .= "<br>";
        $tableText .= "Controllers Path: <input name='forms_controller_path' type='text' value='src/Controllers' readonly />";
        $tableText .= "<br>";
        $tableText .= "Views Path: <input name='forms_view_path' type='text' value='src/Views' readonly />";
        $tableText .= "<br>";
        $tableText .= "API Path: <input name='api_path' type='text' value='src/api' readonly />";
        $tableText .= "<br>";
        $tableText .= "<br>";

        $tableText .="<b>Form classes</b>";
        $tableText .= "<br>";
        $tableText .= "Button CSS Classes: <input name='forms_class[button]' type='text' value='' />";
        $tableText .= "<br>";
        $tableText .= "Input CSS Classes: <input name='forms_class[input]' type='text' value='' />";
        $tableText .= "<br>";
        $tableText .= "Anchor CSS Classes: <input name='forms_class[anchor]' type='text' value='' />";
        $tableText .= "<br>";
        $tableText .= "Table CSS Classes: <input name='forms_class[table]' type='text' value='' />";
        $tableText .= "<br>";
        $tableText .= "<br>";

        $tableText .= "Replace includes.php (classes) <input type='checkbox' name='replace_include' value='1' checked />";
        $tableText .= "<br>";
        $tableText .= "Replace RouteForms.php (classes) <input type='checkbox' name='replace_routeforms' value='1' checked />";
        $tableText .= "<br>";
        $tableText .= "Replace RouteAPI.php (classes) <input type='checkbox' name='replace_routeapi' value='1' checked />";
        $tableText .= "<br>";
        $tableText .= "<br>";
        $tableText .= "<input name='step1' type='submit' value='Generate' />";
        $tableText .= "</form>";

        echo $tableText;
    }
    if (filter_input(INPUT_POST, 'step1')) {
        echo "<img src='../img/logo.png'/>";
        echo "<br>";
        echo "<br>";
        if (filter_input(INPUT_POST, 'classes_path') &&
        filter_input(INPUT_POST, 'forms_controller_path') &&
        filter_input(INPUT_POST, 'forms_view_path') &&
        filter_input(INPUT_POST, 'api_path')) {
            $helper = new \LazyMePHP\ClassesBuilder\BuildTableClasses(filter_input(INPUT_POST, 'classes_path'), $_POST['class'], filter_input(INPUT_POST, 'replace_include'), $_POST['tableDescriptors']);
            echo "Classes Built!<br>";
            $helper2 = new \LazyMePHP\FormsBuilder\BuildTableForms(filter_input(INPUT_POST, 'forms_controller_path'), filter_input(INPUT_POST, 'forms_view_path'), filter_input(INPUT_POST, 'classes_path'), $_POST['form'], filter_input(INPUT_POST, 'replace_routeforms'), filter_input(INPUT_POST, 'forms_class',FILTER_DEFAULT, FILTER_REQUIRE_ARRAY));
            $helper3 = new \LazyMePHP\FormsBuilder\BuildTableAPI(filter_input(INPUT_POST, 'api_path'), filter_input(INPUT_POST, 'replace_routeapi'));
            echo "Api Built!<br>";
        } else echo "Paths not available";
        echo "Classes and Forms Built!<br>";
    }
} else {
  session_destroy();
  // Show login if user not logged
  echo "<img src='../img/logo.png'/>";
  echo "<br>";
  echo "<br>";
  echo "<b>Login in with database credentials to continue</b>";
  echo "<br />";
  echo "<br />";
  echo "<form method='POST' action=''>";
  echo "<b>User:</b>";
  echo "<br />";
  echo "<input type='text' name='username'/>";
  echo "<br />";
  echo "<b>Password:</b>";
  echo "<br />";
  echo "<input type='password' name='password'/>";
  echo "<br />";
  echo "<br />";
  echo "<input type='submit' value='Login' />";
  echo "</form>";
}

?>
<script>
function toggleAllClass(d)
{
	var x = document.getElementsByClassName("class");
	for (var i = 0; i < x.length; i++) {
		x[i].checked = d.checked;
	}
}
function toggleAllForm(d)
{
	var x = document.getElementsByClassName("form");
	for (var i = 0; i < x.length; i++) {
		x[i].checked = d.checked;
	}
}
function toggleAllApi(d)
{
	var x = document.getElementsByClassName("api");
	for (var i = 0; i < x.length; i++) {
		x[i].checked = d.checked;
	}
}
function toggleClass(d)
{
	if (d.checked)
	{
		var unchecked = false;
		var x = document.getElementsByClassName("class");
		for (var i = 0; i < x.length; i++) {
			if (!x[i].checked) unchecked = true;
		}
		if (!unchecked) document.getElementById("allClass").checked = true;
	} else {
		document.getElementById("allClass").checked = false;
	}
}
function toggleForm(d)
{
	if (d.checked)
	{
		var unchecked = false;
		var x = document.getElementsByClassName("form");
		for (var i = 0; i < x.length; i++) {
			if (!x[i].checked) unchecked = true;
		}
		if (!unchecked) document.getElementById("allForm").checked = true;
	} else {
		document.getElementById("allForm").checked = false;
	}
}
function toggleApi(d)
{
	if (d.checked)
	{
		var unchecked = false;
		var x = document.getElementsByClassName("api");
		for (var i = 0; i < x.length; i++) {
			if (!x[i].checked) unchecked = true;
		}
		if (!unchecked) document.getElementById("allApi").checked = true;
	} else {
		document.getElementById("allApi").checked = false;
	}
}
</script>
