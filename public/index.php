<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

  require_once __DIR__."/../src/bootstrap.php";
  use LazyMePHP\Config\Internal\APP;
?>

<?php if (filter_input(INPUT_GET, "render")!="no") : ?>

<!DOCTYPE html>
<html class="" lang="pt">
  <head>
      <link rel="icon" type="image/png" href="/img/logo.png">
      <!-- CSS -->
      <link rel="stylesheet" href="/css/css.css">
      <title><?php echo APP::APP_TITLE(); ?></title>
    </head>
    <body onload="LazyMePHP.Init()">
      <?= \LazyMePHP\Config\Internal\GetErrors();?>

      <ul><?php foreach (glob(__DIR__."/../src/Routes/" . '/*.php') as $r) { if (substr($file, strrpos($file, "/")+1, strlen($file)) != "Routes.php") { echo "<li><a href=\"/".substr($r,strrpos($r,"/")+1, -4)."\">".substr($r,strrpos($r,"/")+1, -4)."</a>"; } } ?></ul>

      <div>
        <div>
        <!-- Main Content -->
        <?=$content?>
        <!-- End  -->
      </div>
    </div>
    <?php
    $showMessage_S = "";
    $showMessage_E = "";
    //Error And Success Messages Generator
    if (array_key_exists('success', $_GET) && strlen($_GET['success'])>0) foreach (explode(',',$_GET['success']) as $s) { $showMessage_S = (strlen($showMessage_S)>0?$showMessage_S."<br/>".$SuccessMessages->GetName($s):$SuccessMessages->GetName($s)); }
    if (array_key_exists('error', $_GET) && strlen($_GET['error'])>0) foreach (explode(',',$_GET['error']) as $e) { $showMessage_E = (strlen($showMessage_E)>0?$showMessage_E."<br/>".$ErrorMessages->GetName($e):$ErrorMessages->GetName($e)); }
    ?>
    <script src="/js/LazyMePHP.js"></script>
    <script>
    <?php if (strlen($showMessage_E)>0) echo "LazyMePHP.ShowError('$showMessage_E');"; if (strlen($showMessage_S)>0) echo "LazyMePHP.ShowSuccess('$showMessage_S');"; ?>
    </script>
  </body>
</html>
<?php endif; ?>
