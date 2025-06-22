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
      <!-- JavaScript - Load early to ensure availability -->
      <script src="/js/LazyMePHP.js"></script>
    </head>
    <body>
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
    
    <script>
      // Initialize LazyMePHP and handle notifications when DOM is ready
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof LazyMePHP !== 'undefined' && typeof LazyMePHP.Init === 'function') {
          LazyMePHP.Init();
          
          // Process any session notifications that might be available
          // This will be handled by the notifications component, but we ensure LazyMePHP is ready
          console.log('LazyMePHP initialized successfully');
        } else {
          console.error('LazyMePHP not available for initialization');
        }
      });
    </script>
  </body>
</html>
<?php endif; ?>
