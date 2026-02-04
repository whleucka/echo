# Echo PHP Framework Documentation

This is the central navigation hub for the Echo PHP framework documentation.

## Project Overview

Echo is a custom PHP 8.2+ MVC framework built for speed and simplicity. It uses PHP 8 attributes for routing, PHP-DI for dependency injection, and Twig for templating.

## Architecture Summary

### Directory Structure

- `app/` - Application code (controllers, models, providers)
- `src/` - Framework code (Echo namespace)
- `config/` - Configuration files
- `migrations/` - Database migration files
- `templates/` - Twig templates (cache in `templates/.cache`)
- `public/` - Web root (entry point: `index.php`)
- `bin/console` - CLI entry point
- `tests/` - PHPUnit test suite

### Namespaces

- `App\` → `app/` - Application code
- `Echo\Framework\` → `src/Framework/` - Framework implementation
- `Echo\Interface\` → `src/Interface/` - Contracts/interfaces
- `Tests\` → `tests/` - Test suite

## Documentation Sections

*   [Quick Reference](QUICK_REF.md)
*   [API Documentation](API.md)
*   [Database Documentation](DATABASE.md)
*   [Testing](TESTING.md)
*   [Deployment](DEPLOYMENT.md)
*   [Troubleshooting](troubleshooting/)
    *   [Database Troubleshooting](troubleshooting/database.md)
    *   [Performance Troubleshooting](troubleshooting/performance.md)
    *   [Common Errors Troubleshooting](troubleshooting/common-errors.md)