#!/bin/bash

##
## Locate Drupal
##
## Findet alle lokalen Drupal-Instanzen innerhalb der Apache-VHosts-Konfiguration
##
## Aufruf:
## drupal-locate.sh TrefferListe VHostsDoku [DrushKommando]
##
## Version: 0.0.1
## Author: Jonas Westphal (jw@yu.am), Stephan Grötschel <groetschel@zebralog.de>
##
## https://jw.is
##
##

outfile=$1
drush_task=$2

DRUPAL_CMD=drush

# Return 1 if element exists in array
containsElement () {
  local e
  for e in "${@:2}"; do [[ "$e" == "$1" ]] && return 1; done
  return 0
}


# Check for proper number of command line args.
EXPECTED_ARGS=1
E_BADARGS=65

if [ $# -lt $EXPECTED_ARGS ]
then
  echo "Usage: `basename $0` found-list.outfile [drushCommand]"
  exit $E_BADARGS
fi

# Find all active web projects
# Search for the DocumentRoot path in active vhost configurations.
SITES=`grep -R -oh -E "^[	 \s]*DocumentRoot \"?([a-zA-Z0-9_\/\.\-]+)\"?" /etc/apache2/sites-enabled`

# Strip the strings DocumentRoot and quotes
SITES=${SITES//DocumentRoot/}
SITES=${SITES//\"/}

# Print summary of projects we'll test. Ignore path if it occurs multiple times
echo "-- Checking for duplicates."
for file in $SITES; do
	containsElement $file "${tasks[@]}"
	if [ $? == "0" ]; then
		tasks=("${tasks[@]}" $file)
	else
		echo "Skipping $file because it's a duplicate."
	fi
done

echo "-- Find active drupal installations"

# Überprüfe rekursiv, ob/wo eine settings.php vorliegt
#count=0

for file in ${tasks[@]}; do
	if [ -d $file ]; then
		# echo "Check if $file is drupal installation."

		find -L $file -name settings.php 2>/dev/null | while read settingsFile

		do
			#TODO: Skip VHost default and /var/www
			#FIXME: Zähler bauen
			#count=`expr $count + 1`

			dirname=${settingsFile%/*}
			isDrupal=`(grep "update_free_access" $settingsFile)`

			if [ -n "$isDrupal" ]; then
				echo "Drupal found in $dirname"
				echo $dirname >> $outfile

				if [ -n "$drush_task" ]; then
					echo "Running drush $drush_task"
					pushd $dirname > /dev/null
					#FIXME: Das hier ist nicht elegant, aber lenny unterstützt kein drush :/
					$DRUPAL_CMD $drush_task
					popd > /dev/null

					# Don't chown because it is not expected by the user.
					# chown -hR www-data:www-data $dirname
				fi
			else
				echo "[Warning] No Drupal found in $dirname"
			fi
		done
	else
		echo "Not checking $file"
	fi
done

#echo "Total $count Drupal Projects found"
