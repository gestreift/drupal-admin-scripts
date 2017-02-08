#!/usr/bin/php
<?php

/**
 * Requires pear Config parser.
 * Install via sudo pear install Config
 */
require_once 'Config.php';


/**
 * Find running websites in apache vhost configuration.
 *
 * Usage example: drupal-locate.php [path-to-apache-vhost-files] --csv --test-hostnames --exec=your_command
 */

// TODO: --group-by-pattern=zebrarchive
//       will sort vhosts by zebrarchive = 0/1

// TODO Username in $site object
// TODO Guess best hostnames:
//      - Check redirects - still on this machine after redirect?
//      - Example: Primary: www.myproject.com
//                 Other hostnames: myproject.com, myproject.internaldomain.com, ...
// TODO Update status (updates vs. security-only)
// TODO Custom Drush Command via arguments (e.g. cron)
// TODO --fix-file-permissions
// TODO Filter hostnames (like heinlein-hosting.de)

if (!isset($argv) || !isset($argv[1])) {
  usage();
  return;
}


// Parse command line arguments
$conf = new stdClass();
$conf->csv = FALSE;
$conf->testHostnames = FALSE;
$conf->sudo = FALSE;

foreach ($argv as $count => $arg) {
  // Skip first argument. This is the script's path.
  if ($count == 0) {
    continue;
  }

  if ($arg == '--csv') {
    $conf->csv = TRUE;
  }
  else if ($arg == '--test-hostnames') {
    $conf->testHostnames = TRUE;
  }
  else if (strstr($arg, '--exec=')) {
    $matches = array();
    preg_match_all('/--exec=(.+)/', $arg, $matches);
    if (isset($matches[1])) {
      $conf->exec = $matches[1][0];
    }
  }
  else if ($arg == '--sudo') {
    $conf->sudo = TRUE;
  }
  else if (file_exists($arg)) {
    $path = $arg;
  }
}

if (!isset($path) ) {
  echo "Please provide a valid path of your vhost config directory.\n";
  usage();
  return;
}

// CSV header
if ($conf->csv) {
  echo "VHostConfig;ServerName;ServerAlias;DocumentRoot;User;Group;Execute output\n";
}

$files = scandir($path);
$count = 0;
foreach($files as $filename) {
  if ($site = parseVhostFile($path . '/' . $filename)) {
    if ($conf->testHostnames) {
      checkSiteHealth($site);
    }

    // Execute a command for each site.
    if (isset($conf->exec) && !$conf->csv) {
      $hostnames = hostnamesToString($site->ServerName, $site);
      echo "------------------------------------------------\n";
      echo "For $hostnames in $site->DocumentRoot:\n";
      $result = executeCommand($conf->exec, $site, $conf->sudo);
      echo "Executing $result->command";
      if (isset($result->sudo_user)) {
        echo " (as user $result->sudo_user)";
      }
      echo "\n";
      echo $result->output;
      echo "------------------------------------------------\n";
    }
    else if (isset($conf->exec)) {
      $hostnames = hostnamesToString($site->ServerName, $site);
      $result = executeCommand($conf->exec, $site, $conf->sudo);
      if (isset($result->sudo_user)) {
        $result->output .= "\n (as user $result->sudo_user)";
      }
      $site->execOutput = $result->output;
      printSiteToCSV($site);
    }
    else if ($conf->csv) {
      printSiteToCSV($site);
    }
    else {
      printSite($site);
    }

    echo "\n";
    $count++;
  }
}

if (!$conf->csv) {
  echo "Counting $count sites.\n";
}

