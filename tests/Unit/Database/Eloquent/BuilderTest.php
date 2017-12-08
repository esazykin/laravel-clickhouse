<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Tests\Unit\Database\Eloquent;

use Esazykin\LaravelClickHouse\Database\Connection;
use Esazykin\LaravelClickHouse\Database\Eloquent\Builder;
use Esazykin\LaravelClickHouse\Database\Eloquent\Collection;
use Esazykin\LaravelClickHouse\Database\Query\Builder as QueryBuilder;
use Esazykin\LaravelClickHouse\Tests\EloquentModelCastingTest;
use Esazykin\LaravelClickHouse\Tests\Helpers;
use Mockery\Mock;
use PHPUnit\Framework\TestCase;
use Tinderbox\ClickhouseBuilder\Query\Enums\Operator;
use Tinderbox\ClickhouseBuilder\Query\Grammar;
use Tinderbox\ClickhouseBuilder\Query\Identifier;
use Tinderbox\ClickhouseBuilder\Query\TwoElementsLogicExpression;

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
        $this->assertSame($this->model->getTable() . '.' . $this->model->getKeyName(), (string)$first);
        $this->assertSame($id, $expression->getSecondElement());
        $operator = $expression->getOperator();
        $this->assertInstanceOf(Operator::class, $operator);
        $this->assertSame('=', $operator->getValue());
    }

    public function testGet()
    {
        $connectionResultRow = [
            'id' => $this->faker()->randomDigit,
            'intAttribute' => (string)$this->faker()->randomDigit,
            'floatAttribute' => (string)$this->faker()->randomFloat(2),
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
        $this->assertSame((int)$connectionResultRow['intAttribute'], $retrievedModel->intAttribute);
        $this->assertSame((float)$connectionResultRow['floatAttribute'], $retrievedModel->floatAttribute);
        $this->assertSame((string)$connectionResultRow['stringAttribute'], $retrievedModel->stringAttribute);
        $this->assertTrue($retrievedModel->boolAttribute);
        $this->assertTrue($retrievedModel->booleanAttribute);
        $this->assertEquals(json_decode($connectionResultRow['objectAttribute']), $retrievedModel->objectAttribute);
        $this->assertSame(json_decode($connectionResultRow['arrayAttribute'], true), $retrievedModel->arrayAttribute);
        $this->assertSame($connectionResultRow['arrayAttribute'], $retrievedModel->jsonAttribute);
    }
}
