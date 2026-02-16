#!/usr/bin/env bash

apt-get update
apt-get install -y unixodbc unixodbc-dev freetds-dev freetds-bin
pecl install sqlsrv pdo_sqlsrv

docker-php-ext-enable sqlsrv pdo_sqlsrv
