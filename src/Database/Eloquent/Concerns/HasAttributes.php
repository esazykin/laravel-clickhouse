<?php

declare(strict_types=1);

namespace Esazykin\LaravelClickHouse\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasAttributes as BaseHasAttributes;

trait HasAttributes
{
    use BaseHasAttributes;

    public function getDates()
    {
        return $this->dates;
    }
}
