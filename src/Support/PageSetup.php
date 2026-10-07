<?php

namespace Rishadblack\IReports\Support;

use Rishadblack\IReports\BaseReportController;

/**
 * Paper, orientation, table font size and scale for print and PDF.
 *
 * Defaults come from the report (setPaperSize(), setOrientation(), setFontSize(), setScale())
 * and config('i-reports.page_setup'). A user may override them from the export dialog; only
 * values in the configured lists (or the report's own defaults) are accepted.
 */
class PageSetup
{
    public const ORIENTATIONS = ['portrait', 'landscape'];

    public function __construct(
        public readonly string $paper = 'A4',
        public readonly string $orientation = 'portrait',
        public readonly ?float $fontSize = null,
        public readonly int $scale = 100,
    ) {}

    /**
     * The report's defaults, overridden by whitelisted values from the request.
     *
     * @param  array<string, mixed>  $input
     */
    public static function resolve(BaseReportController $report, array $input = []): self
    {
        $options = self::options($report);
        $pick = fn (string $key, mixed $value, mixed $default) => self::allowed($options[$key], $value) ?? $default;

        return new self(
            (string) $pick('papers', $input['paper'] ?? null, $options['defaults']['paper']),
            (string) $pick('orientations', $input['orientation'] ?? null, $options['defaults']['orientation']),
            self::nullableFloat($pick('font_sizes', $input['font_size'] ?? null, $options['defaults']['font_size'])),
            (int) $pick('scales', $input['scale'] ?? null, $options['defaults']['scale']),
        );
    }

    /**
     * The choices offered in the export dialog and the report's defaults.
     *
     * @return array{defaults: array{paper: string, orientation: string, font_size: float|null, scale: int}, papers: array<int, string>, orientations: array<int, string>, font_sizes: array<int, float>, scales: array<int, int>}
     */
    public static function options(BaseReportController $report): array
    {
        $defaults = [
            'paper' => $report->getPaperSize(),
            'orientation' => in_array($report->getOrientation(), self::ORIENTATIONS, true) ? $report->getOrientation() : 'portrait',
            'font_size' => $report->getFontSize(),
            'scale' => $report->getScale(),
        ];

        $list = function (string $key, mixed $default, callable $cast): array {
            $values = array_map($cast, (array) config("i-reports.page_setup.{$key}", []));

            if ($default !== null) {
                $values[] = $cast($default);
            }

            $values = array_values(array_unique($values, SORT_REGULAR));

            if ($key !== 'papers') {
                sort($values);
            }

            return $values;
        };

        return [
            'defaults' => $defaults,
            'papers' => $list('papers', $defaults['paper'], fn ($value): string => (string) $value),
            'orientations' => self::ORIENTATIONS,
            'font_sizes' => $list('font_sizes', $defaults['font_size'], fn ($value): float => (float) $value),
            'scales' => $list('scales', $defaults['scale'], fn ($value): int => (int) $value),
        ];
    }

    protected static function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * @param  array<int, mixed>  $allowed
     */
    protected static function allowed(array $allowed, mixed $value): mixed
    {
        if ($value === null || $value === '' || ! is_scalar($value)) {
            return null;
        }

        foreach ($allowed as $option) {
            if (is_string($option) ? strcasecmp($option, (string) $value) === 0 : (is_numeric($value) && (float) $option === (float) $value)) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @return array{paper: string, orientation: string, font_size: float|null, scale: int}
     */
    public function toArray(): array
    {
        return [
            'paper' => $this->paper,
            'orientation' => $this->orientation,
            'font_size' => $this->fontSize,
            'scale' => $this->scale,
        ];
    }

    public function isLandscape(): bool
    {
        return $this->orientation === 'landscape';
    }

    public function factor(): float
    {
        return $this->scale / 100;
    }

    /**
     * The CSS @page size, e.g. "a4 landscape".
     */
    public function cssPageSize(): string
    {
        return strtolower($this->paper).' '.$this->orientation;
    }

    /**
     * A table cell style with the chosen font size (replacing or adding font-size). Without a
     * chosen size the style is returned as designed.
     */
    public function tableStyle(string $style): string
    {
        if ($this->fontSize === null) {
            return $style;
        }

        $size = 'font-size: '.$this->formatNumber($this->fontSize).'pt';

        if (! preg_match('/font-size\s*:/i', $style)) {
            return trim(rtrim(trim($style), ';').'; '.$size.';', '; ').';';
        }

        return (string) preg_replace('/font-size\s*:\s*[^;"]+/i', $size, $style);
    }

    /**
     * Scale the size declarations (font-size, padding, heights, letter-spacing) of a CSS text.
     * Used for PDF, where mPDF has no zoom; print uses CSS zoom instead.
     */
    public function scaleCss(string $css): string
    {
        if ($this->scale === 100) {
            return $css;
        }

        return (string) preg_replace_callback(
            '/\b(font-size|padding(?:-top|-right|-bottom|-left)?|max-height|max-width|min-height|line-height|letter-spacing)\s*:\s*([^;"}]+)/i',
            fn (array $match): string => $match[1].': '.preg_replace_callback(
                '/(\d*\.?\d+)(pt|px|mm)\b/i',
                fn (array $number): string => $this->formatNumber((float) $number[1] * $this->factor()).$number[2],
                $match[2],
            ),
            $css,
        );
    }

    /**
     * Scale every style="" attribute and <style> block of an HTML fragment.
     */
    public function scaleHtml(string $html): string
    {
        if ($this->scale === 100) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(\bstyle=")([^"]*)(")|(<style\b[^>]*>)(.*?)(<\/style>)/is',
            fn (array $match): string => isset($match[4])
                ? $match[4].$this->scaleCss($match[5]).$match[6]
                : $match[1].$this->scaleCss($match[2]).$match[3],
            $html,
        );
    }

    protected function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
