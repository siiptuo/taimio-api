# Taimio API

[![Build Status](https://travis-ci.org/siiptuo/taimio-api.svg?branch=master)](https://travis-ci.org/siiptuo/taimio-api)
[![Code Climate](https://codeclimate.com/github/siiptuo/taimio-api/badges/gpa.svg)](https://codeclimate.com/github/siiptuo/taimio-api)

Taimio is a time tracking software. This repository contains the backend REST
API created using PHP and PostgreSQL.

## Running

### Application

Install required dependencies:

    $ composer install

Then [configure your webserver of
choice](http://www.slimframework.com/docs/start/web-servers.html) to route
correctly.

### Database

Create a database if needed:

    $ createdb taimio

If you have a dump/backup, simply import it using:

    $ psql taimio < taimio.sql

Otherwise configure [Phinx](https://phinx.org/) migrations. It's recommended to
simply copy `phinx.yml.sample` as `phinx.yml` and fill in your database
information.

    $ cp phinx.sample.yml phinx.yml
    $ vi phinx.yml

Then run migrations:

    $ composer migrate

For development you may want to generate some test data:

    $ composer seed

### Environment variables

- `TAIMIO_DBNAME`
- `TAIMIO_USERNAME`
- `TAIMIO_PASSWORD`
- `TAIMIO_SECRET`

## License

MIT
