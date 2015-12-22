# Modified ProcessWire CMS for some of my small projects

## Primary difference to the base PW

1. [Separeted configurations](#config)
2. [Basic authentication](#auth)
3. [Disabled Blowfish](#blowfish)
4. [Deploy script](#deploy)

## <a name="config"></a>Separeted configuration

Although ProcessWire already implements separation of dev- and prod-configs, it does not 
provides common parameters, so any changes should be made in both files.

I use separations based on web-server variable APPLICATION_ENV or on it's absence. By default,
this variable set to use production environment. On development or test servers it can be redefined
to use corresponding environment settings.
(There's a little Python script that helps to create localhost dev-domain: http://github.com/stmswitcher/siter)

Common config parameters are described in ./site/config.php. Environment-dependent parameters are located
in ./site/config/.

For example, if developer's web-server have APPLICATION_ENV set to 'dev', the config file ./site/config/config-dev.php
will be used.

## <a name="auth></a>Basic authentication

Basic authentication described in ./auth.php
It used for project's debug on production servers (or, if APPLICATION_ENV is set to 'prod').
When project's ready for release the file should be renamed or removed.

## <a name="blowfish"></a>Disabled Blowfish

Since some of production servers running PHP version < 5.3.7 and most of developer's machines running
modern PHP versions, sometimes, there's an issue with administrators being unable to log in after
the site was deployed.

Most of the servers with old PHP version cannot be updated at the moment, so, to ease the deployment
process, Blowfish was disabled.
It can be enabled anytime in ./wire/Password.php.

## <a name="deploy"></a>Deploy script

The script file ./deploy.sh automates the deployment process.
It's run with single argument - project's short name (example: ./deploy.sh myproject).
There should be MySQL DB-dump located it the same folder (./dump.sql) for script to properly work.

If there's something wrong, then the script will let you know it before applying any changes.

Then it will create new database based on project's short name, given to script as an argument,
and apply dump to the new DB.

When it successfully finishes, it will destroy itself.