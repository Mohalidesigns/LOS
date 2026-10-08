#!/bin/sh
# Runs once, on an empty data directory, as the container superuser.
# Creates fundly_owner / fundly_app and the application database.
set -eu
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
  -v owner_password="$(cat /run/secrets/db_owner_password)" \
  -v app_password="$(cat /run/secrets/db_app_password)" \
  -v db_name="${FUNDLY_DB_NAME:-fundly}" \
  -f /fundly/00-provision-roles.sql
