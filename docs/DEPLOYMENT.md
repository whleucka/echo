# DEPLOYMENT Documentation

This document outlines the steps for deploying applications built with the Echo PHP framework, with a focus on the Docker environment.

## Docker Environment

The Echo framework is designed to run within Docker containers. This ensures a consistent and isolated environment for development and deployment.

### Container Names

- `php` - PHP 8.3-FPM with Redis extension (run ALL PHP commands here: tests, composer, console)
- `nginx` - Nginx web server
- `db` / `mariadb` - MariaDB 11 database
- `redis` - Redis 7 Alpine (caching, sessions, rate limiting)

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
docker-compose logs redis
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

### Accessing Redis CLI

To interact with Redis directly:

```bash
docker-compose exec -it redis redis-cli
```

## Environment Configuration

Copy `.env.example` to `.env` and configure your settings:

```bash
cp .env.example .env
```

### Key Environment Variables

```env
# Application
APP_NAME=Echo
APP_URL=http://localhost
APP_DEBUG=true

# Database
DB_HOST=db
DB_NAME=echo
DB_USERNAME=echo
DB_PASSWORD=secret

# Redis (optional - enables caching, sessions, rate limiting)
REDIS_HOST=redis
REDIS_PORT=6379

# Drivers (set to "redis" to use Redis, "file" for file-based fallback)
SESSION_DRIVER=file
CACHE_DRIVER=file
```

## Production Considerations

### Redis

For production, consider:
- Set `SESSION_DRIVER=redis` for horizontal scaling
- Set `CACHE_DRIVER=redis` for better performance
- Configure `REDIS_PASSWORD` for security
- Use Redis persistence (`appendonly yes`) for session durability

### Security

- Set `APP_DEBUG=false` in production
- Use strong `DB_PASSWORD` and `REDIS_PASSWORD`
- Configure proper firewall rules for container ports