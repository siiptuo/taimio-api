#!/usr/bin/env bash

set -e

export TAIMIO_SECRET=testing
export PHINX_DBHOST=localhost
export PHINX_DBNAME=$TAIMIO_DBNAME
export PHINX_DBUSER=$TAIMIO_USERNAME
export PHINX_DBPASS=$TAIMIO_PASSWORD

echo ''
echo '==============='
echo 'Create database'
echo '==============='
echo ''

dropdb --if-exists $TAIMIO_DBNAME
createdb $TAIMIO_DBNAME

vendor/bin/phinx migrate -e testing

echo ''
echo '=============='
echo 'Run web server'
echo '=============='
echo ''

cd public
php -S localhost:9000 index.php &
PHP_PID=$!
cd ..

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

dropdb $TAIMIO_DBNAME
kill $PHP_PID
