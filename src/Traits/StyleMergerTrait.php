<?php

namespace Rishadblack\IReports\Traits;

trait StyleMergerTrait
{
    /**
     * @return array<string, string>
     */
    protected function parseStyleString(string $style): array
    {
        $rules = [];
        foreach (explode(';', $style) as $rule) {
            if (trim($rule) === '') {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $rule, 2) + [1 => '']);
            if ($key !== '') {
                $rules[$key] = $value;
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, string>  $rules
     */
    protected function buildStyleString(array $rules): string
    {
        if (count($rules) === 0) {
            return '';
        }

        $parts = [];
        foreach ($rules as $key => $value) {
            $parts[] = "$key: $value";
        }

        return implode('; ', $parts).';';
    }

    protected function mergeStyles(?string $baseStyle, ?string $overrideStyle): string
    {
        $baseStyle = $baseStyle ?? '';
        $overrideStyle = $overrideStyle ?? '';

        $baseRules = $this->parseStyleString($baseStyle);
        $overrideRules = $this->parseStyleString($overrideStyle);

        $merged = array_merge($baseRules, $overrideRules);

        return $this->buildStyleString($merged);
    }
}
