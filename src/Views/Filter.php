<?php
namespace Rishadblack\IReports\Views;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class Filter
{
    protected $name;                 // This is now acting as the title of the filter.
    protected $title;                // This serves as the unique identifier (or 'name') for the filter.
    protected $placeholder;          // This serves as the unique identifier (or 'name') for the filter.
    protected $responseTime = '500'; // This serves as response time for the filter.
    protected $customClass;
    protected $filter_type = 'text';  // This is the filter ID (key).
    protected $options     = [];      // Filter options for dropdown/select.
    protected $filterPillTitle;       // Title for filter pill in UI.
    protected $filterPillValues = []; // Values for displaying pills in UI.
    protected $filterCallback;        // Callback to apply the filter logic.
    protected $component;             // Search component for the filter.
    protected $componentParameters = [];

    /**
     * Create a new filter instance with a name and an optional column (ID).
     *
     * If column is null, it defaults to the snake_cased name with 'filter_' prefix.
     */
    public static function make(string $title, string $name): self
    {
        $instance = new self();

        // 'name' now represents the filter title.
        $instance->title = $title;
        $instance->name = $name;

        return $instance;
    }

    /**
     * Get the unique key for the filter (i.e., column name).
     */
    public function key(): string
    {
        return $this->name;
    }

    /**
     * Set filter options (e.g., dropdown choices).
     */
    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Set a callback function to be applied for the filter logic.
     */
    public function filter(Closure $callback): self
    {
        $this->filterCallback = $callback;
        return $this;
    }

    /**
     * Set the placeholder text for the filter input (applicable for text filters).
     */

    public function placeholder(string $values): self
    {
        $this->placeholder = $values;
        return $this;
    }

    /**
     * Set the response time for the filter (used for debouncing).
     */

    public function responseTime(string $values): self
    {
        $this->responseTime = $values;
        return $this;
    }

    /**
     * Set custom CSS classes for the filter input.
     */

    public function customClass(string $values): self
    {
        $this->customClass = $values;
        return $this;
    }

    /**
     * Set the filter type to 'text' for a simple text input.
     */

    public function text(): self
    {
        $this->filter_type = 'text';
        return $this;
    }

    /**
     * Set the filter type to 'select' and provide options for a dropdown.
     */

    public function select(array $options = []): self
    {
        if (count($options) > 0) {
            $this->options = $options;
        }

        $this->filter_type = 'select';
        return $this;
    }

    /**
     * Set the filter type to 'component' and specify the component to be used for this filter.
     */

    public function component(string $component, array $componentParameters = []): self
    {
        $this->filter_type = 'component';
        $this->component = $component;
        $this->componentParameters = $componentParameters;
        return $this;
    }

    /**
     * Set the filter type to 'blade_component' and specify the blade component to be used for this filter.
     */

    public function bladeComponent(string $component, array $componentParameters = []): self
    {
        $this->filter_type = 'blade_component';
        $this->component = $component;
        $this->componentParameters = $componentParameters;
        return $this;
    }

    /**
     * Apply the filter logic to the query using the stored callback.
     */
    public function apply(Builder $query, $value)
    {
        if ($this->filterCallback) {
            call_user_func($this->filterCallback, $query, $value);
        }
    }

    /**
     * Convert the filter instance to an array for passing data to views.
     * The callback is intentionally excluded.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'placeholder' => $this->placeholder,
            'class' => $this->customClass,
            'filter_type' => $this->filter_type,
            'component' => $this->component,
            'component_parameters' => $this->componentParameters,
            'response_time' => $this->responseTime,
            'options' => $this->options,
        ];
    }
}