<?php

namespace Rishadblack\IReports\Views;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Describes one report filter: how it is rendered in the viewer and how it changes the query.
 *
 * A filter applies through its ->filter() callback, or automatically on ->column() when no
 * callback is given. Values are sanitised by type before they reach the query.
 */
class Filter
{
    public const TYPES = ['text', 'select', 'multi_select', 'date', 'date_range', 'number', 'number_range', 'boolean', 'component', 'blade_component'];

    protected string $name;

    protected string $title;

    protected ?string $placeholder = null;

    protected ?string $customClass = null;

    protected string $filterType = 'text';

    /** @var array<int|string, mixed> */
    protected array $options = [];

    /** @var Closure|null */
    protected $filterCallback;

    protected ?string $column = null;

    protected ?string $component = null;

    /** @var array<string, mixed> */
    protected array $componentParameters = [];

    protected ?string $dependsOn = null;

    protected mixed $default = null;

    protected string $responseTime = '500';

    /** @var Closure|null */
    protected $displayCallback;

    /**
     * Final so that make() can safely instantiate subclasses with new static.
     */
    final public function __construct() {}

    public static function make(string $title, string $name): static
    {
        $instance = new static;
        $instance->title = trim($title);
        $instance->name = trim($name);

        return $instance;
    }

    public function key(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): string
    {
        return $this->filterType;
    }

    /*
    |--------------------------------------------------------------------------
    | Query behaviour
    |--------------------------------------------------------------------------
    */

    /**
     * Callback that receives (Builder $query, mixed $value) and constrains the query.
     */
    public function filter(callable $callback): static
    {
        $this->filterCallback = Closure::fromCallable($callback);

        return $this;
    }

