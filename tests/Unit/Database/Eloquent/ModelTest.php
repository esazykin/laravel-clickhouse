<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Tests\Unit\Database\Eloquent;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Esazykin\LaravelClickHouse\Tests\Helpers;
use Esazykin\LaravelClickHouse\Tests\EloquentModelTest;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Esazykin\LaravelClickHouse\Tests\EloquentModelWithTest;
use Esazykin\LaravelClickHouse\Tests\EloquentModelCastingTest;

class ModelTest extends TestCase
{
    use Helpers;

    public function testAttributeManipulation()
    {
        $model = new EloquentModelTest();
        $model->status = 'successful';
        $this->assertEquals('successful', $model->status);
        $this->assertTrue(isset($model->status));
        unset($model->status);
        $this->assertFalse(isset($model->status));
        // test mutation
        $model->list_items = range(1, 5);
        $this->assertEquals(range(1, 5), $model->list_items);
        $attributes = $model->getAttributes();
        $this->assertEquals(json_encode(range(1, 5)), $attributes['list_items']);
    }

    public function testDirtyAttributes()
    {
        $this->expectException(MassAssignmentException::class);

        new EloquentModelTest(['foo' => '1', 'bar' => 2, 'baz' => 3]);
    }

    public function testDirtyOnCastOrDateAttributes()
    {
        $model = new EloquentModelCastingTest();
        $model->setDateFormat('Y-m-d H:i:s');
        $model->boolAttribute = 1;
        $model->foo = 1;
        $model->bar = '2017-03-18';
        $model->dateAttribute = '2017-03-18';
        $model->datetimeAttribute = '2017-03-23 22:17:00';
        $model->syncOriginal();
        $model->boolAttribute = true;
        $model->foo = true;
        $model->bar = '2017-03-18 00:00:00';
        $model->dateAttribute = '2017-03-18 00:00:00';
        $model->datetimeAttribute = null;
        $this->assertTrue($model->isDirty());
        $this->assertTrue($model->isDirty('foo'));
        $this->assertTrue($model->isDirty('bar'));
        $this->assertFalse($model->isDirty('boolAttribute'));
        $this->assertFalse($model->isDirty('dateAttribute'));
        $this->assertTrue($model->isDirty('datetimeAttribute'));
    }

    public function testCalculatedAttributes()
    {
        $model = new EloquentModelTest();
        $model->password = 'secret';
        $attributes = $model->getAttributes();
        // ensure password attribute was not set to null
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertSame('******', $model->password);
        $hash = 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4';
        $this->assertEquals($hash, $attributes['password_hash']);
        $this->assertEquals($hash, $model->password_hash);
    }

    public function testWithMethodCallsQueryBuilderCorrectly()
    {
        $result = EloquentModelWithTest::with('foo', 'bar');
        $this->assertEquals('foo', $result);
    }

    public function testTimestampsAreReturnedAsObjectsFromPlainDatesAndTimestamps()
    {
        $datetime = '2012-12-04';
        $model = new EloquentModelCastingTest();
        $model->payed_at = $datetime;

        $this->assertInstanceOf(Carbon::class, $model->payed_at);
        $this->assertSame($datetime.' 00:00:00', $model->payed_at->toDateTimeString());
    }
}
