<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Tests;

class EloquentModelCastingTest extends EloquentModelTest
{
    protected $casts = [
        'intAttribute' => 'int',
        'floatAttribute' => 'float',
        'stringAttribute' => 'string',
        'boolAttribute' => 'bool',
        'booleanAttribute' => 'boolean',
        'objectAttribute' => 'object',
        'arrayAttribute' => 'array',
        'jsonAttribute' => 'json',
        'dateAttribute' => 'date',
        'datetimeAttribute' => 'datetime',
        'timestampAttribute' => 'timestamp',
    ];

    public function jsonAttributeValue()
    {
        return $this->attributes['jsonAttribute'];
    }

}
