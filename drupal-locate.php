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

// TODO: --group-by-pattern=zebrarchive
//       will sort vhosts by zebrarchive = 0/1

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
    checkSiteHealth($site);

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
  $site = new stdClass();
  $site->vhostFile = $file;

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
      // ServerName & ServerAlias as an array as one file can have multiple vhosts.
      if ($key == 'ServerName' || $key == 'ServerAlias') {
        $site->$key = $matches[1];
      }
      if (!isset($site->$key)) {
        $site->$key = $matches[1][0];
      }
    }
  }

  // A site needs to have at least DocumentRoot and ServerName.
  if (empty($site->ServerName) || empty($site->DocumentRoot)) {
    return FALSE;
  }

  // Convert space separated hosts into flat array because we didn't figure out
  // how to get a flat array with regex (like /ServerAlias ((HOSTPATTERN+ )+)/.
  $explode_keys = array('ServerName', 'ServerAlias');
  foreach ($explode_keys as $key) {
    $hosts_array = array();

    if (!isset($site->$key)) {
      continue;
    }

    foreach($site->$key as $hosts_string) {
      // Use regex instead of explode()
      $hosts_array = array_merge($hosts_array, explode(' ', $hosts_string));
    }
    $site->$key = $hosts_array;
  }

  return $site;
}

/**
 * Return site healt as an object.
 *
 * @param object &$site
 *        Description of the site.
 */
function checkSiteHealth(&$site) {
  $health = new stdClass();

  // Test hostname availability.
  $health->hosts = checkSiteHosts($site);

  // TODO Update status (updates vs. security-only)
  $site->health = $health;
}

/**
 * Test if the hostnames of a site are available.
 *
 * @param [type] $site
 *
 * @return [type]
 */
function checkSiteHosts($site) {
  $host_health = array();

  $host_keys = array('ServerName', 'ServerAlias');
  foreach($host_keys as $key) {
    if (!isset($site->$key)) {
      continue;
    }
    foreach($site->$key as $host) {
      set_error_handler(function() { /* ignore errors */ }, E_WARNING);
      if (file_get_contents('http://' . $host)) {
        $host_health[$host] = TRUE;
      }
      else {
        $host_health[$host] = FALSE;
      }
      restore_error_handler();
    }
  }

  return $host_health;
}

function printSite($site) {
  echo  $site->ServerName[0] . "\n";
  echo "  ServerName:  " . hostnamesToString($site->ServerName, $site) . "\n";
  if (isset($site->ServerAlias) && !empty($site->ServerAlias)) {
    echo "  ServerAlias: " . implode(", ", $site->ServerAlias) . "\n";
  }
  echo "  DocumentRoot: $site->DocumentRoot\n";
  echo '  Config: ' . basename($site->vhostFile) . "\n";
}

/**
 * Print list of hostnames
 *
 * @param array  $hostnames
 *        List of hostnames.
 * @param object $site
 *        Site description.
 */
function hostnamesToString($hostnames, $site) {
  foreach($hostnames as &$host) {
    if (!$site->health->hosts[$host]) {
      $host .= '*';
    }
  }
  return implode(', ', $hostnames);
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