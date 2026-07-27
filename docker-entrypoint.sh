#!/bin/sh
set -e

# Render sets $PORT at runtime
PORT=${PORT:-80}

# Configure Apache to listen on Render's port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec "$@"
