# Nextcloud Circles Share Migration 
PHP script to migrate all shares to nextcloud circles into single user shares. The script writes all migrated share
into CSV file.

![CSV Export](docs/csv_export.png)

## Setup
Create `.env.local` file and set database connection params.
```console
cp .env .env.local
```

## Usage
- List all command
```console
php app.php
```
- Run migration
```console
php app.php migrate
```
