<?php

/**
 * Find running websites in apache vhost configuration.
 *
 * Usage example: drupal-locate.php /etc/apache2/sites-enabled
 */

$path = '/etc/apache2/sites-enabled';

$files = scandir($path);
foreach($files as $filename) {
  $site = parseVhostFile($path . '/' . $filename);
  print_r($site);
  echo "\n";
}

function parseVhostFile($file) {
  $ret = new stdClass();

  // Get the file contents, assuming the file to be readable (and exist)
  $contents = file_get_contents($file);
  
  $pattern = 'ServerName ([A-Z0-9a-z\-\.]+)';
  // $pattern = preg_quote($pattern, '/');
  // Finalise the regular expression, matching the whole line
  $pattern = "/$pattern/";

  // Search, and store all matching occurences in $matches
  if(preg_match_all($pattern, $contents, $matches)){
    if (isset($matches[1][0])) {
      $ret->ServerName = $matches[1][0];
    }
  }

  return $ret;

}