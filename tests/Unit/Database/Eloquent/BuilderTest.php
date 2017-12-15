<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Tests\Unit\Database\Eloquent;

use Mockery\Mock;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\DatabaseManager;
use Tinderbox\ClickhouseBuilder\Query\Tuple;
use Esazykin\LaravelClickHouse\Tests\Helpers;
use Tinderbox\ClickhouseBuilder\Query\Grammar;
use Tinderbox\ClickhouseBuilder\Query\Identifier;
use Esazykin\LaravelClickHouse\Database\Connection;
use Tinderbox\ClickhouseBuilder\Query\Enums\Operator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Esazykin\LaravelClickHouse\Database\Eloquent\Builder;
use Esazykin\LaravelClickHouse\Database\Eloquent\Collection;
use Esazykin\LaravelClickHouse\Tests\EloquentModelCastingTest;
use Tinderbox\ClickhouseBuilder\Query\TwoElementsLogicExpression;
use Esazykin\LaravelClickHouse\Database\Query\Builder as QueryBuilder;

/**
 * @property Mock|Connection connection
 * @property Builder builder
 * @property EloquentModelCastingTest model
 */
class BuilderTest extends TestCase
{
    use Helpers;

    protected function setUp()
    {
        parent::setUp();

        $this->connection = $this->mock(Connection::class);

        $this->model = new EloquentModelCastingTest();

        $this->builder = (new Builder(new QueryBuilder($this->connection, new Grammar())))
            ->setModel($this->model);
    }

    public function testWhereKey()
    {
        $id = $this->faker()->numberBetween(1);

        $this->builder->whereKey($id);

        $wheres = $this->builder->getQuery()->getWheres();

        $this->assertCount(1, $wheres);
        /** @var TwoElementsLogicExpression $expression */
        $expression = $wheres[0];
        $this->assertInstanceOf(TwoElementsLogicExpression::class, $expression);
        /** @var Identifier $first */
        $first = $expression->getFirstElement();
        $this->assertInstanceOf(Identifier::class, $first);
        $this->assertSame($this->model->getTable().'.'.$this->model->getKeyName(), (string) $first);
        $this->assertSame($id, $expression->getSecondElement());
        $operator = $expression->getOperator();
        $this->assertInstanceOf(Operator::class, $operator);
        $this->assertSame('=', $operator->getValue());
    }

    public function testWhereKeyNot()
    {
        $ids = range(1, 5);

        $this->builder->whereKeyNot($ids);

        $wheres = $this->builder->getQuery()->getWheres();

        $this->assertCount(1, $wheres);
        /** @var TwoElementsLogicExpression $expression */
        $expression = $wheres[0];
        $this->assertInstanceOf(TwoElementsLogicExpression::class, $expression);
        /** @var Identifier $first */
        $first = $expression->getFirstElement();
        $this->assertInstanceOf(Identifier::class, $first);
        $this->assertSame($this->model->getTable().'.'.$this->model->getKeyName(), (string) $first);
        /** @var Tuple $second */
        $second = $expression->getSecondElement();
        $this->assertSame($ids, $second->getElements());
        $operator = $expression->getOperator();
        $this->assertInstanceOf(Operator::class, $operator);
        $this->assertSame('NOT IN', $operator->getValue());
    }

    public function testWhereSimple()
    {
        $date = $this->faker()->date();
        $this->builder->where('date_column', '>', $date);

        $wheres = $this->builder->getQuery()->getWheres();

        $this->assertCount(1, $wheres);
        /** @var TwoElementsLogicExpression $expression */
        $expression = $wheres[0];
        $this->assertInstanceOf(TwoElementsLogicExpression::class, $expression);
        /** @var Identifier $first */
        $first = $expression->getFirstElement();
        $this->assertInstanceOf(Identifier::class, $first);
        $this->assertSame('date_column', (string) $first);
        $this->assertSame($date, $expression->getSecondElement());
        $operator = $expression->getOperator();
        $this->assertInstanceOf(Operator::class, $operator);
        $this->assertSame('>', $operator->getValue());
    }

