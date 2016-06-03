#!/usr/bin/env bash

set -e

export TAIMIO_DBNAME='taimio_test'
export TAIMIO_USERNAME='tuomas'
export TAIMIO_PASSWORD=''

echo ''
echo '==============='
echo 'Create database'
echo '==============='
echo ''

dropdb --if-exists taimio_test
createdb taimio_test

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

dropdb taimio_test
kill %1
