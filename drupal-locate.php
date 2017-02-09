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
  else {
    if ($arg == '--test-hostnames') {
      $conf->testHostnames = TRUE;
    }
    else {
      if (strstr($arg, '--exec=')) {
        $matches = array();
        preg_match_all('/--exec=(.+)/', $arg, $matches);
        if (isset($matches[1])) {
          $conf->exec = $matches[1][0];
        }
      }
      else {
        if ($arg == '--sudo') {
          $conf->sudo = TRUE;
        }
        else {
          if (file_exists($arg)) {
            $path = $arg;
          }
        }
      }
    }
  }
}

if (!isset($path)) {
  echo "Please provide a valid path of your vhost config directory.\n";
  usage();
  return;
}

// CSV header
if ($conf->csv) {
  echo "VHostConfig;ServerName;ServerAlias;HeinleinDomain;DocumentRoot;Redirect;User;Group;Execute output\n";
}

$files = scandir($path);
$count = 0;
foreach ($files as $filename) {
  if ($sites = parseVhostFile($path . '/' . $filename)) {
    foreach ($sites as $site) {
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
      else {
        if (isset($conf->exec)) {
          $hostnames = hostnamesToString($site->ServerName, $site);
          $result = executeCommand($conf->exec, $site, $conf->sudo);
          if (isset($result->sudo_user)) {
            $result->output .= "\n (as user $result->sudo_user)";
          }
          $site->execOutput = $result->output;
          printSiteToCSV($site);
        }
        else {
          if ($conf->csv) {
            printSiteToCSV($site);
          }
          else {
            printSite($site);
          }
        }
      }

      echo "\n";
      $count++;
    }
  }
}

if (!$conf->csv) {
  echo "Counting $count sites.\n";
}


/**
 * Parse a vhost file. Returns sites in the given config file.
 *
 * @param $file             Filename of the apache vhost file.
 * @return array|bool       Array of site descriptions. False on parse error.
 */
function parseVhostFile($filename) {

  if (!is_file($filename)) {
    return FALSE;
  }

  $conf = new Config();
  $root = $conf->parseConfig($filename, 'apache');
  if (PEAR::isError($root)) {
    echo 'Error reading config: ' . $root->getMessage() . "\n";
    exit(1);
  }

  $sites = processConfigTree($root, $filename);
  return $sites;
}

/**
 * Process a whole or a partial apache config tree.
 *
 * We assume VirtualHost records in the level of 1st generation children.
 *
 * @param \Config_Container $config
 *     Contains vhost configuration.
 *
 * @return Array with site descriptions.
 */
function processConfigTree(Config_Container $config, $filename) {
  $sites = array();

  $maxCount = $config->countChildren();
  for ($childIndex = 0; $childIndex < $maxCount; $childIndex++) {
    $child = $config->getChild($childIndex);
    $type = $child->getName();
    if ($type != 'VirtualHost') {
      continue;
    }

    $site = processVirtualHost($child);

    // Validation: A site needs to have at least a ServerName plus a
    // - DocumentRoot or a
    // - Redirect
    if (empty($site->ServerName)) {
      continue;
    }
    else {
      if (empty($site->DocumentRoot) && empty($site->Redirect)) {
        continue;
      }
    }

    $site->sourceFile = $filename;
    $sites[] = $site;
  }

  return $sites;
}

function processVirtualHost(Config_Container $config) {
  $site = new stdClass();

  $maxCount = $config->countChildren();
  for ($childIndex = 0; $childIndex < $maxCount; $childIndex++) {
    $child = $config->getChild($childIndex);
    processVhostItem($child, $site);
  }

  return $site;
}

/**
 * Process a vhost item and add to the site object.
 *
 * @param $item         Config item.
 * @param $site         Site description that will be altered.
 */
function processVhostItem($item, &$site) {
  // We want to consider only these apache directives. Discard all other.
  $allowedDirectives = array(
    'ServerName',
    'DocumentRoot',
    'ServerAlias',
    'AssignUserID',
    'Redirect'
  );
  $multiValueDirectives = array('ServerName', 'ServerAlias');

  $itemType = $item->getType();
  $itemName = $item->getName();
  if ($itemType == 'directive' && in_array($itemName, $allowedDirectives)) {
    if ($itemName == 'AssignUserID') {
      // Replace whatever space and tabs characters by space.
      $assignUserID = preg_replace('/\s+/', ' ', $item->content);
      $userGroup = explode(' ', $assignUserID);
      $site->User = $userGroup[0];
      $site->Group = $userGroup[1];
    }
    else {
      if (in_array($itemName, $multiValueDirectives)) {
        $value = preg_replace('/\s+/', ' ', $item->content);
        $site->{$item->name} = explode(' ', $value);
      }
      else {
        $site->{$item->name} = $item->content;
      }
    }
  }
}

/**
 * Return site health as an object.
 *
 * @param object &$site
 *        Description of the site.
 */
function checkSiteHealth(&$site) {
  // Test hostname availability.
  $health = new stdClass();
  $health->hosts = checkSiteHosts($site);
  $site->health = $health;

  $domains = checkDomainTechC($site);
  $site->domains = $domains;
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
  foreach ($host_keys as $key) {
    if (!isset($site->$key)) {
      continue;
    }
    foreach ($site->$key as $host) {
      $url = 'http://' . $host;
      $handle = curl_init($url);
      curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
      $response = curl_exec($handle);
      $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
      $host_health[$host] = $httpCode;
      curl_close($handle);
    }
  }

  return $host_health;
}

