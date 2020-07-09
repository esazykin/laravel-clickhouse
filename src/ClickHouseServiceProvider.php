<?php

declare(strict_types=1);

namespace Bavix\LaravelClickHouse;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Bavix\LaravelClickHouse\Database\Connection;
use Bavix\LaravelClickHouse\Database\Eloquent\Model;

class ClickHouseServiceProvider extends ServiceProvider
{
    /**
     * @return void
     * @throws
     */
    public function boot(): void
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->resolving('db', static function (DatabaseManager $db) {
            $db->extend('bavix::clickhouse', static function ($config, $name) {
                return new Connection(\array_merge($config, [
                    'name' => $name,
                ]));
            });
        });
    }
}
