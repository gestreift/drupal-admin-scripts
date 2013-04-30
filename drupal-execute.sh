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

tmpId=`makepasswd --chars=10`
tmpDir=/tmp/drupal-$tmpId

drushCmd=$1
log=$2

if [ -z "$log" ]; then
	log=$tmpDir/log
fi

mkdir $tmpDir

echo "Executing Drupal Locate with $drushCmd (output: $tmpDir, log: $log)"
/opt/drupal-admin-scripts/drupal-locate.sh $tmpDir/out $log $drushCmd

rm -rf $tmpDir
