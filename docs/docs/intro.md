---
id: intro
title: Introduction
sidebar_position: 1
slug: /intro
---

# LazyMePHP

LazyMePHP is a PHP 8+ rapid-development framework built around a single idea:

> **The database schema is the application.**

Point it at a database and you instantly get a full CRUD web UI, a GraphQL API, JWT-authenticated REST endpoints, and a developer dashboard — with **zero code generation**.

## What you get out of the box

- MySQL, SQLite, and MSSQL support
- Runtime ORM — no generated model files, schema introspected at boot
- Generic CRUD web UI driven by the live schema
- GraphQL API auto-built from the schema (`POST /graphql`)
- JWT authentication with refresh-token rotation for SPA / API consumers
- Role-based access control (RBAC)
- Database migration system
- Seeder and factory system for test data
- Audit log for all data mutations
- Batman developer dashboard with secure login
- Schema file cache for OPcache-friendly production deployments
- OpenAPI 3.0 spec auto-generated from live schema (`GET /openapi.json`)
- Health check endpoint (`GET /health`)
- Request ID tracing on every response (`X-Request-ID`)
- Pluggable cache layer: Redis, APCu, or in-process array
- General-purpose rate limiting middleware
- Background queue system: sync, database, or Redis drivers
- Standalone `FormRequest` validation
- File storage abstraction with local disk driver
- Multi-tenancy support (subdomain, header, path, or JWT resolution)

## Quick start

```bash
git clone https://github.com/Peixinho/LazyMePHP myProject
cd myProject && rm -rf .git
composer install
cp .env.example .env   # edit DB_* and APP_* values
php LazyMePHP migrate  # create framework tables
php LazyMePHP serve
```

Navigate to `http://localhost:8080`. Every table in the database is immediately accessible at `/{table}` with list, create, edit, and delete pages, and via the GraphQL endpoint at `POST /graphql`.

## How it works

On every request `LazyMePHP::boot()` (called from `App/Routes/Routes.php`):

1. Reads the list of tables from the schema cache, or queries the DB directly.
2. Emits a `X-Request-ID` header for tracing.
3. Registers 6 CRUD web routes per table via `Core\AutoRouter`.
4. Registers `POST /graphql` via `Core\GraphQL\Endpoint`.
5. Registers `POST /auth/login`, `POST /auth/logout`, `POST /auth/refresh`, `GET /auth/me` when `AUTH_TABLE` is set.
6. Registers `GET /health` and `GET /openapi.json`.

No files are generated. Schema introspection happens once per table per process (cached in memory), and optionally pre-warmed to disk for production.

## Requirements

- PHP 8.1+
- Composer
- MySQL, MSSQL, or SQLite
