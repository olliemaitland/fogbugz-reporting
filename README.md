fogbugz-reporting
=================

Fogbugz reporting application

Installation:
==================

    curl -sS https://getcomposer.org/installer | php

Configuation:
==================

    cd /path/to/directory/
    php console.php setup:fogbugz https://acme.fogbugz.com me@domain.com password

Running synchronisation:
==================

Run as a cron job as often as your please:

php console.php pull:worklogs