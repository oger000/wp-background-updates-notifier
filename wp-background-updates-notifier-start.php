#!/usr/bin/php
<?PHP

if ($argv[1]) {
  $_SERVER['HTTP_HOST'] = $argv[1];
}
else {
  echo "Usage: {$argv[0]} blog-url\n";
  exit;
}


require_once("wp-background-updates-notifier.php");


?>