function parseVhostFile($file) {
  $site = new stdClass();
  $site->vhostFile = $file;

  if(!is_file($file)) {
    return FALSE;
  }

  $conf = new Config();
  $root = $conf->parseConfig($file, 'apache');
  if (PEAR::isError($root)) {
      echo 'Error reading config: ' . $root->getMessage() . "\n";
      exit(1);
  }

  // We want to consider only these apache directives. Discard all other.
  $allowedDirectives = array('ServerName', 'DocumentRoot', 'ServerAlias', 'AssignUserID');

  // Parse vhost section.
  // TODO: There can be multiple VirtualHosts
  if ($vhostConfig = $root->getChild()) {
    $directiveType = $vhostConfig->getName();
    if ($directiveType == 'VirtualHost') {
      $maxCount = $vhostConfig->countChildren();
      for ($i = 0; $i < $maxCount; $i++) {
        $item = $vhostConfig->getChild($i);
        if (!$item) {
          continue;
        }
        $itemType = $item->getType();
        $itemName = $item->getName();
        if ($itemType == 'directive' && in_array($itemName, $allowedDirectives) ) {
          if ($itemName == 'AssignUserID') {
            // Replace whatever space and tabs characters by space.
            $assignUserID = preg_replace('/\s+/', ' ', $item->content);
            $userGroup = explode(' ', $assignUserID);
            $site->User = $userGroup[0];
            $site->Group = $userGroup[1];
          }
          $site->{$item->name} = $item->content;
        }
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

    $site->$key = explode(' ', $site->$key);
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
      $url = 'http://' . $host;
      $handle = curl_init($url);
      curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
      $response = curl_exec($handle);
      $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
      $host_health[$host] = $httpCode;
      curl_close($handle);
    }
  }

  return $host_health;
}

/**
 * Execute a shell command for the given site.
 *
 * @param string $command
 *        Shell command, like 'ls' or 'drush status'
 * @param object $site
 * @param boolean $sudo
 *        TRUE if the command will be executed as the user defined in $site.
 *
 * @return object
 *         Executed command and output from shell command.
 */
function executeCommand($command, $site, $sudo = FALSE) {
  $result = new stdClass();

  // We go into the document root, execute the command and then return to the current path.
  $cur_path = getcwd();
  chdir ($site->DocumentRoot);

  // Change user if the vhost defines a specific user.
  $sudo_prefix = '';
  if ($sudo) {
    if (isset($site->Group)) {
      $result->sudo_user = $site->Group;
    }
    else if (isset($site->User)) {
      $result->sudo_user = $site->User;
    }
  }

  // Execute command
  $hostnames = hostnamesToString($site->ServerName, $site);
  $result->command = $command;
  $sudo_prefix = isset($result->sudo_user) ? 'sudo sudo -u ' . $result->sudo_user . ' ' : '';
  // Check if the command is in a relative path.
  if (substr($command, 0,1) != '/' && file_exists($cur_path . '/' . $command)) {
    $command = $cur_path . '/' . $command;
  }
  $result->output = shell_exec($sudo_prefix . $command);

  // Return to original path.
  chdir($cur_path);

  return $result;
}

function printSite($site) {
  echo  $site->ServerName[0] . "\n";
  echo "  ServerName:  " . hostnamesToString($site->ServerName, $site) . "\n";
  if (isset($site->ServerAlias) && !empty($site->ServerAlias)) {
    echo "  ServerAlias: " . hostnamesToString($site->ServerAlias, $site) . "\n";
  }
  echo "  DocumentRoot: $site->DocumentRoot\n";
  echo '  Config: ' . basename($site->vhostFile) . "\n";
  if (isset($site->Group)) {
    echo '  User: ' . $site->User . "\n";
  }
  if (isset($site->Group)) {
    echo '  Group: ' . $site->Group . "\n";
  }
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
  $response_ok = array('200');
  foreach($hostnames as &$host) {
    if ( isset($site->health) && !in_array($site->health->hosts[$host], $response_ok) ) {
      $host .= '[' . $site->health->hosts[$host] . ']';
    }
  }
  return implode(', ', $hostnames);
}

function printSiteToCSV($site) {
  echo basename($site->vhostFile) . ";";
  echo hostnamesToString($site->ServerName, $site) . ";";
  if (isset($site->ServerAlias)) {
    echo hostnamesToString($site->ServerAlias, $site) . ';';
  }
  else {
    echo ';';
  }
  echo "$site->DocumentRoot;";
  if (isset($site->Group)) {
    echo $site->User . ";";
  }
  else {
    echo ";";
  }
  if (isset($site->Group)) {
    echo $site->Group . ";";
  }
  else {
    echo ";";
  }
  if (isset($site->execOutput)) {
    echo '"' . $site->execOutput . '";';
  }
  else {
    echo ";";
  }
}

function usage() {
  echo "Usage:\n";
  echo '  ' . basename(__FILE__) . ' [path-to-apache-vhost-files] --csv --test-hostnames --exec="your_command" --sudo' . "\n";
}
