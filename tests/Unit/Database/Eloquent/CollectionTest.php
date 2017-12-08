<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Tests\Unit\Database\Eloquent;

use Esazykin\LaravelClickHouse\Database\Connection;
use Esazykin\LaravelClickHouse\Database\Eloquent\Collection;
use Esazykin\LaravelClickHouse\Tests\EloquentModelCastingTest;
use Esazykin\LaravelClickHouse\Tests\Helpers;
use Illuminate\Database\DatabaseManager;
use Mockery\Mock;
use PHPUnit\Framework\TestCase;

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


    public function testGet()
    {
        $connectionResult = collect()
            ->times(5, function (int $id) {
                return [
                    'id' => $id,
                    'floatAttribute' => $this->faker()->randomFloat(2),
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
                    $row['floatAttribute'] = (float)$row['floatAttribute'];
                    return $row;
                })
                ->toArray(),
            $models->toArray()
        );
    }
}
