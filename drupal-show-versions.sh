#!/bin/bash

##
# Find active drupal project (enabled in apache config) and summarize their update status
##

OUTFILE=$HOME/show-module-versions.log

# Purge output file
echo > $OUTFILE

# Find all active web projects
SITES=`grep -R -oh -E "^\s*DocumentRoot (.+)" /etc/apache2/sites-enabled`
SITES=${SITES//DocumentRoot/}

echo "We will check drupal projects in the following paths" >> $OUTFILE
# TODO Remove duplicates
for file in $SITES; do
  echo "-> " $file >> $OUTFILE
done

for file in $SITES; do
# for file in *; do
   if [ -d $file ]; then
      echo  ++++++++++++ $file ++++++++++++++++++ >> $OUTFILE
      pushd $file 
      drush en -y update
      drush pm-update --security-only -n >> $OUTFILE
      popd 
   fi
done