/**
 * Try to find out the hosting company managing the domain.
 *
 * @param $site
 * @return stdClass
 *   Domain info object with additional domain information.
 *   - Heinlein (boolean) if the domain is hosted by Heinlein Support.
 *   - TechC (string) contains name and/or contact of the hosting company.
 */
function checkDomainTechC($site) {
  $domains = array();

  foreach ($site->ServerName as $hostname) {
    $domainName = _get_domain($hostname);
    if (!$domainName) {
      continue;
    }
    $cmd = "whois " . $domainName . " | grep \"Name:\|Organisation:\"";
    $result = shell_exec($cmd);

    $domainInfo = new stdClass();

    $domainInfo->Heinlein = FALSE;
    if (strstr($result, 'Heinlein')) {
      $domainInfo->Heinlein = TRUE;
    }
    $domainInfo->TechC = $result;
    $domains[$hostname] = $domainInfo;
  }
  return $domains;
}

function _get_domain($hostname) {
  $hostnames = explode(".", $hostname);
  if (count($hostnames) < 2) {
    return FALSE;
  }

  $bottom_host_name = $hostnames[count($hostnames) - 2] . "." . $hostnames[count($hostnames) - 1];
  return $bottom_host_name;
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
  chdir($site->DocumentRoot);

  // Change user if the vhost defines a specific user.
  $sudo_prefix = '';
  if ($sudo) {
    if (isset($site->Group)) {
      $result->sudo_user = $site->Group;
    }
    else {
      if (isset($site->User)) {
        $result->sudo_user = $site->User;
      }
    }
  }

  // Execute command
  $hostnames = hostnamesToString($site->ServerName, $site);
  $result->command = $command;
  $sudo_prefix = isset($result->sudo_user) ? 'sudo sudo -u ' . $result->sudo_user . ' ' : '';
  // Check if the command is in a relative path.
  if (substr($command, 0, 1) != '/' && file_exists($cur_path . '/' . $command)) {
    $command = $cur_path . '/' . $command;
  }
  $result->output = shell_exec($sudo_prefix . $command);

  // Return to original path.
  chdir($cur_path);

  return $result;
}

function printSite($site) {
  $redirect_tag = '';
  if (isset($site->Redirect) && !isset($site->DocumentRoot)) {
    $redirect_tag = ' [Exclusive redirect] ';
  }

  echo $site->ServerName[0] . "\n";
  echo "  ServerName:  " . hostnamesToString($site->ServerName, $site) . $redirect_tag . "\n";

  if (isset($site->ServerAlias) && !empty($site->ServerAlias)) {
    echo "  ServerAlias: " . hostnamesToString($site->ServerAlias, $site) . "\n";
  }

  if (isset($site->DocumentRoot)) {
    echo "  DocumentRoot: $site->DocumentRoot\n";
  }

  if (isset($site->Redirect)) {
    echo "  Redirect: $site->Redirect\n";
  }

  if (isset($site->domains)) {
    echo '  Domain Info: ' . printDomainInfos($site) . "\n";
  }

  echo '  Config: ' . basename($site->sourceFile) . "\n";
  if (isset($site->Group)) {
    echo '  User: ' . $site->User . "\n";
  }
  if (isset($site->Group)) {
    echo '  Group: ' . $site->Group . "\n";
  }
}

/**
 * Print domain infos to string.
 */
function printDomainInfos($site) {
  $domains = $site->domains;

  $out = array();
  foreach ($domains as $hostname => $domainInfo) {
    $techC = str_replace("\n", ", ", $domainInfo->TechC);
    $out[] = "$hostname => $techC";
  }

  return implode(' | ', $out);
}

/**
 * Print domain infos for csv output.
 *
 * @return string
 */
function printDomainInfosCSV($site) {
  $domains = $site->domains;

  $out = array();
  foreach ($domains as $hostname => $domainInfo) {
    $out[] = "$domainInfo->Heinlein";
  }

  return implode('|', $out);
}

/**
 * Print list of hostnames
 *
 * @param array $hostnames
 *        List of hostnames.
 * @param object $site
 *        Site description.
 */
function hostnamesToString($hostnames, $site) {
  $response_ok = array('200');
  foreach ($hostnames as &$host) {
    if (isset($site->health) && !in_array($site->health->hosts[$host], $response_ok)) {
      $host .= '[' . $site->health->hosts[$host] . ']';
    }
  }
  return implode(', ', $hostnames);
}

function printSiteToCSV($site) {
  echo basename($site->sourceFile) . ";";
  echo hostnamesToString($site->ServerName, $site) . ";";

  if (isset($site->ServerAlias)) {
    echo hostnamesToString($site->ServerAlias, $site) . ';';
  }
  else {
    echo ';';
  }

  if (isset($site->domains)){
    echo printDomainInfosCSV($site) . ';';
  }
  else {
    echo ';';
  }

  if (isset($site->DocumentRoot)) {
    echo "$site->DocumentRoot;";
  }
  else {
    echo ';';
  }

  if (isset($site->Redirect)) {
    echo "$site->Redirect;";
  }
  else {
    echo ';';
  }

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


