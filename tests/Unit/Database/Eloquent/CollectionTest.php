<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Tests\Unit\Database\Eloquent;

use Mockery\Mock;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\DatabaseManager;
use Esazykin\LaravelClickHouse\Tests\Helpers;
use Esazykin\LaravelClickHouse\Database\Connection;
use Esazykin\LaravelClickHouse\Database\Eloquent\Collection;
use Esazykin\LaravelClickHouse\Tests\EloquentModelCastingTest;

/**
 * @property Mock|Connection connection
 */
class CollectionTest extends TestCase
{
    use Helpers;

    protected function setUp()
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

    public function testMapModelToModel()
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

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount($connectionResult->count(), $models);

        $models->each(function (EloquentModelCastingTest $model, int $key) use ($now) {
            $this->assertSame($key + 1, $model->id);
            $this->assertInstanceOf(Carbon::class, $model->datetimeAttribute);
            $this->assertSame($now->toDateTimeString(), $model->datetimeAttribute->toDateTimeString());
        });
    }

    public function testMapModelToArray()
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
                    'id' => $model->id,
                    'datetimeAttribute' => $now,
                ];
            });

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $collection);
        $this->assertCount($connectionResult->count(), $collection);

        $collection->each(function (array $row, int $key) use ($now) {
            $this->assertSame($key + 1, $row['id']);
            $this->assertInstanceOf(Carbon::class, $row['datetimeAttribute']);
            $this->assertSame($now->toDateTimeString(), $row['datetimeAttribute']->toDateTimeString());
        });
    }

    /**
     * @dataProvider findDataProvider
     * @param $key
     */
    public function testFind($key)
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
            $this->assertInstanceOf(Collection::class, $found);
            $this->assertCount(count($key), $found);
        } else {
            $this->assertInstanceOf(EloquentModelCastingTest::class, $found);
        }
    }

    public function testContains()
    {
        $key = 5;

        $connectionResult = collect()
            ->times(5, function (int $id) {
                return ['id' => $id];
            });

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult->toArray());

        $this->assertTrue(EloquentModelCastingTest::all()->contains($key));
    }

    public function testGet()
    {
        $connectionResult = collect()
            ->times(5, function (int $id) {
                return [
                    'id' => $id,
                    'floatAttribute' => (string) $this->faker()->randomFloat(2),
                ];
            });

        $this->connection
            ->shouldReceive('select')
            ->andReturn($connectionResult->toArray());

        $models = EloquentModelCastingTest::all();

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount($connectionResult->count(), $models);
        $models = $models->map(function (EloquentModelCastingTest $model) {
            return $model->toArray();
        });

        $this->assertSame(
            $connectionResult
                ->map(function (array $row) {
                    $row['floatAttribute'] = (float) $row['floatAttribute'];

                    return $row;
                })
                ->toArray(),
            $models->toArray()
        );
    }

    public function findDataProvider()
    {
        return [
            [5],
            [
                tap(new EloquentModelCastingTest, function (EloquentModelCastingTest $model) {
                    $model->id = 5;
                }),
            ],
            [1, 5],
        ];
    }
}
