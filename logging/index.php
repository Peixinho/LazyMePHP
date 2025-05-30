<?php
/**
 * @copyright This file is part of the LazyMePHP LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * Source File Generated Automatically
 */

namespace Logging;
use Config\Internal\APP;
session_start();

require_once __DIR__."/../src/Configurations/Configurations.php";
require_once __DIR__."/../src/Classes/includes.php";

function appendUrl($key, $value) 
{
  // Get GET vars
  $get = "";
  foreach($_GET as $k => $g) if ($g && $k!=$key) $get.=(strlen($get)==0?"?":"&")."$k=$g";
  return $get.(strlen($get)==0?"?":"&")."$key=$value";
}

// $_GET to $_GET
foreach($_POST as $k => $p) $_GET[$k]=$p;

if ((!array_key_exists('username', $_SESSION) || !array_key_exists('password', $_SESSION)) && ($_GET && $_GET['username'] && $_GET['password'] && $_GET['username'] == APP::DB_USER() && $_GET['password'] == APP::DB_PASSWORD()))
{
    $_SESSION['username'] = $_GET['username'];
    $_SESSION['password'] = $_GET['password'];
}

// If user is logged with database credentials
if ($_SESSION && isset($_SESSION['username']) == APP::DB_USER() && $_SESSION['password'] == APP::DB_PASSWORD())
{

$sqlFilter="";
if ($_GET['date1'] ?? null && $_GET['date2'] ?? null) {
  $level1 = true;
  $sqlFilter = "(LA.date BETWEEN '".$_GET['date1']."' AND '".$_GET['date2']."')";
}
if ($_GET['user'] ?? null) {
  $level1 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LA.user='".$_GET['user']."'";
}
if ($_GET['method'] ?? null) {
  $level1 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LA.method='".$_GET['method']."'";
}
$level2 = false;
if ($_GET['arg'] ?? null) {
  $level2 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LAO.subOption='".$_GET['arg']."'";
}
if ($_GET['value'] ?? null) {
  $level2 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LAO.value='".$_GET['value']."'";
}
$level3 = false;
if ($_GET['table'] ?? null) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.table='".$_GET['table']."'";
}
if ($_GET['pk'] ?? null) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.pk='".$_GET['pk']."'";
}
if ($_GET['method2'] ?? null) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.method='".$_GET['method2']."'";
}
if ($_GET['field'] ?? null) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.field='".$_GET['field']."'";
}
if ($_GET['initialValue'] ?? null) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.dataBefore='".$_GET['initialValue']."'";
}
if ($_GET['changedTo'] ?? null) {
  $level3 = true;
  $sqlFilter .= (strlen($sqlFilter)>0?" AND ":"")."LD.dataAfter='".$_GET['changedTo']."'";
}

echo "<h3>Logging</h3>";
$count = 0;

$sql = "
SELECT 
SUM(LOG_ACTIVITY_NR) AS LOG_ACTIVITY_NR,
SUM(LOG_ACTIVITY_OPTIONS_NR) AS LOG_ACTIVITY_OPTIONS_NR,
SUM(LOG_ACTIVITY_DATA_NR) AS LOG_ACTIVITY_DATA_NR
FROM (
	SELECT COUNT(DISTINCT LA.id) AS LOG_ACTIVITY_NR, 0 AS LOG_ACTIVITY_OPTIONS_NR, 0 as LOG_ACTIVITY_DATA_NR
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
$rtn = APP::DB_CONNECTION()->Query($sql);
while($o = $rtn->FetchObject()) {
  $countLogActivity = $o->LOG_ACTIVITY_NR;
  $countLogActivityOptions = $o->LOG_ACTIVITY_OPTIONS_NR;
  $countLogActivityData = $o->LOG_ACTIVITY_DATA_NR;
}

$count = $countLogActivity;
$page = (\array_key_exists('page', $_GET)&&$_GET['page']?$_GET['page']:1);
$limit = 1000;
$countPage = ($count / $limit);
$countPage+=(($count % $limit) != 0 ? 1:0);
if ($page>$countPage) $page = $_GET['page'] = 1;

$sql = "
  SELECT 
    LA_ID,
    LA_DATE,
    LA_USER,
    LA_METHOD,
    LAO_ID,
    LAO_ID_LOG_ACTIVITY,
    LAO_SUBOPTION,
    LAO_VALUE,
    LD_ID,
    LD_PK,
    LD_ID_LOG_ACTIVITY,
    LD_TABLE,
    LD_METHOD,
    LD_FIELD,
    LD_DATABEFORE,
    LD_DATAAFTER,
    type
FROM (
SELECT DISTINCT
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
	SELECT id FROM __LOG_ACTIVITY ORDER BY id DESC
  ) A ON A.id = LA.id
  LEFT JOIN __LOG_ACTIVITY_OPTIONS LAO ON LA.id=LAO.id_log_activity".
  ($level3?" RIGHT JOIN __LOG_DATA LD ON LA.id=LD.id_log_activity":"").
  (strlen($sqlFilter)>0?" WHERE $sqlFilter ":"")
  ." UNION ALL
  SELECT DISTINCT 
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
	SELECT id FROM __LOG_ACTIVITY ORDER BY id DESC 
  ) A ON A.id = LA.id
  LEFT JOIN __LOG_DATA LD ON LD.id_log_activity = LA.id".
  (strlen($sqlFilter)>0?" WHERE $sqlFilter ":"")
  ."
  ) R
  ORDER BY LA_ID DESC
  LIMIT ".($page-1)*$limit.", $limit
  ";
