# Drupal admin scripts

Scripts that let you manage many drupal sites at once. The most recent is ``drupal-locate.php``. The rest is a little obsolete and poorly documented.

## Usage

    # Get a list of all your drupal sites
    ./drupal-locate.php /etc/apache2/sites-enabled/

    # Export your drupal sites to php
    ./drupal-locate.php --csv /etc/apache2/sites-enabled/  > /tmp/export.csv

    # Run a custom command on each site
    ./drupal-locate.php --exec="drush status" /etc/apache2/sites-enabled/

## Obsolete

These scripts may or may not work. They're a little outdated.

- drupal-clear-all-caches.sh
- drupal-cron.sh
- drupal-execute.sh
- drupal-locate.sh
- drupal-show-versions.sh
- drupal-watchdog.sh
