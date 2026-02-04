# TESTING Documentation

This document covers testing procedures and best practices for the Echo PHP framework.

## Running Tests

Tests are executed using PHPUnit. It is crucial to run all PHP commands, including tests, inside the `php` Docker container.

To run tests:

```bash
docker-compose exec -it php ./vendor/phpunit/phpunit/phpunit tests
```