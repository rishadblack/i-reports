<?php
namespace Rishadblack\IReports;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class Filter
{
    protected string $field;
    protected Closure $callback;

    public function __construct(string $field, Closure $callback = null)
    {
        $this->field = $field;
        $this->callback = $callback ?? function () {};
    }

    public static function make(string $field): self
    {
        return new self($field);
    }

    public function filter(Closure $callback): self
    {
        $this->callback = $callback;
        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function apply(Builder $builder, $value): void
    {
        ($this->callback)($builder, $value);
    }
}
