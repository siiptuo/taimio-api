#!/usr/bin/env bash

set -e

export TIIMA_DBNAME='tiima_test'
export TIIMA_USERNAME='tuomas'
export TIIMA_PASSWORD=''

echo ''
echo '==============='
echo 'Create database'
echo '==============='
echo ''

dropdb --if-exists tiima_test
createdb tiima_test

vendor/bin/phinx migrate -e testing

echo ''
echo '=============='
echo 'Run web server'
echo '=============='
echo ''

php -S localhost:9000 index.php &

echo ''
echo '========='
echo 'Run tests'
echo '========='
echo ''

vendor/bin/behat

echo ''
echo '===='
echo 'Exit'
echo '===='
echo ''

dropdb tiima_test
kill %1
