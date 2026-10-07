<?php

namespace Rishadblack\IReports\Views;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Describes one report column: where its value comes from, how it is rendered and exported.
 *
 * Output is escaped by default. Call html() when format() returns markup that must be printed raw.
 *
 * @implements Arrayable<string, mixed>
 */
class Column implements Arrayable
{
    public const TYPES = ['text', 'number', 'money', 'date', 'datetime', 'boolean', 'badge', 'link', 'image'];

    public const AGGREGATES = ['sum', 'avg', 'count', 'min', 'max'];

    protected string $title;

    protected string $name;

    protected string $field;

    protected ?string $table = null;

    protected bool $custom = false;

    /** @var string|Closure|null */
    protected $style;

    protected bool $searchable = false;

    protected bool $sortable = false;

    protected bool $isHidden = false;

    protected bool $hideable = true;

    protected bool $hiddenByDefault = false;

    /** @var array<int, string> */
    protected array $hideIn = [];

    /** @var Closure|null */
    protected $format;

    /** @var Closure|null */
    protected $exportFormat;

    protected bool $html = false;

    protected string $type = 'text';

    /** @var array<string, mixed> */
    protected array $typeOptions = [];

    protected ?string $aggregate = null;

    protected ?string $align = null;

    protected ?string $width = null;

    /** @var array<int, string> */
    protected array $relations = [];

    final public function __construct(string $title, string $name)
    {
        $this->title = trim($title);
        $this->name = trim($name);

        if ($this->name === '') {
            $this->name = Str::snake($this->title);
        }

        if (Str::contains($this->name, '.')) {
            $this->field = Str::afterLast($this->name, '.');
            $this->relations = explode('.', Str::beforeLast($this->name, '.'));
        } else {
            $this->field = $this->name;
        }
    }

    public static function make(string $title, string $name = ''): static
    {
        return new static($title, $name);
    }

