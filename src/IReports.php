<?php

namespace Rishadblack\IReports;

use Illuminate\Support\Collection;

/**
 * Registry of explicitly named reports. Convention-based names still resolve without registration.
 */
class IReports
{
    /** @var array<string, class-string<BaseReportController>> */
    protected array $reports = [];

    /**
     * @param  array<string, class-string<BaseReportController>>|string  $name
     * @param  class-string<BaseReportController>|null  $class
     */
    public function register(array|string $name, ?string $class = null): static
    {
        $names = is_array($name) ? $name : [$name => $class];

        foreach ($names as $reportName => $reportClass) {
            if (is_string($reportName) && is_string($reportClass)) {
                $this->reports[$reportName] = $reportClass;
            }
        }

        return $this;
    }

    /**
     * @return class-string<BaseReportController>|null
     */
    public function find(string $name): ?string
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return array<string, class-string<BaseReportController>>
     */
    public function all(): array
    {
        $configured = config('i-reports.reports', []);

        return array_merge(is_array($configured) ? $configured : [], $this->reports);
    }

    /**
     * @return Collection<string, class-string<BaseReportController>>
     */
    public function collection(): Collection
    {
        return collect($this->all());
    }
}
