# Drupal admin scripts

Scripts that let you manage many drupal sites at once. The most recent is ``drupal-locate.php``. The rest is a little obsolete and poorly documented.

## Manage many drupal sites

Use drupal-locate.php if you want to manage all your active drupal sites. Just run
``./drupal-locate.php /etc/apache2/sites-enabled/`` to get a full list of your vhosts
and their configuration.

Example:

    $ ./drupal-locate.php /etc/apache2/sites-enabled/

    localhost
      ServerName:  localhost
      DocumentRoot: /var/www/
      Config: 000-default

    myproject1.localhost
      ServerName:  myproject1.localhost
      DocumentRoot: /var/www/myproject1
      Config: myproject1

    myproject2.localhost
      ServerName:  myproject2.localhost
      ServerAlias: myproject2.example.com, myproject2
      DocumentRoot: /home/user/development/myproject2/htdocs
      Config: myproject2

    ...

### Usage

    # Get a list of all your vhosts and their configuration
    ./drupal-locate.php /etc/apache2/sites-enabled/

    # Export your vhosts to php
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
