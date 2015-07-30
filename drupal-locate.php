<?php

/**
 * Find running websites in apache vhost configuration.
 *
 * Usage example: drupal-locate.php /etc/apache2/sites-enabled
 */

$path = '/etc/apache2/sites-enabled';

$files = scandir($path);
$count = 0;
foreach($files as $filename) {
  if ($site = parseVhostFile($path . '/' . $filename)) {
    printSite($site);
    echo "\n";
    $count++;
  }
}

echo "Counting $count sites.\n";

function parseVhostFile($file) {
  $ret = new stdClass();
  $ret->vhostFile = $file;

  // Get the file contents, assuming the file to be readable (and exist)
  $contents = file_get_contents($file);

  $patterns = array(
    'ServerName'    => '/ServerName\s+([A-Z0-9a-z\-\.]+)/',
    'ServerAlias'   => '/ServerAlias\s+([A-Z0-9a-z\-\. ]+)/',
    'DocumentRoot'  => '/DocumentRoot\s+(.+)/'
  );

  foreach ($patterns as $key => $pattern) {
    // Search, and store all matching occurences in $matches
    if(preg_match_all($pattern, $contents, $matches)){
      if (isset($matches[1][0])) {
        $ret->$key = $matches[1][0];
      }
    }
  }

  // A site needs to have at least DocumentRoot and ServerName.
  if (empty($ret->ServerName) || empty($ret->DocumentRoot)) {
    return FALSE;
  }
  else {
    return $ret;
  }
}

function printSite($site) {
  echo  $site->ServerName . "\n";
  echo "  ServerName:  $site->ServerName" . "\n";
  if (isset($site->ServerAlias)) {
    echo "  ServerAlias: $site->ServerAlias\n";
  }
  echo "  DocumentRoot: $site->DocumentRoot\n";
  echo '  Config: ' . basename($site->vhostFile) . "\n";
}