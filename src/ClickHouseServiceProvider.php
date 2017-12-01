<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse;

use Esazykin\LaravelClickHouse\Database\Connection;
use Esazykin\LaravelClickHouse\Database\Eloquent\Model;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

class ClickHouseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var DatabaseManager $db */
        $db = $this->app->get('db');

        $db->extend('clickhouse', function ($config, $name) {
            $config['name'] = $name;

            return new Connection($config);
        });

        Model::setConnectionResolver($db);
    }
}
