#! /bin/bash

# This script will deploy project specified with <project name> as in first argument.
# Project must be completed Processwire-based web site with environment-based config separation.
# It has to have config-prod.php file located at ./site/config/ and a MySQL database dump file dump.sql
# in it's root folder.

# The script will modify production config file with requiring lines of DB name and host address.
# Then it will modify the DB dump file to use newely created database.
# After it's done it will remove dump file and commit suicide.

if [ $# -ne 1 ]; then
    echo 'usage: ./install.sh <project name>';
    echo 'sample: ./install.sh pasabahce';
    exit 0;
fi;

projectName=$1;
#projectName="pasabahce";

configFile='./site/config/config-prod.php'

echo "Performing checks"
if ! [ -f $configFile ]; then
	echo "Config file not found!"
	exit
else
	echo "Config file - OK"
fi

if ! [ -f "./dump.sql" ]; then
	echo "DB dump file not found!"
	exit
else
	echo "DB dump - OK"
fi

echo "\$config->dbName = 'specialproject_"$projectName"';" >> $configFile
echo "\$config->httpHosts = array('"$projectName".woman.ru');" >> $configFile

echo 'use specialproject_'$projectName';' > d.sql

cat dump.sql >> d.sql

mysql -uspecialproject -p9tzmKLFxAyWu8CpL 'specialproject_'$projectName < d.sql
rm d.sql
rm dump.sql
rm $0
