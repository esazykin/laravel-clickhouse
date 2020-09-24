<?php

declare(strict_types=1);

namespace Bavix\LaravelClickHouse\Database\Eloquent;

use ArrayAccess;
use Bavix\LaravelClickHouse\Database\Connection;
use Bavix\LaravelClickHouse\Database\Query\Builder as QueryBuilder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Database\Eloquent\Concerns\HasRelationships;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Str;
use JsonSerializable;
use Tinderbox\ClickhouseBuilder\Query\Grammar;

/**
 * Class Model.
 */
abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    use Concerns\HasAttributes;
    use Concerns\Common;
    use HasEvents;
    use HasRelationships;
    use HidesAttributes;
    use GuardsAttributes;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'bavix::clickhouse';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = [];

    /**
     * The relationship counts that should be eager loaded on every query.
     *
     * @var array
     */
    protected $withCount = [];

    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    protected $perPage = 15;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected static $dispatcher;

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * Create a new Eloquent model instance.
     *
     * @param array $attributes
     *
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();

        $this->syncOriginal();

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->fireModelEvent('booting', false);

            static::boot();

            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     *
     * @return void
     */
    protected static function bootTraits()
    {
        $class = static::class;

        foreach (class_uses_recursive($class) as $trait) {
            if (method_exists($class, $method = 'boot'.class_basename($trait))) {
                forward_static_call([$class, $method]);
            }
        }
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     *
     * @return void
     */
    public static function clearBootedModels()
    {
        static::$booted = [];
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $attributes
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     *
     * @return $this
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $key = $this->removeTableFromKey($key);

            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException($key);
            }
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function forceFill(array $attributes)
    {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }

    /**
     * Remove the table name from a given key.
     *
     * @param string $key
     *
     * @return string
     */
    protected function removeTableFromKey($key)
    {
        return Str::contains($key, '.') ? last(explode('.', $key)) : $key;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param array $attributes
     * @param bool  $exists
     *
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static((array) $attributes);

        $model->exists = $exists;

        $model->setConnection(
            $this->getConnectionName()
        );

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param array       $attributes
     * @param string|null $connection
     *
     * @return static
     */
    public function newFromBuilder(array $attributes = [], string $connection = null)
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes($attributes, true);

        $model->setConnection($connection ?: $this->getConnectionName());

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Get all of the models from the database.
     *
     * @return Collection|static[]
     */
    public static function all()
    {
        return (new static())->newQuery()->get();
    }

    /**
     * Begin querying a model with eager loading.
     *
     * @param array|string $relations
     *
     * @return Builder|static
     */
    public static function with($relations)
    {
        return (new static())->newQuery()->with(
            is_string($relations) ? func_get_args() : $relations
        );
    }

    public static function query(): Builder
    {
        return (new static())->newQuery();
    }

    public function newQuery(): Builder
    {
        return $this->newQueryWithoutScopes();
    }

    public function newQueryWithoutScopes(): Builder
    {
        $builder = $this->newEloquentBuilder($this->newBaseQueryBuilder());

        // Once we have the query builders, we will set the model instances so the
        // builder can easily access any information it may need from the model
        // while it is constructing and executing various queries against it.
        return $builder->setModel($this)
            ->with($this->with);
    }

    public function newQueryWithoutScope()
    {
        return $this->newQuery();
    }

    public function newEloquentBuilder(QueryBuilder $query): Builder
    {
        return new Builder($query);
    }

    protected function newBaseQueryBuilder(): QueryBuilder
    {
        /** @var Connection $connection */
        $connection = $this->getConnection();

        return new QueryBuilder($connection, new Grammar());
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param array $models
     *
     * @return Collection
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param int $options
     *
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     *
     * @return string
     */
    public function toJson($options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the database connection for the model.
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection(): \Illuminate\Database\Connection
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setConnection(string $name)
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Resolve a connection instance.
     *
     * @param string|null $connection
     *
     * @return Connection|\Illuminate\Database\ConnectionInterface
     */
    public static function resolveConnection(string $connection = null)
    {
        return static::getConnectionResolver()->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     *
     * @return ConnectionResolverInterface
     */
    public static function getConnectionResolver(): ConnectionResolverInterface
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     *
     * @param \Illuminate\Database\ConnectionResolverInterface $resolver
     *
     * @return void
     */
    public static function setConnectionResolver(Resolver $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        if (!isset($this->table)) {
            $this->setTable(str_replace(
                '\\',
                '',
                Str::snake(Str::plural(class_basename($this)))
            ));
        }

        return $this->table;
    }

    /**
     * Set the table associated with the model.
     *
     * @param string $table
     *
     * @return $this
     */
    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     *
     * @param string $key
     *
     * @return $this
     */
    public function setKeyName(string $key): self
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName(): string
    {
        return $this->getTable().'.'.$this->getKeyName();
    }

    /**
     * Get the pk key type.
     *
     * @return string
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * Set the data type for the primary key.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setKeyType(string $type)
    {
        $this->keyType = $type;

        return $this;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)).'_'.$this->primaryKey;
    }

    /**
     * Get the number of models to return per page.
     *
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Set the number of models to return per page.
     *
     * @param int $perPage
     *
     * @return $this
     */
    public function setPerPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->getAttribute($offset) !== null;
    }

    /**
     * Get the value for a given offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param mixed $offset
     *
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static())->$method(...$parameters);
    }
}
