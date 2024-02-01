<?php

declare(strict_types=1);

namespace Bavix\LaravelClickHouse\Database\Query;

use Bavix\LaravelClickHouse\Database\Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Tinderbox\Clickhouse\Common\Format;
use Tinderbox\ClickhouseBuilder\Query\BaseBuilder;
use Tinderbox\ClickhouseBuilder\Query\Grammar;

class Builder extends BaseBuilder
{
    use  Macroable {
        __call as macroCall;
    }

    protected $connection;

    public function __construct(
        Connection $connection,
        Grammar $grammar
    ) {
        $this->connection = $connection;
        $this->grammar = $grammar;
    }

    /**
     * Perform compiled from builder sql query and getting result.
     *
     * @throws \Tinderbox\Clickhouse\Exceptions\ClientException
     *
     * @return Collection
     */
    public function get(): Collection
    {
        if (!empty($this->async)) {
            $result = $this->connection->selectAsync($this->toAsyncSqls());
        } else {
            $result = $this->connection->select($this->toSql(), [], $this->getFiles());
        }

        return collect($result);
    }

    /**
     * Performs compiled sql for count rows only. May be used for pagination
     * Works only without async queries.
     *
     * @param string $column Column to pass into count() aggregate function
     *
     * @throws \Tinderbox\Clickhouse\Exceptions\ClientException
     *
     * @return int
     */
    public function count($column = '*'): int
    {
        $builder = $this->getCountQuery($column);
        $result = $builder->get();

        if (count($this->groups) > 0) {
            return count($result);
        }

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Perform query and get first row.
     *
     * @throws \Tinderbox\Clickhouse\Exceptions\ClientException
     *
     * @return mixed|null
     */
    public function first()
    {
        return $this->get()->first();
    }

    /**
     * Makes clean instance of builder.
     *
     * @return self
     */
    public function newQuery(): self
    {
        return new static($this->connection, $this->grammar);
    }

    /**
     * Insert in table data from files.
     *
     * @param array  $columns
     * @param array  $files
     * @param string $format
     * @param int    $concurrency
     *
     * @throws \Tinderbox\Clickhouse\Exceptions\ClientException
     *
     * @return array
     */
    public function insertFiles(array $columns, array $files, string $format = Format::CSV, int $concurrency = 5): array
    {
        return $this->connection->insertFiles(
            (string) $this->getFrom()->getTable(),
            $columns,
            $files,
            $format,
            $concurrency
        );
    }

    /**
     * Performs insert query.
     *
     * @param array $values
     *
     * @return bool
     */
    public function insert(array $values): bool
    {
        if (empty($values)) {
            return false;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        foreach ($values as $key => &$value) {
            ksort($value);
        }

        return $this->connection->insert(
            $this->grammar->compileInsert($this, $values),
            Arr::flatten($values)
        );
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
