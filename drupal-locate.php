<?php

/**
 * Find running websites in apache vhost configuration.
 *
 * Usage example: drupal-locate.php /etc/apache2/sites-enabled
 */

$path = '/etc/apache2/sites-enabled';

$files = scandir($path);
foreach($files as $filename) {
  echo(searchInFile($path . '/' . $filename));
  echo "\n";
}

function searchInFile($file) {
  // Get the file contents, assuming the file to be readable (and exist)
  $contents = file_get_contents($file);
  
  $pattern = 'ServerName';
  $pattern = preg_quote($pattern, '/');
  // Finalise the regular expression, matching the whole line
  $pattern = "/^.*$pattern.*\$/m";

  // Search, and store all matching occurences in $matches
  if(preg_match_all($pattern, $contents, $matches)){
     return($matches[0][0]);
  }
  else {
    return '';
  }

}