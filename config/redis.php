<?php

declare(strict_types=1);

// Toggle Redis from environment without changing code.
if (!defined('REDIS_ENABLED')) {
    define('REDIS_ENABLED', in_array(strtolower((string) getenv('REDIS_ENABLED')), ['1', 'true', 'yes', 'on'], true));
}
if (!defined('REDIS_HOST')) {
    define('REDIS_HOST', getenv('REDIS_HOST') ?: '127.0.0.1');
}
if (!defined('REDIS_PORT')) {
    define('REDIS_PORT', (int) (getenv('REDIS_PORT') ?: 6379));
}
if (!defined('REDIS_PASSWORD')) {
    define('REDIS_PASSWORD', (string) (getenv('REDIS_PASSWORD') ?: ''));
}
if (!defined('REDIS_DB')) {
    define('REDIS_DB', (int) (getenv('REDIS_DB') ?: 0));
}
if (!defined('REDIS_TIMEOUT')) {
    define('REDIS_TIMEOUT', (float) (getenv('REDIS_TIMEOUT') ?: 1.5));
}
if (!defined('REDIS_PREFIX')) {
    define('REDIS_PREFIX', (string) (getenv('REDIS_PREFIX') ?: 'pharma_care:'));
}

/**
 * Create and return a Redis client instance.
 *
 * Returns one of:
 * - \Redis (phpredis extension)
 * - \Predis\Client (predis package)
 * - null when Redis is disabled or unavailable
 */
function getRedisClient() {
    static $client = false;

    if ($client !== false) {
        return $client;
    }

    if (!REDIS_ENABLED) {
        $client = null;
        return $client;
    }

    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            $connected = $redis->connect(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);
            if (!$connected) {
                $client = null;
                return $client;
            }

            if (REDIS_PASSWORD !== '') {
                $redis->auth(REDIS_PASSWORD);
            }

            if (REDIS_DB > 0) {
                $redis->select(REDIS_DB);
            }

            if (method_exists($redis, 'setOption')) {
                $redis->setOption(Redis::OPT_PREFIX, REDIS_PREFIX);
            }

            $client = $redis;
            return $client;
        } catch (Throwable $e) {
            $client = null;
            return $client;
        }
    }

    if (class_exists('Predis\\Client')) {
        try {
            $params = [
                'scheme' => 'tcp',
                'host' => REDIS_HOST,
                'port' => REDIS_PORT,
                'database' => REDIS_DB,
                'read_write_timeout' => REDIS_TIMEOUT,
            ];

            if (REDIS_PASSWORD !== '') {
                $params['password'] = REDIS_PASSWORD;
            }

            $predis = new Predis\Client($params, ['prefix' => REDIS_PREFIX]);
            $predis->ping();
            $client = $predis;
            return $client;
        } catch (Throwable $e) {
            $client = null;
            return $client;
        }
    }

    $client = null;
    return $client;
}

function redisIsHealthy(): bool {
    $client = getRedisClient();
    if (!$client) {
        return false;
    }

    try {
        if ($client instanceof Redis) {
            return $client->ping() !== false;
        }

        $pong = (string) $client->ping();
        return stripos($pong, 'PONG') !== false;
    } catch (Throwable $e) {
        return false;
    }
}
