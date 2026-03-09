# syncrone

## Database Configuration

The application requires MySQL/MariaDB or can use SQLite for temporary testing.

### Environment Variables

Database configuration is available through environment variables. See `.env.example` for reference:

- `DB_HOST` - Database hostname (e.g., `127.0.0.1` or `localhost`)
- `DB_PORT` - Database port (default: `3306`)
- `DB_USER` - Database username (default: `root`)
- `DB_PASS` - Database password
- `DB_NAME` - Database name (default: `pharma_care`)
- `SITE_BASE_PATH` - Application base path (leave empty for root, or set to `/subdirectory`)
- `USE_SQLITE_TEMP` - Use SQLite instead of MySQL (set to `true` for testing)
- `TEMP_SQLITE_PATH` - Path to SQLite database file

**Important Notes**:
- `DB_HOST` should be either a hostname OR an IP address, not both (e.g., use `127.0.0.1` or `localhost`, NOT `localhost/103.108.220.222`)
- `SITE_BASE_PATH` is auto-detected if not set. Set it explicitly if assets (CSS/JS) are not loading correctly

### Automatic Schema Migration

The application automatically creates all required tables on first run. No manual SQL import is required, though `sql/pharma_care.sql` is provided for reference.

## Redis Config

Redis configuration is available in `config/redis.php`.

Environment keys are documented in `config/redis.env.example`:

- `REDIS_ENABLED`
- `REDIS_HOST`
- `REDIS_PORT`
- `REDIS_PASSWORD`
- `REDIS_DB`
- `REDIS_TIMEOUT`
- `REDIS_PREFIX`

### Example usage

```php
require_once __DIR__ . '/config/redis.php';

$redis = getRedisClient();
if ($redis) {
	// Redis is connected and ready.
}
```

### Health check helper

```php
if (redisIsHealthy()) {
	// Redis responds to ping.
}
```