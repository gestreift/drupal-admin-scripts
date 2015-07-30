<?php

/**
 * Find running websites in apache vhost configuration.
 *
 * Usage example: drupal-locate.php /etc/apache2/sites-enabled
 */

if (!isset($argv) || !isset($argv[1])) {
  usage();
  return;
}

// Get vhost path from command line argument
$path = $argv[1];
if (!file_exists($path)) {
  echo "Directory does not exist.\n";
  return;
}

// Process additional cmd parameters
$csv = FALSE;
foreach($argv as $argument) {
  if ($argument == '--csv') {
    $csv = TRUE;
  }
}

// CSV header
if ($csv) {
  echo "ServerName;ServerAlias;DocumentRoot;VHostConfig\n";
}

$files = scandir($path);
$count = 0;
foreach($files as $filename) {
  if ($site = parseVhostFile($path . '/' . $filename)) {
    if ($csv) {
      printSiteToCSV($site);
    }
    else {
      printSite($site);
    }
    echo "\n";
    $count++;
  }
}

if (!$csv) {
  echo "Counting $count sites.\n";
}

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

function printSiteToCSV($site) {
  echo  $site->ServerName . ";";
  if (isset($site->ServerAlias)) {
    echo "$site->ServerAlias;";
  }
  else {
    echo ';';
  }
  echo "$site->DocumentRoot;";
  echo basename($site->vhostFile) . ";";
}

function usage() {
  echo "Usage:\n";
  echo '  ' . basename(__FILE__) . ' [path-to-apache-vhost-files]' . "\n";
}