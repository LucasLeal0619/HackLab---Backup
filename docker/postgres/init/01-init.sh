#!/bin/bash
# Executado pelo PostgreSQL só na PRIMEIRA inicialização do volume (banco vazio).
# hacklab_dev é criado pelo POSTGRES_DB; aqui criamos o banco exclusivo de testes
# e o schema `hacklab` nos dois bancos (o mesmo schema usado no Supabase em produção).
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<SQL
CREATE DATABASE ${HACKLAB_TEST_DATABASE} OWNER ${POSTGRES_USER};
SQL

for db in "$POSTGRES_DB" "$HACKLAB_TEST_DATABASE"; do
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$db" <<SQL
CREATE SCHEMA IF NOT EXISTS hacklab AUTHORIZATION ${POSTGRES_USER};
SQL
done
