<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

if (file_exists(getcwd() . '/public/' . $_SERVER['REQUEST_URI'])) {
  return false; // serve the requested resource as-is.
} 

$cmdline = file_get_contents('/proc/self/cmdline');
$argv = str_getcsv($cmdline, "\0");
$argvo = $argv;

while (true) {
  $arg = array_shift($argv);
  if ($arg === null || $arg === '--') {
    // Stick whatever on the start to align with normal $argv
    array_unshift($argv, __FILE__);
    if ($arg !== null) {
      // Remove extra empty arg at the end
      array_pop($argv);
    }
    break;
  }
}
$argc = count($argv);

$filePath = realpath(ltrim($_SERVER["REQUEST_URI"], '/'));
if ($filePath && is_dir($filePath)){
  // attempt to find an index file
  foreach (['index.php', 'index.html'] as $indexFile){
    if ($filePath = realpath($filePath . DIRECTORY_SEPARATOR . $indexFile)){
      break;
    }
  }
}
if ($filePath && is_file($filePath)) {
  // 1. check that file is not outside of this directory for security
  // 2. check for circular reference to router.php
  // 3. don't serve dotfiles
  if (strpos($filePath, getcwd() . DIRECTORY_SEPARATOR) === 0 &&
    $filePath != getcwd() . DIRECTORY_SEPARATOR . 'serve.php' &&
    substr(basename($filePath), 0, 1) != '.'
) {
    if (strtolower(substr($filePath, -4)) == '.php') {
      // php file; serve through interpreter
      include $filePath;
    } else {
      // asset file; serve from filesystem
      return false;
    }
  } else {
    // disallowed file
    header("HTTP/1.1 404 Not Found");
    echo "404 Not Found";
  }
} else {
  // rewrite to our index file
  if (substr($_SERVER["REQUEST_URI"],0,4) == "/api")
    include getcwd() . DIRECTORY_SEPARATOR . 'public/api/index.php';
  elseif (substr($_SERVER["REQUEST_URI"],0,4) == "/dbhelper")
    include getcwd() . DIRECTORY_SEPARATOR . '../dbhelper/index.php';
  elseif (substr($_SERVER["REQUEST_URI"],0,4) == "/logging")
    include getcwd() . DIRECTORY_SEPARATOR . '../logging/index.php';
  else
    include getcwd() . DIRECTORY_SEPARATOR . 'public/index.php';
}
?>
