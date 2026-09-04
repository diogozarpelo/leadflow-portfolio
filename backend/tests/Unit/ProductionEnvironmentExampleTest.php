<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductionEnvironmentExampleTest extends TestCase
{
    public function test_it_contains_safe_mysql_production_defaults(): void
    {
        $path = dirname(__DIR__, 2).'/.env.production.example';

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression(
            '/^APP_ENV=production\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^APP_DEBUG=false\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^APP_KEY=\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^DB_CONNECTION=mysql\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^DB_HOST=localhost\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^DB_DATABASE=change_me\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^DB_USERNAME=change_me\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^DB_PASSWORD=change_me\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^DB_CHARSET=utf8mb4\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^DB_COLLATION=utf8mb4_unicode_ci\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^LEAD_DELIVERY_ENABLED=false\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^LEAD_DELIVERY_DRIVER=null\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^LEAD_DELIVERY_TIMEOUT=10\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^LEAD_DELIVERY_MAX_ATTEMPTS=3\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^LEAD_DELIVERY_LOG_LEVEL=info\s*$/m',
            $contents
        );
        $this->assertMatchesRegularExpression(
            '/^LEAD_DELIVERY_LOG_DAYS=14\s*$/m',
            $contents
        );
    }
}
