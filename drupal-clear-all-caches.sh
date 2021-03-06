#!/bin/bash

##
# Find active drupal project (enabled in apache config) and summarize their update status
##

OUTFILE=$HOME/drupal-cache-clear.log

# Return 1 if element exists in array
containsElement () {
  local e
  for e in "${@:2}"; do [[ "$e" == "$1" ]] && return 1; done
  return 0
}


# Purge output file
echo > $OUTFILE

# Find all active web projects
SITES=`grep -R -oh -E "^\s*DocumentRoot (.+)" /etc/apache2/sites-enabled`
SITES=${SITES//DocumentRoot/}

echo "We will check drupal projects in the following paths" >> $OUTFILE
# Print summary of projects we'll test. Ignore path if it occurs multiple times
for file in $SITES; do
  containsElement $file "${tasks[@]}"
  if [ $? == "0" ]; then
    tasks=("${tasks[@]}" $file)
    echo "-> " $file >> $OUTFILE
  else
    echo Skipping duplicate directory $file.
  fi
done

# TODO Print out actual hostname

for file in ${tasks[@]}; do
  if [ -d $file ]; then
    echo  "++ Cache clear for:" $file +++++ 
    echo  "++ Cache clear for:" $file +++++ >> $OUTFILE
    pushd $file > /dev/null
      drush cc all
    popd > /dev/null
  fi
done




