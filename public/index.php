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

<?php
// Set up Blade for rendering
$views = __DIR__ . '/../App/Views/';
$cache = __DIR__ . '/../App/Views/_compiled';
$blade = new \eftec\bladeone\BladeOne($views, $cache);

// Get the content from the router
$pageContent = '';
if (isset($content) && !empty($content)) {
    $pageContent = $content;
}

// Always render the layout with the content
echo $blade->run("_Layouts.app", [
    'pageContent' => $pageContent
]);
?>

<?php endif; ?>
