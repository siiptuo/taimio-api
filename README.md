# Tiima

Tiima is a time tracking software. This repository contains the backend REST
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

    $ createdb tiima

If you have a dump/backup, simply import it using:

    $ psql tiima < tiima.sql

Otherwise configure [Phinx](https://phinx.org/) migrations. It's recommended to
simply copy `phinx.yml.sample` as `phinx.yml` and fill in your database
information.

    $ cp phinx.sample.yaml phinx.yaml
    $ vi phinx.yml

Then run migrations:

    $ composer run-script migrate

For development you may want to generate some test data:

    $ composer run-script seed

## License

MIT
