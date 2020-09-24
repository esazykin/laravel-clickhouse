<?php

declare(strict_types=1);

namespace Bavix\LaravelClickHouse\Tests\Unit\Database\Eloquent;

use Bavix\LaravelClickHouse\Database\Connection;
use Bavix\LaravelClickHouse\Database\Eloquent\Collection;
use Bavix\LaravelClickHouse\Tests\EloquentModelCastingTest;
use Bavix\LaravelClickHouse\Tests\Helpers;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Mockery\Mock;
use PHPUnit\Framework\TestCase;

/**
 * @property Mock|Connection connection
 */
class CollectionTest extends TestCase
{
    use Helpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->mock(Connection::class);

        $this->connection
            ->shouldReceive('getName')
            ->andReturn((new EloquentModelCastingTest())->getConnectionName());

        /** @var Mock|DatabaseManager $resolver */
        $resolver = $this->mock(DatabaseManager::class);
        $resolver->shouldReceive('connection')
            ->andReturn($this->connection);

        EloquentModelCastingTest::setConnectionResolver($resolver);
    }

    public function testMapModelToModel(): void
    {
        $connectionResult = collect()
            ->times(5, function (int $id) {
                return ['id' => $id];
            });

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult->toArray());

        $now = now();

        $models = EloquentModelCastingTest::all()
            ->map(function (EloquentModelCastingTest $model) use ($now) {
                $model->datetimeAttribute = $now;

                return $model;
            });

        self::assertInstanceOf(Collection::class, $models);
        self::assertCount($connectionResult->count(), $models);

        $models->each(function (EloquentModelCastingTest $model, int $key) use ($now) {
            self::assertSame($key + 1, $model->id);
            self::assertInstanceOf(Carbon::class, $model->datetimeAttribute);
            self::assertSame($now->toDateTimeString(), $model->datetimeAttribute->toDateTimeString());
        });
    }

    public function testMapModelToArray(): void
    {
        $connectionResult = collect()
            ->times(5, function (int $id) {
                return ['id' => $id];
            });

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult->toArray());

        $now = now();

        $collection = EloquentModelCastingTest::all()
            ->map(function (EloquentModelCastingTest $model) use ($now) {
                return [
                    'id'                => $model->id,
                    'datetimeAttribute' => $now,
                ];
            });

        self::assertInstanceOf(\Illuminate\Support\Collection::class, $collection);
        self::assertCount($connectionResult->count(), $collection);

        $collection->each(function (array $row, int $key) use ($now) {
            self::assertSame($key + 1, $row['id']);
            self::assertInstanceOf(Carbon::class, $row['datetimeAttribute']);
            self::assertSame($now->toDateTimeString(), $row['datetimeAttribute']->toDateTimeString());
        });
    }

    /**
     * @dataProvider findDataProvider
     *
     * @param $key
     */
    public function testFind($key): void
    {
        $connectionResult = collect()
            ->times(5, function (int $id) {
                return ['id' => $id];
            });

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult->toArray());

        $found = EloquentModelCastingTest::all()->find($key);

        if (is_array($key)) {
            self::assertInstanceOf(Collection::class, $found);
            self::assertCount(count($key), $found);
        } else {
            self::assertInstanceOf(EloquentModelCastingTest::class, $found);
        }
    }

    /**
     * @dataProvider containsDataProvider
     *
     * @param bool $expected
     * @param $key
     * @param null $operator
     * @param null $value
     */
    public function testContains(bool $expected, $key, $operator = null, $value = null): void
    {
        $connectionResult = collect()
            ->times(5, function (int $id) {
                return ['id' => $id];
            });

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult->toArray());

        if ($operator !== null && $value !== null) {
            $contains = EloquentModelCastingTest::all()->contains($key, $operator, $value);
        } else {
            $contains = EloquentModelCastingTest::all()->contains($key);
        }

        self::assertSame($expected, $contains);
    }

    public function testGet(): void
    {
        $connectionResult = collect()
            ->times(5, function (int $id) {
                return [
                    'id'             => $id,
                    'floatAttribute' => (string) $this->faker()->randomFloat(2),
                ];
            });

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult->toArray());

        $models = EloquentModelCastingTest::all();

        self::assertInstanceOf(Collection::class, $models);
        self::assertCount($connectionResult->count(), $models);
        $models = $models->map(function (EloquentModelCastingTest $model) {
            return $model->toArray();
        });

        self::assertSame(
            $connectionResult
                ->map(function (array $row) {
                    $row['floatAttribute'] = (float) $row['floatAttribute'];

                    return $row;
                })
                ->toArray(),
            $models->toArray()
        );
    }

    public function findDataProvider(): array
    {
        return [
            [5],
            [
                tap(new EloquentModelCastingTest(), function (EloquentModelCastingTest $model) {
                    $model->id = 5;
                }),
            ],
            [1, 5],
        ];
    }

    public function containsDataProvider()
    {
        return [
            [true, 5],
            [false, 6],
            [true, 'id', '>=', 5],
            [false, 'id', '>=', 6],
        ];
    }
}
