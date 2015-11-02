#/bin/bash
drush status | egrep "Drupal version" | egrep -o "[678]\.[0-9]+"