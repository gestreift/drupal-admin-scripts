#!/bin/bash

##
## Drupal Execute
##
## Findet alle lokalen Drupal-Instanzen innerhalb der Apache-VHosts-Konfiguration und führt drush-Kommando aus (Wrapper-Script)
##
## Aufruf:
## drupal-execute.sh drushCmd [log]
##
## Version: 0.0.1
## Author: Jonas Westphal (jw@yu.am)
##
## https://jw.is
##
##

SCRIPT_PATH="`dirname \"$0\"`"              # relative
SCRIPT_PATH="`( cd \"$SCRIPT_PATH\" && pwd )`"  # absolutized and normalized
if [ -z "$SCRIPT_PATH" ] ; then
  # error; for some reason, the path is not accessible
  # to the script (e.g. permissions re-evaled after suid)
  exit 1  # fail
fi
echo "$SCRIPT_PATH"

tmpDir=`mktemp -d`

drushCmd=$1
log=$2

if [ -z "$log" ]; then
	log=$tmpDir/log
fi

echo "Executing Drupal Locate with $drushCmd (output: $tmpDir, log: $log)"
$SCRIPT_PATH/drupal-locate.sh $tmpDir/out $log $drushCmd

rm -rf $tmpDir