    /*
    |--------------------------------------------------------------------------
    | Behaviour flags
    |--------------------------------------------------------------------------
    */

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function hide(): static
    {
        $this->isHidden = true;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    /**
     * Whether the user may show or hide the column from the viewer's column picker (default true).
     */
    public function hideable(bool $hideable = true): static
    {
        $this->hideable = $hideable;

        return $this;
    }

    public function isHideable(): bool
    {
        return $this->hideable && ! $this->isHidden;
    }

    /**
     * Start hidden in the viewer; the user can switch it on from the column picker.
     */
    public function hiddenByDefault(bool $hidden = true): static
    {
        $this->hiddenByDefault = $hidden;

        return $this;
    }

    public function isHiddenByDefault(): bool
    {
        return $this->hiddenByDefault && $this->isHideable();
    }

    /**
     * Hide the column in some outputs only: 'pdf|xlsx|csv|print|view|inline'.
     *
     * @param  string|array<int, string>  $hideIn
     */
    public function hideIn(string|array $hideIn): static
    {
        $this->hideIn = array_values(array_filter(array_map('trim', is_array($hideIn) ? $hideIn : explode('|', $hideIn))));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getHideIn(): array
    {
        return $this->hideIn;
    }

    public function isHiddenIn(?string $export): bool
    {
        if ($this->isHidden) {
            return true;
        }

        return $export !== null && in_array($export, $this->hideIn, true);
    }

    /**
     * A custom column is rendered by the view (or format()) and is not selected from the database.
     */
    public function custom(): static
    {
        $this->custom = true;

        return $this;
    }

    public function isCustom(): bool
    {
        return $this->custom;
    }

    /**
     * Allow format() output to be printed without escaping.
     */
    public function html(bool $html = true): static
    {
        $this->html = $html;

        return $this;
    }

    public function isHtml(): bool
    {
        return $this->html;
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function style(string|callable $style): static
    {
        $this->style = is_string($style) ? $style : Closure::fromCallable($style);

        return $this;
    }

    public function getStyle(): string|callable|null
    {
        return $this->style;
    }

    public function applyStyle(mixed $row = null): ?string
    {
        $style = $this->style instanceof Closure ? ($this->style)($row) : $this->style;
        $extra = [];

        if ($this->align) {
            $extra[] = "text-align: {$this->align};";
        }

        if ($this->width) {
            $extra[] = "width: {$this->width};";
        }

        $extra = implode(' ', $extra);

        return trim($extra.' '.($style ?? '')) ?: null;
    }

    public function align(string $align): static
    {
        if (! in_array($align, ['left', 'center', 'right'], true)) {
            throw new InvalidArgumentException('Align must be left, center or right');
        }

        $this->align = $align;

        return $this;
    }

    public function getAlign(): ?string
    {
        return $this->align;
    }

    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    /**
     * Transform the value for display. The result is escaped unless html() was called.
     */
    public function format(callable $format): static
    {
        $this->format = Closure::fromCallable($format);

        return $this;
    }

    public function getFormat(): ?callable
    {
        return $this->format;
    }

    /**
     * Transform the value for CSV and Excel. Falls back to the type formatting, then to format().
     */
    public function exportFormat(callable $format): static
    {
        $this->exportFormat = Closure::fromCallable($format);

        return $this;
    }

    public function getExportFormat(): ?callable
    {
        return $this->exportFormat;
    }

    /**
     * Apply the user format callback only (kept for backwards compatibility).
     */
    public function applyFormat(mixed $value, mixed $row, ?Column $column = null): mixed
    {
        if ($this->format instanceof Closure) {
            return ($this->format)($value, $row, $column ?? $this);
        }

        return $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Types
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $options
     */
    public function type(string $type, array $options = []): static
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown column type [{$type}]");
        }

        $this->type = $type;
        $this->typeOptions = $options;

        if (in_array($type, ['number', 'money'], true) && $this->align === null) {
            $this->align = 'right';
        }

        if (in_array($type, ['badge', 'link', 'image'], true)) {
            $this->html = true;
        }

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeOptions(): array
    {
        return $this->typeOptions;
    }

    public function date(string $format = 'Y-m-d'): static
    {
        return $this->type('date', ['format' => $format]);
    }

    public function datetime(string $format = 'Y-m-d H:i'): static
    {
        return $this->type('datetime', ['format' => $format]);
    }

    public function number(int $decimals = 2, string $decimalPoint = '.', string $thousandsSeparator = ','): static
    {
        return $this->type('number', compact('decimals', 'decimalPoint', 'thousandsSeparator'));
    }

    public function money(string $currency = '', int $decimals = 2, string $position = 'before', string $decimalPoint = '.', string $thousandsSeparator = ','): static
    {
        return $this->type('money', compact('currency', 'decimals', 'position', 'decimalPoint', 'thousandsSeparator'));
    }

    public function boolean(string $true = 'Yes', string $false = 'No'): static
    {
        return $this->type('boolean', compact('true', 'false'));
    }

    /**
     * Render the value as a coloured badge. $map is value => css class (or value => [label, class]).
     *
     * @param  array<int|string, string|array{0: string, 1?: string}>  $map
     */
    public function badge(array $map = [], string $defaultClass = 'badge bg-secondary'): static
    {
        return $this->type('badge', ['map' => $map, 'default_class' => $defaultClass]);
    }

    /**
     * Render the value as a link. $url receives ($value, $row) and returns the href.
     */
    public function link(callable $url, string $target = '_blank'): static
    {
        return $this->type('link', ['url' => Closure::fromCallable($url), 'target' => $target]);
    }

    public function image(int|string $width = 40, ?callable $src = null): static
    {
        return $this->type('image', ['width' => $width, 'src' => $src ? Closure::fromCallable($src) : null]);
    }

    /*
    |--------------------------------------------------------------------------
    | Aggregates
    |--------------------------------------------------------------------------
    */

    public function aggregate(string $aggregate): static
    {
        if (! in_array($aggregate, self::AGGREGATES, true)) {
            throw new InvalidArgumentException("Unknown aggregate [{$aggregate}]");
        }

        $this->aggregate = $aggregate;

        return $this;
    }

    public function sum(): static
    {
        return $this->aggregate('sum');
    }

    public function avg(): static
    {
        return $this->aggregate('avg');
    }

    public function count(): static
    {
        return $this->aggregate('count');
    }

    public function min(): static
    {
        return $this->aggregate('min');
    }

    public function max(): static
    {
        return $this->aggregate('max');
    }

    public function getAggregate(): ?string
    {
        return $this->aggregate;
    }

    public function hasAggregate(): bool
    {
        return $this->aggregate !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Identity and query mapping
    |--------------------------------------------------------------------------
    */

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function isBaseColumn(): bool
    {
        return ! $this->hasRelations();
    }

    public function hasRelations(): bool
    {
        return count($this->relations) > 0;
    }

    /**
     * @return Collection<int, string>
     */
    public function getRelations(): Collection
    {
        return collect($this->relations);
    }

    public function getRelationString(): ?string
    {
        return $this->hasRelations() ? implode('.', $this->relations) : null;
    }

    public function setTable(?string $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Qualified column for SQL: "table.field" or "relation_alias.field".
     */
    public function getColumn(): string
    {
        return ($this->table ? $this->table.'.' : '').$this->field;
    }

    /**
     * Alias used in the SELECT list and on the hydrated row.
     */
    public function getColumnSelectName(): string
    {
        return $this->isBaseColumn() ? $this->field : $this->getRelationString().'.'.$this->field;
    }

    public function getValue(mixed $row): mixed
    {
        $key = $this->getColumnSelectName();

        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        if (is_object($row)) {
            return $row->{$key} ?? null;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    /**
     * Value ready to print in HTML. Already escaped unless the column allows HTML.
     */
    public function render(mixed $row, ?string $export = null): string
    {
        $value = $this->getValue($row);
        $formatted = $this->formatByType($value, $row, false);

        if ($this->format instanceof Closure) {
            $formatted = ($this->format)($formatted, $row, $this);
        }

        if ($formatted === null) {
            return '';
        }

        if ($formatted instanceof Htmlable) {
            return $formatted->toHtml();
        }

        $formatted = $this->stringify($formatted);

        return $this->html ? $formatted : e($formatted);
    }

    /**
     * Plain value for CSV and Excel cells.
     */
    public function exportValue(mixed $row): mixed
    {
        $value = $this->getValue($row);

        if ($this->exportFormat instanceof Closure) {
            return ($this->exportFormat)($value, $row, $this);
        }

        $formatted = $this->formatByType($value, $row, true);

        if ($this->format instanceof Closure && ! in_array($this->type, ['number', 'money', 'date', 'datetime', 'boolean'], true)) {
            $formatted = ($this->format)($formatted, $row, $this);

            if ($formatted instanceof Htmlable) {
                $formatted = $formatted->toHtml();
            }

            if (is_string($formatted) && $this->html) {
                $formatted = html_entity_decode(strip_tags($formatted), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return is_object($formatted) || is_array($formatted) ? $this->stringify($formatted) : $formatted;
    }

    /**
     * Format an aggregate result (sum, avg, ...) using the column type.
     */
    public function formatAggregate(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($this->aggregate === 'count') {
            return (string) $value;
        }

        if (in_array($this->type, ['number', 'money'], true)) {
            return (string) $this->formatByType($value, null, false);
        }

        return $this->stringify($value);
    }

    protected function formatByType(mixed $value, mixed $row, bool $forExport): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $options = $this->typeOptions;

        switch ($this->type) {
            case 'number':
                if (! is_numeric($value)) {
                    return $value;
                }

                return $forExport
                    ? round((float) $value, $options['decimals'])
                    : number_format((float) $value, $options['decimals'], $options['decimalPoint'], $options['thousandsSeparator']);

            case 'money':
                if (! is_numeric($value)) {
                    return $value;
                }

                if ($forExport) {
                    return round((float) $value, $options['decimals']);
                }

                $number = number_format((float) $value, $options['decimals'], $options['decimalPoint'], $options['thousandsSeparator']);

                if ($options['currency'] === '') {
                    return $number;
                }

                return $options['position'] === 'after'
                    ? $number.' '.$options['currency']
                    : $options['currency'].' '.$number;

            case 'date':
            case 'datetime':
                try {
                    return Carbon::parse($value)->format($options['format']);
                } catch (Throwable) {
                    return $value;
                }

            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $options['true'] : $options['false'];

            case 'badge':
                $entry = $options['map'][$value] ?? null;
                $label = is_array($entry) ? ($entry[0] ?? $value) : $value;
                $class = is_array($entry) ? ($entry[1] ?? $options['default_class']) : ($entry ?? $options['default_class']);

                return $forExport ? $label : '<span class="'.e($class).'">'.e($this->stringify($label)).'</span>';

            case 'link':
                $url = ($options['url'])($value, $row, $this);

                if ($forExport) {
                    return $url ?? $value;
                }

                return '<a href="'.e((string) $url).'" target="'.e($options['target']).'">'.e($this->stringify($value)).'</a>';

            case 'image':
                $src = isset($options['src']) ? ($options['src'])($value, $row, $this) : $value;

                if ($forExport) {
                    return $src;
                }

                return '<img src="'.e((string) $src).'" width="'.e((string) $options['width']).'" alt="" />';
        }

        return $value;
    }

    protected function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) json_encode($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'name' => $this->name,
            'type' => $this->type,
            'searchable' => $this->searchable,
            'sortable' => $this->sortable,
            'is_hidden' => $this->isHidden,
            'hideable' => $this->isHideable(),
            'hidden_by_default' => $this->isHiddenByDefault(),
            'hide_in' => $this->hideIn,
            'aggregate' => $this->aggregate,
            'align' => $this->align,
            'width' => $this->width,
            'html' => $this->html,
            'style' => $this->style instanceof Closure ? '[callback]' : $this->style,
        ];
    }
}
