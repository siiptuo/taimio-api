#!/usr/bin/env bash

export TIIMA_DBNAME='tiima_test'
export TIIMA_USERNAME='tuomas'
export TIIMA_PASSWORD=''

dropdb --if-exists tiima_test
createdb tiima_test

vendor/bin/phinx migrate -e testing

php -S localhost:9000 index.php &

vendor/bin/behat

dropdb tiima_test
kill %1
