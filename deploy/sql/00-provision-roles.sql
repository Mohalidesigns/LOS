-- Fundly LOS: one-time database provisioning, run as a PostgreSQL superuser.
-- Two roles (TRD §2.3, §5.4):
--   fundly_owner  owns the schema; runs migrations only. NOT a superuser.
--   fundly_app    the runtime role used by api/worker/scheduler. Non-owner,
--                 NOSUPERUSER, NOBYPASSRLS, so row-level security always applies.
-- Passwords are supplied by psql variables, never committed:
--   psql -v owner_password=... -v app_password=... -v db_name=fundly -f 00-provision-roles.sql
\set ON_ERROR_STOP on

SELECT format('CREATE ROLE fundly_owner LOGIN PASSWORD %L NOSUPERUSER NOCREATEROLE NOBYPASSRLS', :'owner_password')
 WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'fundly_owner') \gexec
SELECT format('CREATE ROLE fundly_app LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS NOINHERIT', :'app_password')
 WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'fundly_app') \gexec

SELECT format('CREATE DATABASE %I OWNER fundly_owner ENCODING ''UTF8''', :'db_name')
 WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'db_name') \gexec

\connect :db_name
ALTER SCHEMA public OWNER TO fundly_owner;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO fundly_app;
-- Installation fingerprint reads the cluster system identifier (TRD §2.6).
GRANT EXECUTE ON FUNCTION pg_control_system() TO fundly_owner, fundly_app;
