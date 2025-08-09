<?php
namespace Rishadblack\IReports\Views;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Column implements Arrayable
{
    protected string $title;
    protected string $name;
    protected string $field;
    protected string $table;
    protected bool $custom = false;
    protected $style;
    protected bool $searchable = false;
    protected bool $sortable   = false;
    protected bool $isHidden   = false;
    protected array $hideIn    = [];
    protected $format;
    protected array $relations         = [];
    protected bool $eagerLoadRelations = false;

    public function __construct(string $title, string $name)
    {
        $this->title = $title;
        $this->name = $name;

        $this->title = trim($title);

        if ($name) {
            $this->name = trim($name);

            if (Str::contains($this->name, '.')) {
                $this->field = Str::afterLast($this->name, '.');
                $this->relations = explode('.', Str::beforeLast($this->name, '.'));
            } else {
                $this->field = $this->name;
            }
        } else {
            $this->field = Str::snake($title);
        }
    }

    public static function make(string $title, string $name): self
    {
        return new self($title, $name);
    }

    public function searchable(): self
    {
        $this->searchable = true;
        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function style(string | callable $style): self
    {
        if (! is_string($style) && ! is_callable($style)) {
            throw new \InvalidArgumentException('Style must be a string or callable');
        }

        $this->style = $style;
        return $this;
    }

    public function getStyle(): string | callable | null
    {
        return $this->style;
    }

    public function applyStyle($row = null): ?string
    {
        if (is_callable($this->style)) {
            return call_user_func($this->style, $row);
        }
        return $this->style;
    }

    public function format(callable $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function getFormat():  ? callable
    {
        return $this->format;
    }

    public function applyFormat($value, $row, $column) : mixed
    {
        if (is_callable($this->format)) {
            return call_user_func($this->format, $value, $row, $column);
        }

        return $value;
    }

    public function sortable(): self
    {
        $this->sortable = true;
        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function hide(): self
    {
        $this->isHidden = true;
        return $this;
    }

    public function hideIn(string $hideIn): self
    {
        $this->hideIn = explode('|', $hideIn);
        return $this;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function custom(): self
    {
        $this->custom = true;
        return $this;
    }

    public function isCustom(): bool
    {
        return $this->custom;
    }

    public function isBaseColumn(): bool
    {
        return ! $this->hasRelations();
    }

    public function hasRelations(): bool
    {
        return $this->getRelations()->count() > 0;
    }

    public function getRelations(): Collection
    {
        return collect($this->relations);
    }

    public function getRelationString(): ?string
    {
        if ($this->hasRelations()) {
            return $this->getRelations()->implode('.');
        }

        return null;
    }

    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function getColumn(): ?string
    {
        return $this->getTable() . '.' . $this->getField();
    }

    public function getColumnSelectName(): ?string
    {
        if ($this->isBaseColumn()) {
            return $this->getField();
        }

        return $this->getRelationString() . '.' . $this->getField();
    }

    public function getValue(Model $row): mixed
    {
        if ($this->isBaseColumn()) {
            return $row->{$this->getField()};
        }

        return $row->{$this->getRelationString() . '.' . $this->getField()};
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'name' => $this->name,
            'searchable' => $this->searchable,
            'sortable' => $this->sortable,
            'is_hidden' => $this->isHidden,
            'hide_in' => $this->hideIn,
            'style' => is_callable($this->style) ? '[callback]' : $this->style,
        ];
    }

    public function __toArray(): array
    {
        return $this->toArray();
    }
}