$rtn = APP::DB_CONNECTION()->Query($sql);

echo "<form method='POST' action=''>";
// Filter
echo "<h3>Log Activity</h3>";
echo "Initial Date:<br> ";
echo "<input type='text' name='date1' id='date1' value='".(isset($_GET['date1'])?$_GET['date1']:"")."' />";
echo "<br>";
echo "Ending Date:<br> ";
echo "<input type='text' name='date2' id='date2' value='".(isset($_GET['date2'])?$_GET['date2']:"")."' />";
echo "<br>";
echo "User:<br> ";
echo "<input type='text' name='user' id='user' value='".(isset($_GET['user'])?$_GET['user']:"")."' />";
echo "<br>";
echo "Request:<br> ";
echo "<select name='method' id='method'><option value=''>-</option><option value='GET' ".(isset($_GET['method'])?($_GET['method']=='GET'?'selected':''):'').">GET</option><option value='POST' ".(isset($_GET['method'])?($_GET['method']=='POST'?'selected':''):'').">POST</option><option value='PUT' ".(isset($_GET['method'])?($_GET['method']=='PUT'?'selected':''):'').">PUT</option><option value='PATCH' ".(isset($_GET['method'])?($_GET['method']=='PATCH'?'selected':''):'').">PATCH</option><option value='DELETE' ".(isset($_GET['method'])?($_GET['method']=='DELETE'?'selected':''):'').">DELETE</option></select>";


echo "<br>";
echo "<br>";
echo "<h3>Log Activity Options</h3>";
echo "Arg:<br> ";
echo "<input type='text' name='arg' id='arg' value='".(isset($_GET['arg'])?$_GET['arg']:"")."' />";
echo "<br>";
echo "Value:<br> ";
echo "<input type='text' name='value' id='value' value='".(isset($_GET['value'])?$_GET['value']:"")."' />";


echo "<br>";
echo "<br>";
echo "<h3>Log Activity Data</h3>";
echo "Table:<br> ";
echo "<input type='text' name='table' id='table' value='".(isset($_GET['table'])?$_GET['table']:"")."' />";
echo "<br>";
echo "PK:<br> ";
echo "<input type='text' name='pk' id='pk' value='".(isset($_GET['pk'])?$_GET['pk']:"")."' />";
echo "<br>";
echo "Method:<br> ";
echo "<select name='method2' id='method2'><option value=''>-</option><option value='I' ".(isset($_GET['method2'])?($_GET['method2']=='I'?'selected':''):'').">Insert</option><option value='U' ".(isset($_GET['method2'])?($_GET['method2']=='U'?'selected':''):'').">Update</option><option value='D' ".(isset($_GET['method2'])?($_GET['method2']=='D'?'selected':''):'').">Delete</option></select>";
echo "<br>";
echo "Field:<br> ";
echo "<input type='text' name='field' id='field' value='".(isset($_GET['field'])?$_GET['field']:"")."' />";
echo "<br>";
echo "initialValue:<br> ";
echo "<input type='text' name='initialValue' id='initialValue' value='".(isset($_GET['initialValue'])?$_GET['initialValue']:"")."' />";
echo "<br>";
echo "changedTo:<br> ";
echo "<input type='text' name='changedTo' id='changedTo' value='".(isset($_GET['changedTo'])?$_GET['changedTo']:"")."' />";
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
echo "</table>";

    $pagination = "";
		if ($page > 1) $pagination.="<a  href='".appendUrl("page",($page-1))."'>&lt;&lt;</a>";
		for ($i=1;$i<=$countPage;$i++) {
			if ($i!=$page)
				$pagination.=" <a  href='".appendUrl("page",$i)."'>". $i ."</a> ";
			else
				$pagination.=" [".$i."] ";
		}

		if (($page+1) <= $countPage) $pagination.="<a  href='".appendUrl("page",($page+1))."'>&gt;&gt;</a>";
  echo $pagination;
} 
else {
  session_destroy();
  // Show login if user not logged
  echo "<img src='../img/logo.png' width='100' height='100' />";
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
