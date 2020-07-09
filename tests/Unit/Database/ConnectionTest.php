<?php

declare(strict_types=1);

namespace Bavix\LaravelClickHouse\Tests\Database;

use PHPUnit\Framework\TestCase;
use Bavix\LaravelClickHouse\Database\Connection;
use Bavix\LaravelClickHouse\Database\Query\Builder;

class ConnectionTest extends TestCase
{
    public function testQuery(): void
    {
        $connection = new Connection([
            'host' => 'localhost',
            'port' => '8123',
            'database' => 'default',
        ]);

        self::assertInstanceOf(Builder::class, $connection->query());
    }
}
