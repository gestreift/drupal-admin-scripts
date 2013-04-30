#!/bin/bash

##
## Locate Drupal
##
## Findet alle lokalen Drupal-Instanzen innerhalb der Apache-VHosts-Konfiguration und export/dokumentiert diese
##
## Version: 0.0.1
## Author: Jonas Westphal (jw@yu.am)
##
## https://jw.is
##
##

outfile=$1
docfile=$2

# Return 1 if element exists in array
containsElement () {
  local e
  for e in "${@:2}"; do [[ "$e" == "$1" ]] && return 1; done
  return 0
}


# Purge output file
echo > $outfile
echo > $docfile

# Find all active web projects
SITES=`grep -R -oh -E "^\s*DocumentRoot (.+)" /etc/apache2/sites-enabled`
SITES=${SITES//DocumentRoot/}

# Print summary of projects we'll test. Ignore path if it occurs multiple times
for file in $SITES; do
	containsElement $file "${tasks[@]}"
	if [ $? == "0" ]; then
		tasks=("${tasks[@]}" $file)
		echo "-> " $file >> $docfile
	else
		echo Skipping duplicate directory $file.
	fi
done

# Überprüfe rekursiv, ob/wo eine settings.php vorliegt
#count=0

for file in ${tasks[@]}; do
	if [ -d $file ]; then
		echo "Check $file"

		find $file -name settings.php | while read settingsFile

		do
			#FIXME: Zähler bauen
			#count=`expr $count + 1`

			echo "Found probably Drupal Project: $settingsFile"
			dirname=${settingsFile%/*}

			#TODO: Eine bessere Suchphrase als "drupal"
			isDrupal=`(grep drupal $settingsFile)`

			if [ -n "$isDrupal" ]; then
				echo "Hello, world - Drupal found in $dirname"
				echo $dirname >> $outfile
			else
				echo "Whoopsie - No Drupal found in $dirname"
			fi
		done
	fi
done

#echo "Total $count Drupal Projects found"
