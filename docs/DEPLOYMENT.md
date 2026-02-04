# DEPLOYMENT Documentation

This document outlines the steps for deploying applications built with the Echo PHP framework, with a focus on the Docker environment.

## Docker Environment

The Echo framework is designed to run within Docker containers. This ensures a consistent and isolated environment for development and deployment.

### Container Names

- `php` - PHP 8.3-FPM (run ALL PHP commands here: tests, composer, console)
- `nginx` - Nginx web server
- `db` / `mariadb` - MariaDB 11 database

### Starting the Docker Environment

To bring up the Docker containers:

```bash
docker-compose up -d
```

### Checking Running Containers

You can verify which containers are running:

```bash
docker ps
```

### Viewing Container Logs

To debug or monitor container activity, you can view their logs:

```bash
docker-compose logs php
docker-compose logs nginx
docker-compose logs db
```

### Accessing Database CLI

To interact with the database directly via its command-line interface:

```bash
docker-compose exec -it db mariadb -u root -p
```

### Interactive Shell in PHP Container

For executing various PHP commands (composer, console, etc.) or just an interactive shell:

```bash
docker-compose exec -it php bash
```