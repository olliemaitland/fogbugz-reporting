fogbugz-reporting
=================

Fogbugz reporting application

Installation:
==================

    curl -sS https://getcomposer.org/installer | php

Configuation:
==================
    cd /path/to/directory/

    Setup FogBugz access:

        php console.php setup:fogbugz https://acme.fogbugz.com me@domain.com password

    Setup Google Account access:

        1. Go to API console at https://code.google.com/apis/console
        2. Select "API access" menu item
        3. Create a new "Service Account" or re-use an existing one
        4. Download generated private key (default path is resource/google, this way only filename can be specified below)
        5. Run the following command with the generated credentials:
            php console.php setup:google foo.apps.googleusercontent.com foo@developer.gserviceaccount.com path/to/foo-privatekey.p12

Running synchronisation:
==================

Run as a cron job as often as your please:

    php console.php pull:worklogs 2013-01-01 2013-01-31