<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Tests\Database;

use Esazykin\LaravelClickHouse\Database\Connection;
use Esazykin\LaravelClickHouse\Database\Query\Builder;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function testQuery()
    {
        $connection = new Connection(['host' => 'localhost']);

        $this->assertInstanceOf(Builder::class, $connection->query());
    }
}