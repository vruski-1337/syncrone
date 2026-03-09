# syncrone

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