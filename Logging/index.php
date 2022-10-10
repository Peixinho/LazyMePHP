<?php
/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * Source File Generated Automatically
 */

namespace LazyMePHP\Forms;
use \LazyMePHP\Config\Internal\APP;
session_start();

require_once "../src/php/Configurations/Configurations.php";
require_once "../src/php/Classes/includes.php";

if ((!array_key_exists('username', $_SESSION) || !array_key_exists('password', $_SESSION)) && ($_POST && $_POST['username'] && $_POST['password'] && $_POST['username'] == APP::DB_USER() && $_POST['password'] == APP::DB_PASSWORD()))
{
    $_SESSION['username'] = $_POST['username'];
    $_SESSION['password'] = $_POST['password'];
}

// If user is logged with database credentials
if ($_SESSION && $_SESSION['username'] == APP::DB_USER() && $_SESSION['password'] == APP::DB_PASSWORD()) {

$sqlFilter="";
if (filter_input(INPUT_POST,'date1') && filter_input(INPUT_POST,'date2')) {
  $level1 = true;
  $sqlFilter = "(LA.date BETWEEN '".filter_input(INPUT_POST,'date1')."' AND '".filter_input(INPUT_POST,'date2')."')";
}
if (filter_input(INPUT_POST,'user')) {
  $level1 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LA.user='".filter_input(INPUT_POST,'user')."'";
}
if (filter_input(INPUT_POST,'method')) {
  $level1 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LA.method='".filter_input(INPUT_POST,'method')."'";
}
$level2 = false;
if (filter_input(INPUT_POST,'arg')) {
  $level2 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LAO.subOption='".filter_input(INPUT_POST,'arg')."'";
}
if (filter_input(INPUT_POST,'value')) {
  $level2 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LAO.value='".filter_input(INPUT_POST,'value')."'";
}
$level3 = false;
if (filter_input(INPUT_POST,'table')) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.table='".filter_input(INPUT_POST,'table')."'";
}
if (filter_input(INPUT_POST,'pk')) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.pk='".filter_input(INPUT_POST,'pk')."'";
}
if (filter_input(INPUT_POST,'method2')) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.method='".filter_input(INPUT_POST,'method2')."'";
}
if (filter_input(INPUT_POST,'field')) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.field='".filter_input(INPUT_POST,'field')."'";
}
if (filter_input(INPUT_POST,'initialValue')) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.dataBefore='".filter_input(INPUT_POST,'initialValue')."'";
}
if (filter_input(INPUT_POST,'changedTo')) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.dataAfter='".filter_input(INPUT_POST,'initialValue')."'";
}

echo "<h3>Logging</h3>";
$count = 0;

