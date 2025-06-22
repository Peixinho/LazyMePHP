<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

// Include the bootstrap file
require_once __DIR__ . "/../App/bootstrap.php";
use Core\LazyMePHP;

// Initialize the application
$app = new LazyMePHP();

?>

<?php if (filter_input(INPUT_GET, "render")!="no") : ?>

<!DOCTYPE html>
<html class="" lang="pt">
  <head>
      <link rel="icon" type="image/png" href="/img/logo.png">
      <!-- CSS -->
      <link rel="stylesheet" href="/css/css.css">
      <title><?php echo LazyMePHP::TITLE(); ?></title>
    </head>
    <body onload="LazyMePHP.Init()">
      <nav aria-label="Main navigation">
        <ul class="nav-menu">
          <?php foreach (glob(__DIR__."/../App/Routes/" . '/*.php') as $r) { if (substr($r, strrpos($r, "/")+1, strlen($r)) != "Routes.php") { echo "<li><a href=\"/".substr($r,strrpos($r,"/")+1, -4)."\">".substr($r,strrpos($r,"/")+1, -4)."</a>"; } } ?>
        </ul>
    </nav>

      <div>
        <div>
        <!-- Main Content -->
        <?=$content??''?>
        <!-- End  -->
      </div>
    </div>
    <?php
    $showMessage_S = "";
    $showMessage_E = "";
    //Error And Success Messages Generator
    if (array_key_exists('success', $_GET) && strlen($_GET['success'])>0) foreach (explode(',',$_GET['success']) as $s) {
      $s = "S$s";
      $showMessage_S = (strlen($showMessage_S)>0?$showMessage_S."\\n".constant("Messages\Success::{$s}")->value:constant("Messages\Success::{$s}")->value);
    }
    if (array_key_exists('error', $_GET) && strlen($_GET['error'])>0) foreach (explode(',',$_GET['error']) as $e) {
      $e = "E$e";
      $showMessage_E = (strlen($showMessage_E)>0?$showMessage_E."\\n".constant("\Messages\Error::{$e}")->value:constant("\Messages\Error::{$e}")->value);
    }
    ?>
    <script src="/js/LazyMePHP.js"></script>
    <script>
    <?php if (strlen($showMessage_E)>0) echo "LazyMePHP.ShowError('$showMessage_E');"; if (strlen($showMessage_S)>0) echo "LazyMePHP.ShowSuccess('$showMessage_S');"; ?>
    </script>
  </body>
</html>
<?php endif; ?>
