# Nextcloud Circles Share Migration 
PHP script to migrate all shares to nextcloud circles, which get replaced by shares to the individual circle members.
The script writes all migrated shares into CSV files.

![CSV Export](docs/csv_export.png)

## Setup
Create `.env.local` file and set database connection params.
```console
cp .env .env.local
```

## Usage
- List all commands
```console
php app.php
```
- Run migration
```console
php app.php migrate
```
