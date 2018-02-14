#!/usr/bin/env bash

mkdir -p output

composer install

OC_PATH=../../../../
CORE_INT_TESTS_PATH=tests/integration/
OCC=${OC_PATH}occ

SCENARIO_TO_RUN=$1
HIDE_OC_LOGS=$2

# avoid port collision on jenkins - use $EXECUTOR_NUMBER
if [ -z "$EXECUTOR_NUMBER" ]; then
    EXECUTOR_NUMBER=0
fi
PORT=$((8080 + $EXECUTOR_NUMBER))
echo $PORT
php -S localhost:$PORT -t ../../../../ &
PHPPID=$!
echo $PHPPID

export TEST_SERVER_URL="http://localhost:$PORT/ocs/"

#Set up personalized skeleton
$OCC config:system:set skeletondirectory --value="$(pwd)/$OC_PATH""$CORE_INT_TESTS_PATH""skeleton"

#Set up mailhog to send emails
$OCC config:system:set mail_domain --value="foobar.com"
$OCC config:system:set mail_from_address --value="owncloud"
$OCC config:system:set mail_smtpmode --value="smtp"
$OCC config:system:set mail_smtphost --value="127.0.0.1"
$OCC config:system:set mail_smtpport --value="1025"
#We cannot set password with csrf enabled
$OCC config:system:set csrf.disabled --value="true"

#Enable needed app
cp -R ./app ../../../notificationsintegrationtesting
$OCC app:enable notifications
$OCC app:enable notificationsintegrationtesting
$OCC app:enable testing

vendor/bin/behat --strict -f junit -f pretty $SCENARIO_TO_RUN
RESULT=$?

kill $PHPPID

#Disable apps
$OCC app:disable notifications
$OCC app:disable notificationsintegrationtesting
$OCC app:disable testing
rm -rf ../../../notificationsintegrationtesting

if [ -z $HIDE_OC_LOGS ]; then
	tail "${OC_PATH}/data/owncloud.log"
fi

echo "runsh: Exit code: $RESULT"
exit $RESULT
