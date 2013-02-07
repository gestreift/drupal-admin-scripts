#!/bin/bash
#
# Write watchdog messages from multiple drupal installations into one file
# 

LOGFILE=$HOME/wd-show.log

# TODO Get active drupal installations from apache config

for file in *; do
   if [ -d $file ]; then
      echo  ++++++++++++ $file ++++++++++++++++++ >> $LOGFILE
      pushd $file
      drush wd-show --count=10000 --full >> $LOGFILE
      popd 
   fi
done