    /**
     * Qualified column used by the default filtering when no callback is set, e.g. "users.status".
     */
    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): ?string
    {
        return $this->column;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Turn the (sanitised) value into readable text for filter chips and export headers,
     * e.g. fn ($countryId) => Country::find($countryId)?->name. Needed for component filters,
     * whose values are usually ids.
     */
    public function displayUsing(callable $callback): static
    {
        $this->displayCallback = Closure::fromCallable($callback);

        return $this;
    }

    /**
     * Readable description of a raw request value, or null when the filter is not active.
     */
    public function describe(mixed $value): ?string
    {
        $value = $this->sanitize($value);

        if ($value === null) {
            return null;
        }

        if ($this->displayCallback instanceof Closure) {
            $described = ($this->displayCallback)($value, $this);

            return $described === null || $described === '' ? null : (string) $described;
        }

        $label = fn (mixed $item): string => (string) ($this->options[$item] ?? $item);

        return match ($this->filterType) {
            'multi_select' => implode(', ', array_map($label, (array) $value)),
            'date_range', 'number_range' => $this->describeRange((array) $value),
            'select', 'boolean' => is_array($value) ? implode(', ', array_map($label, $value)) : $label($value),
            default => is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $range
     */
    protected function describeRange(array $range): string
    {
        $from = $range['from'] ?? null;
        $to = $range['to'] ?? null;

        return match (true) {
            $from !== null && $to !== null => $from.' – '.$to,
            $from !== null => 'from '.$from,
            default => 'up to '.$to,
        };
    }

    /**
     * Re-mount the filter component with the parent's current value whenever the parent changes.
     */
    public function dependsOn(string $parentKey): static
    {
        $this->dependsOn = $parentKey;

        return $this;
    }

    public function getDependsOn(): ?string
    {
        return $this->dependsOn;
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int|string, mixed>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function responseTime(string $responseTime): static
    {
        $this->responseTime = $responseTime;

        return $this;
    }

    public function customClass(string $customClass): static
    {
        $this->customClass = $customClass;

        return $this;
    }

    public function text(): static
    {
        $this->filterType = 'text';

        return $this;
    }

    /**
     * @param  array<int|string, mixed>  $options
     */
    public function select(array $options = []): static
    {
        if (count($options) > 0) {
            $this->options = $options;
        }

        $this->filterType = 'select';

        return $this;
    }

    /**
     * @param  array<int|string, mixed>  $options
     */
    public function multiSelect(array $options = []): static
    {
        if (count($options) > 0) {
            $this->options = $options;
        }

        $this->filterType = 'multi_select';

        return $this;
    }

    public function date(): static
    {
        $this->filterType = 'date';

        return $this;
    }

    public function dateRange(): static
    {
        $this->filterType = 'date_range';

        return $this;
    }

    public function number(): static
    {
        $this->filterType = 'number';

        return $this;
    }

    public function numberRange(): static
    {
        $this->filterType = 'number_range';

        return $this;
    }

    public function boolean(string $trueLabel = 'Yes', string $falseLabel = 'No'): static
    {
        $this->filterType = 'boolean';
        $this->options = ['1' => $trueLabel, '0' => $falseLabel];

        return $this;
    }

    /**
     * Render a Livewire component (for example a wire-tomselect dropdown) bound to the filter value.
     *
     * @param  array<string, mixed>  $componentParameters
     */
    public function component(string $component, array $componentParameters = []): static
    {
        $this->filterType = 'component';
        $this->component = $component;
        $this->componentParameters = $componentParameters;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $componentParameters
     */
    public function bladeComponent(string $component, array $componentParameters = []): static
    {
        $this->filterType = 'blade_component';
        $this->component = $component;
        $this->componentParameters = $componentParameters;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Applying
    |--------------------------------------------------------------------------
    */

    /**
     * True when the raw request value carries something to filter on.
     */
    public function hasValue(mixed $value): bool
    {
        return $this->sanitize($value) !== null;
    }

    /**
     * Normalise a raw request value for this filter's type. Returns null when there is nothing usable.
     */
    public function sanitize(mixed $value): mixed
    {
        return match ($this->filterType) {
            'multi_select' => $this->sanitizeList($value),
            'date_range' => $this->sanitizeRange($value, fn ($v) => $this->validDate($v)),
            'number_range' => $this->sanitizeRange($value, fn ($v) => is_numeric($v) ? $v + 0 : null),
            'date' => $this->validDate($this->scalar($value)),
            'number' => is_numeric($this->scalar($value)) ? $this->scalar($value) + 0 : null,
            'boolean' => $this->sanitizeBoolean($value),
            'select', 'text' => is_scalar($value) || is_array($value) ? $this->scalar($value) : null,
            default => $this->sanitizeScalarOrList($value),
        };
    }

    /**
     * Constrain the query with the sanitised value. Values that fail sanitisation are ignored.
     */
    public function apply(Builder $query, mixed $value): void
    {
        $value = $this->sanitize($value);

        if ($value === null) {
            return;
        }

        if ($this->filterCallback instanceof Closure) {
            ($this->filterCallback)($query, $value, $this);

            return;
        }

        if ($this->column === null) {
            return;
        }

        $this->applyDefault($query, $value);
    }

    protected function applyDefault(Builder $query, mixed $value): void
    {
        $column = $this->column;

        switch ($this->filterType) {
            case 'text':
                $query->where($column, 'like', '%'.$value.'%');
                break;

            case 'multi_select':
                $query->whereIn($column, $value);
                break;

            case 'date_range':
                if (isset($value['from'])) {
                    $query->whereDate($column, '>=', $value['from']);
                }
                if (isset($value['to'])) {
                    $query->whereDate($column, '<=', $value['to']);
                }
                break;

            case 'number_range':
                if (isset($value['from'])) {
                    $query->where($column, '>=', $value['from']);
                }
                if (isset($value['to'])) {
                    $query->where($column, '<=', $value['to']);
                }
                break;

            case 'date':
                $query->whereDate($column, $value);
                break;

            default:
                is_array($value) ? $query->whereIn($column, $value) : $query->where($column, $value);
        }
    }

    protected function scalar(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' || $value === false ? null : $value;
    }

    protected function sanitizeScalarOrList(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeList($value);
        }

        return is_scalar($value) ? $this->scalar($value) : null;
    }

    /**
     * @return array<int, int|float|string>|null
     */
    protected function sanitizeList(mixed $value): ?array
    {
        $value = is_array($value) ? $value : [$value];
        $list = array_values(array_filter(array_map(fn ($item) => is_scalar($item) ? trim((string) $item) : null, $value), fn ($item) => $item !== null && $item !== ''));

        return count($list) > 0 ? $list : null;
    }

    /**
     * @return array{from?: mixed, to?: mixed}|null
     */
    protected function sanitizeRange(mixed $value, Closure $normalise): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $range = [];

        foreach (['from', 'to'] as $edge) {
            $edgeValue = $this->scalar($value[$edge] ?? null);

            if ($edgeValue === null) {
                continue;
            }

            $normalised = $normalise($edgeValue);

            if ($normalised !== null) {
                $range[$edge] = $normalised;
            }
        }

        return count($range) > 0 ? $range : null;
    }

    protected function sanitizeBoolean(mixed $value): ?string
    {
        $value = $this->scalar($value);

        if ($value === null) {
            return null;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $bool === null ? null : ($bool ? '1' : '0');
    }

    protected function validDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || ! preg_match('/\d/', $value) || strtotime($value) === false) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Data the viewer needs to render the filter. Callbacks are excluded.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! in_array($this->filterType, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown filter type [{$this->filterType}]");
        }

        return [
            'name' => $this->name,
            'title' => $this->title,
            'placeholder' => $this->placeholder,
            'class' => $this->customClass,
            'filter_type' => $this->filterType,
            'component' => $this->component,
            'component_parameters' => $this->componentParameters,
            'response_time' => $this->responseTime,
            'options' => $this->options,
            'depends_on' => $this->dependsOn,
            'default' => $this->default,
        ];
    }
}