$sql = "
SELECT 
SUM(LOG_ACTIVITY_NR) AS LOG_ACTIVITY_NR,
SUM(LOG_ACTIVITY_OPTIONS_NR) AS LOG_ACTIVITY_OPTIONS_NR,
SUM(LOG_ACTIVITY_DATA_NR) AS LOG_ACTIVITY_DATA_NR
FROM (
	SELECT COUNT(LA.id) AS LOG_ACTIVITY_NR, 0 AS LOG_ACTIVITY_OPTIONS_NR, 0 as LOG_ACTIVITY_DATA_NR
	FROM __LOG_ACTIVITY LA ".
  ($level2?" RIGHT JOIN __LOG_ACTIVITY_OPTIONS LAO ON LA.id=LAO.id_log_activity":"").
  ($level3?" RIGHT JOIN __LOG_DATA LD ON LA.id=LD.id_log_activity":"").
  (strlen($sqlFilter)>0?" WHERE $sqlFilter ":"")
	." UNION ALL 
	SELECT 0 AS LOG_ACTIVITY_NR, COUNT(LA.id) AS LOG_ACTIVITY_OPTIONS_NR, 0 as LOG_ACTIVITY_DATA_NR
	FROM __LOG_ACTIVITY LA
	LEFT JOIN __LOG_ACTIVITY_OPTIONS LAO ON LA.id = LAO.id_log_activity".
  ($level3?" RIGHT JOIN __LOG_DATA LD ON LA.id=LD.id_log_activity":"").
  (strlen($sqlFilter)>0?" WHERE $sqlFilter ":"")
	." UNION ALL
	SELECT 0 AS LOG_ACTIVITY_NR, 0 AS LOG_ACTIVITY_OPTIONS_NR, COUNT(LA.id) as LOG_ACTIVITY_DATA_NR
	FROM __LOG_ACTIVITY LA
	LEFT JOIN __LOG_ACTIVITY_OPTIONS LAO ON LA.id = LAO.id_log_activity
	LEFT JOIN __LOG_DATA LD ON LA.id = LD.id_log_activity".
  (strlen($sqlFilter)>0?" WHERE $sqlFilter ":"")
.") LOGGING
";
APP::DB_CONNECTION()->Query($sql, $rtn);
while($o = $rtn->FetchObject()) {
  $countLogActivity = $o->LOG_ACTIVITY_NR;
  $countLogActivityOptions = $o->LOG_ACTIVITY_OPTIONS_NR;
  $countLogActivityData = $o->LOG_ACTIVITY_DATA_NR;
}

$count = max($countLogActivity, $countLogActivityData);
$page = (\array_key_exists('page', $_GET)?$_GET['page']:1);
$limit = 1000;
$countPage = ($count / $limit);
$countPage+=(($count % $limit) != 0 ? 1:0);

$sql = "
SELECT
  LA.id AS LA_ID,
  LA.date AS LA_DATE,
  LA.user AS LA_USER,
  LA.method AS LA_METHOD,
  LAO.id AS LAO_ID,
  LAO.id_log_activity AS LAO_ID_LOG_ACTIVITY,
  LAO.subOption AS LAO_SUBOPTION,
  LAO.value AS LAO_VALUE,
  '' AS LD_ID,
  '' AS LD_ID_LOG_ACTIVITY,
  '' AS LD_TABLE,
  '' AS LD_PK,
  '' AS LD_METHOD,
  '' AS LD_FIELD,
  '' AS LD_DATABEFORE,
  '' AS LD_DATAAFTER,
  'LAO' as type
FROM
  __LOG_ACTIVITY LA
  RIGHT JOIN (
	SELECT id FROM __LOG_ACTIVITY ORDER BY id DESC LIMIT ".($page-1)*$limit.", $limit
  ) A ON A.id = LA.id
  LEFT JOIN __LOG_ACTIVITY_OPTIONS LAO ON LA.id=LAO.id_log_activity".
  ($level3?" RIGHT JOIN __LOG_DATA LD ON LA.id=LD.id_log_activity":"").
  (strlen($sqlFilter)>0?" WHERE $sqlFilter ":"")
  ." UNION ALL
  SELECT
  LA.id AS LA_ID,
  LA.date AS LA_DATE,
  LA.user AS LA_USER,
  LA.method AS LA_METHOD,
  '' AS LAO_ID,
  '' AS LAO_ID_LOG_ACTIVITY,
  '' AS LAO_SUBOPTION,
  '' AS LAO_VALUE,
  LD.id AS LD_ID,
  LD.id_log_activity AS LD_ID_LOG_ACTIVITY,
  LD.table AS LD_TABLE,
  LD.pk AS LD_PK,
  LD.method AS LD_METHOD,
  LD.field AS LD_FIELD,
  LD.dataBefore AS LD_DATABEFORE,
  LD.dataAfter AS LD_DATAAFTER,
  'LD' AS type
FROM
  __LOG_ACTIVITY LA ".
  ($level2?" RIGHT JOIN __LOG_ACTIVITY_OPTIONS LAO ON LA.id=LAO.id_log_activity":"")."
  RIGHT JOIN (
	SELECT id FROM __LOG_ACTIVITY ORDER BY id DESC LIMIT ".($page-1)*$limit.", $limit
  ) A ON A.id = LA.id
  LEFT JOIN __LOG_DATA LD ON LD.id_log_activity = LA.id".
  (strlen($sqlFilter)>0?" WHERE $sqlFilter ":"")
  ." ORDER BY LA_ID DESC
  ";
APP::DB_CONNECTION()->Query($sql, $rtn);

echo "<form method='POST' action=''>";
// Filter
echo "<h3>Level 1</h3>";
echo "Initial Date:<br> ";
echo "<input type='text' name='date1' id='date1' value='".filter_input(INPUT_POST,'date1')."' />";
echo "<br>";
echo "Ending Date:<br> ";
echo "<input type='text' name='date2' id='date2' value='".filter_input(INPUT_POST,'date2')."' />";
echo "<br>";
echo "User:<br> ";
echo "<input type='text' name='user' id='user' value='".filter_input(INPUT_POST,'user')."' />";
echo "<br>";
echo "Request:<br> ";
echo "<select name='method' id='method'><option value=''>-</option><option value='GET' ".(filter_input(INPUT_POST,'method')=='GET'?'selected':'').">GET</option><option value='POST' ".(filter_input(INPUT_POST,'method')=='POST'?'selected':'').">POST</option><option value='PUT' ".(filter_input(INPUT_POST,'method')=='PUT'?'selected':'').">PUT</option><option value='PATCH' ".(filter_input(INPUT_POST,'method')=='PATCH'?'selected':'').">PATCH</option><option value='DELETE' ".(filter_input(INPUT_POST,'method')=='DELETE'?'selected':'').">DELETE</option></select>";


echo "<br>";
echo "<br>";
echo "<h3>Level 2</h3>";
echo "Arg:<br> ";
echo "<input type='text' name='arg' id='arg' value='".filter_input(INPUT_POST,'arg')."' />";
echo "<br>";
echo "Value:<br> ";
echo "<input type='text' name='value' id='value' value='".filter_input(INPUT_POST,'value')."' />";


echo "<br>";
echo "<br>";
echo "<h3>Level 3</h3>";
echo "Table:<br> ";
echo "<input type='text' name='table' id='table' value='".filter_input(INPUT_POST,'table')."' />";
echo "<br>";
echo "PK:<br> ";
echo "<input type='text' name='pk' id='pk' value='".filter_input(INPUT_POST,'pk')."' />";
echo "<br>";
echo "Method:<br> ";
echo "<select name='method2' id='method2'><option value=''>-</option><option value='I' ".(filter_input(INPUT_POST,'method')=='I'?'selected':'').">Insert</option><option value='U' ".(filter_input(INPUT_POST,'method2')=='U'?'selected':'').">Update</option><option value='D' ".(filter_input(INPUT_POST,'method2')=='D'?'selected':'').">Delete</option></select>";
echo "<br>";
echo "Field:<br> ";
echo "<input type='text' name='field' id='field' value='".filter_input(INPUT_POST,'field')."' />";
echo "<br>";
echo "initialValue:<br> ";
echo "<input type='text' name='initialValue' id='initialValue' value='".filter_input(INPUT_POST,'initialValue')."' />";
echo "<br>";
echo "changedTo:<br> ";
echo "<input type='text' name='changedTo' id='changedTo' value='".filter_input(INPUT_POST,'changedTo')."' />";
echo "<br>";
echo "<input type='submit' value='Submit' />";
echo "</form>";

echo "<br>";
echo "<br>";
echo "<table width='100%' cellpadding='0' cellspacing='0'>";
echo "<tr><td><b>date</b></td><td><b>user</b></td><td><b>method</b></td></tr>";

$lastActivityID = 0;
$activityLog = "";
$activityOptions = "";
$activityData = "";

$color1 = "#eaeaea";
$color2 = "#aeaeae";
$count = 0;
while($o = $rtn->FetchObject()) {

  if ($lastActivityID != $o->LA_ID && $lastActivityID != 0) {
    echo "<tr ".((strlen($activityData)>0 || strlen($activityOptions)>0)?"onclick='showId(".$lastActivityID.")' style='cursor:pointer;background: ".($count%2==0?$color1:$color2).";'": "")."'>";
    echo $activityLog;
    if (strlen($activityData)>0 || strlen($activityOptions)>0) {
      echo "<tr style='visibility:collapse;font-size:9pt;background: ".($count++%2==0?$color1:$color2)."' class='log_details' id='log_details_$lastActivityID'>";
      echo "<td colspan='3'>";
      if (strlen($activityOptions)>0)
        echo "<b>Activity Options (URL):</b><ul>".$activityOptions."</ul>";
      if (strlen($activityData)>0)
        echo "<b>Activity Data:</b><ul>".$activityData."</ul>";
      echo "</td>";
      echo "</tr>";
    }
    $activityLog = "";
    $activityOptions = "";
    $activityData = "";
    $lastActivityID = $o->LA_ID;
  }

  // Get Activity Log
  if (strlen($activityLog)==0) {
    $activityLog.="<td>".$o->LA_DATE."</td>";
    $activityLog.="<td>".$o->LA_USER."</td>";
    $activityLog.="<td>".$o->LA_METHOD."</td>";
    $activityLog.="</tr>";
    $lastActivityID = $o->LA_ID;
  }
  // Get Activity Data and Options
  switch($o->type) {
    case "LAO":
      if ($o->LAO_ID)
        $activityOptions.="<li><b>arg:</b> ".$o->LAO_SUBOPTION." <b>val:</b> ".$o->LAO_VALUE."</li>";
    break;
    case "LD":
      if ($o->LD_ID)
        $activityData.="<li><b>table:</b> ".$o->LD_TABLE." <b>pk:</b> ".$o->LD_PK." <b>method</b>: ".$o->LD_METHOD." <b>field:</b> ".$o->LD_FIELD." <b>data:</b> ".($o->LD_DATABEFORE==NULL?"NULL":$o->LD_DATABEFORE)." <b>changedTo:</b> ".($o->LD_DATAAFTER==NULL?"NULL":$o->LD_DATAAFTER)."</li>";
    break;
  }

}
echo "<tr style='visibility:collapse;font-size:9pt;background: ".($count++%2==0?$color1:$color2)."' class='log_details' id='log_details_$lastActivityID'>";
echo "<td colspan='3'>";
echo "<ul>".$activityOptions."</ul>";
echo "<ul>".$activityData."</ul>";
echo "</td>";
echo "</tr>";
echo "</table>";

if ($page > 1) echo "<a href='".APP::URLENCODEAPPEND("page=".($page-1))."'>&lt;&lt;</a>";
for ($i=1;$i<=$countPage;$i++) {
  if ($i!=$page)
    echo " <a href='".APP::URLENCODEAPPEND("page=$i")."'>". $i ."</a> ";
  else
    echo " [".$i."] ";
}
if (($page+1) <= $countPage) $pagination.="<a  href='".APP::URLENCODEAPPEND("page=".($page+1))."'>&gt;&gt;</a>";
} else {
  session_destroy();
  // Show login if user not logged
  echo "<img src='../src/img/logo.png'/>";
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
var openedId = undefined;
function showId(id){
  for (const el of document.getElementsByClassName('log_details'))
    el.style.visibility='collapse';
  document.getElementById("log_details_"+id).style.visibility="visible";
};
</script>

