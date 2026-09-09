#!/bin/sh
set -eu

mkdir -p /var/www/html/runtime
chown -R www-data:www-data /var/www/html/runtime
php /var/www/html/docker/bootstrap.php
exec "$@"
