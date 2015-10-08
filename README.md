# kazoo-regextern
external number of providers integration in kazoo

this is for internal use only, to integration with telecom services

1. INSTALL kazoo-platform
2. INSTALL monster-ui (from this repository because of adds in ui)
3. INSTALL THIS in the subfolder where one of kazoo-freeswitch is, AND ADD php-cli with fping support there
    - config first your couchdb and node settings in $hosts = "couchdb1.com couchdb2.com couchdb3.com" on config.php
    - kazoo-regextern move this file  to init.d to start as service (service kazoo_regextern start)
4. FOLLOW the instruction on google-groups on the 2600hz-dev to allow ONLY! calls from your providers

If you have more ideas let me known on this github repository

ROADMAP
-------
-Add multi-freeswitch support to move at failover