<?php
namespace Rishadblack\IReports\Views;

use Illuminate\Contracts\Support\Arrayable;

class Column implements Arrayable
{
    protected string $title;
    protected string $name;
    protected $style;
    protected bool $searchable = false;
    protected bool $sortable   = false;
    protected bool $isHidden   = false;
    protected array $hideIn    = [];
    protected $format;

    public function __construct(string $title, string $name)
    {
        $this->title = $title;
        $this->name = $name;
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

    public function applyFormat($value, $row) : mixed
    {
        if (is_callable($this->format)) {
            return call_user_func($this->format, $value, $row, $this);
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
