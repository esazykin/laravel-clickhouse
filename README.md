# laravel-clickhouse
[![Build Status](https://travis-ci.org/bavix/laravel-clickhouse.svg?branch=master)](https://travis-ci.org/bavix/laravel-clickhouse)
[![StyleCI](https://styleci.io/repos/112756298/shield?branch=master)](https://styleci.io/repos/112756298)
[![Coverage Status](https://coveralls.io/repos/github/bavix/laravel-clickhouse/badge.svg)](https://coveralls.io/github/bavix/laravel-clickhouse)

Eloquent model for ClickHouse

## Prerequisites
- php 7.1
- clickhouse server

## Installation
```sh
$ composer require bavix/laravel-clickhouse
```

Then add the code above into your config/app.php file providers section
```php
Bavix\LaravelClickHouse\ClickHouseServiceProvider::class,
```
And add new connection into your config/database.php file. Something like this:
```php
'connections' => [
    'bavix::clickhouse' => [
        'driver' => 'bavix::clickhouse',
        'host' => '',
        'port' => '',
        'database' => '',
        'username' => '',
        'password' => '',
        'options' => [
            'timeout' => 10,
            'protocol' => 'https'
        ]
    ]
]
```
Or like this, if clickhouse runs in cluster
```php
'connections' => [
    'bavix::clickhouse' => [
        'driver' => 'bavix::clickhouse',
        'cluster' => [
            'server-1' => [
                'host' => '',
                'port' => '',
                'database' => '',
                'username' => '',
                'password' => '',
                'options' => [
                    'timeout' => 10,
                    'protocol' => 'https'
                ]
            ],
            'server-2' => [
                'host' => '',
                'port' => '',
                'database' => '',
                'username' => '',
                'password' => '',
                'options' => [
                    'timeout' => 10,
                    'protocol' => 'https'
                ]
            ]
        ]
    ]
]
```

Then create model
```php
<?php

use Bavix\LaravelClickHouse\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
}
```

And use it
```php
Payment::select(raw('count() AS cnt'), 'payment_system')
    ->whereBetween('payed_at', [
        Carbon\Carbon::parse('2017-01-01'),
        now(),
    ])
    ->groupBy('payment_system')
    ->get();

```

## Roadmap
- more tests
- Model::with() method
- relations

