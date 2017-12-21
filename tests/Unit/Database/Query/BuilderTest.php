<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Tests\Unit\Database\Query;

use PHPUnit\Framework\TestCase;
use Esazykin\LaravelClickHouse\Tests\Helpers;
use Tinderbox\ClickhouseBuilder\Query\Grammar;
use Esazykin\LaravelClickHouse\Database\Connection;
use Tinderbox\ClickhouseBuilder\Query\Enums\Format;
use Esazykin\LaravelClickHouse\Database\Query\Builder;

/**
 * @property \Mockery\MockInterface|Connection connection
 * @property Builder builder
 */
class BuilderTest extends TestCase
{
    use Helpers;

    protected function setUp()
    {
        parent::setUp();

        $this->connection = $this->mock(Connection::class);
        $this->builder = new Builder(
            $this->connection,
            new Grammar()
        );
        $this->builder->from($this->faker()->word);
    }

    public function testGet(): void
    {
        $connectionResult = $this->faker()->shuffle(range(1, 5));

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult);

        $builderResult = $this->builder->get();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $builderResult);
        $this->assertSame($connectionResult, $builderResult->toArray());
    }

    public function testCount(): void
    {
        $connectionResult = range(1, 5);

        $this->connection
            ->shouldReceive('select')
            ->andReturn([['count' => count($connectionResult)]]);

        $builderResult = $this->builder->count();

        $this->assertCount($builderResult, $connectionResult);
    }

    public function testFirst(): void
    {
        $connectionResult = $this->faker()->shuffle(range(1, 5));

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult);

        $builderResult = $this->builder->first();

        $this->assertSame($connectionResult[0], $builderResult);
    }

    public function testNewQuery(): void
    {
        $this->assertInstanceOf(Builder::class, $this->builder->newQuery());
    }

    public function testInsertFiles(): void
    {
        $this->connection
            ->shouldReceive('insertFiles')
            ->andReturn([]);

        $builderResult = $this->builder->insertFiles(['column_1', 'column_2'], []);
        $this->assertSame([], $builderResult);
    }

    public function testInsert(): void
    {
        $this->assertFalse($this->builder->insert([]));

        $insertedRow = [
            $this->faker()->word => $this->faker()->randomDigit,
            $this->faker()->randomLetter => $this->faker()->randomDigit,
            $this->faker()->numerify('column_#') => $this->faker()->randomLetter,
        ];
        $inserted = [$insertedRow];

        $generatedSql = sprintf(
            'INSERT INTO `%s` (%s) FORMAT %s (?, ?, ?)',
            $this->builder->getFrom()->getTable(),
            collect($insertedRow)
                ->keys()
                ->sort()
                ->map(function (string $columnName) {
                    return sprintf('`%s`', $columnName);
                })
                ->implode(', '),
            Format::VALUES
        );

        ksort($insertedRow);
        $this->connection
            ->shouldReceive('insert')
            ->withArgs([
                $generatedSql,
                collect($insertedRow)->values()->toArray(),
            ])
            ->andReturn(true);

        $this->assertTrue($this->builder->insert($inserted));
    }
}