    public function testWhereClosure()
    {
        /** @var Mock|DatabaseManager $resolver */
        $resolver = $this->mock(DatabaseManager::class);
        $resolver->shouldReceive('connection')
            ->andReturn($this->connection);
        EloquentModelCastingTest::setConnectionResolver($resolver);

        $this->builder
            ->where(function (Builder $query) {
                $query->where('id', '<', 10)
                    ->where('id', '=', 15, 'OR');
            })
            ->where('status', 100);

        $sql = $this->builder->toSql();

        $this->assertSame('SELECT * FROM `test_table` WHERE (`id` < 10 OR `id` = 15) AND `status` = 100', $sql);
    }

    public function testOrWhere()
    {
        $id = $this->faker()->numberBetween(1);
        $date = $this->faker()->date();
        $this->builder->where('id', $id);
        $this->builder->orWhere('date_column', '>', $date);

        $sql = $this->builder->toSql();
        $this->assertSame(
            'SELECT * FROM `test_table` WHERE `id` = '.$id.' OR `date_column` > \''.$date.'\'',
            $sql
        );
    }

    public function testFind()
    {
        $id = $this->faker()->numberBetween(1);
        $stringAttribute = $this->faker()->word;

        $this->connection
            ->shouldReceive('getName')
            ->andReturn($this->model->getConnectionName());

        $this->connection
            ->shouldReceive('select')
            ->andReturn([
                ['id' => $id, 'stringAttribute' => $stringAttribute],
            ]);

        $model = $this->builder->find($id);

        $this->assertInstanceOf(EloquentModelCastingTest::class, $model);
        $this->assertSame($id, $model->id);
        $this->assertSame($stringAttribute, $model->stringAttribute);
    }

    public function testFindMany()
    {
        $ids = collect()->times(5);

        $this->connection
            ->shouldReceive('getName')
            ->andReturn($this->model->getConnectionName());

        $this->connection
            ->shouldReceive('select')
            ->andReturn(
                $ids
                    ->map(function ($id) {
                        return ['id' => $id];
                    })
                    ->toArray()
            );

        $models = $this->builder->findMany($ids->toArray());

        $this->assertInstanceOf(Collection::class, $models);
        $this->assertCount($ids->count(), $models);
    }

    public function testFindOrFail()
    {
        $this->expectException(ModelNotFoundException::class);

        $this->connection
            ->shouldReceive('getName')
            ->andReturn($this->model->getConnectionName());

        $this->connection
            ->shouldReceive('select')
            ->andReturn([]);

        $this->builder->findOrFail($this->faker()->numberBetween());
    }

    public function testGet()
    {
        $connectionResultRow = [
            'id' => $this->faker()->randomDigit,
            'intAttribute' => (string) $this->faker()->randomDigit,
            'floatAttribute' => (string) $this->faker()->randomFloat(2),
            'stringAttribute' => $this->faker()->randomDigit,
            'boolAttribute' => 1,
            'booleanAttribute' => 1,
            'objectAttribute' => json_encode([
                $this->faker()->word => $this->faker()->randomLetter,
            ]),
            'arrayAttribute' => json_encode(range(1, 5)),
            'dateAttribute' => now()->toDateTimeString(),
            'datetimeAttribute' => now()->toDateString(),
            'timestampAttribute' => now()->toDateString(),
        ];
        $connectionResultRow['jsonAttribute'] = json_encode($connectionResultRow['arrayAttribute']);

        $this->connection
            ->shouldReceive('getName')
            ->andReturn($this->model->getConnectionName());

        $this->connection
            ->shouldReceive('select')
            ->andReturn([
                $connectionResultRow,
            ]);

        $collection = $this->builder->get();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(1, $collection);

        $retrievedModel = $collection[0];
        $this->assertSame($connectionResultRow['id'], $retrievedModel->id);
        $this->assertSame((int) $connectionResultRow['intAttribute'], $retrievedModel->intAttribute);
        $this->assertSame((float) $connectionResultRow['floatAttribute'], $retrievedModel->floatAttribute);
        $this->assertSame((string) $connectionResultRow['stringAttribute'], $retrievedModel->stringAttribute);
        $this->assertTrue($retrievedModel->boolAttribute);
        $this->assertTrue($retrievedModel->booleanAttribute);
        $this->assertEquals(json_decode($connectionResultRow['objectAttribute']), $retrievedModel->objectAttribute);
        $this->assertSame(json_decode($connectionResultRow['arrayAttribute'], true), $retrievedModel->arrayAttribute);
        $this->assertSame($connectionResultRow['arrayAttribute'], $retrievedModel->jsonAttribute);
    }
}
