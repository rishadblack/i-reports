<td {{ $attributes->merge(['style' => $style]) }}>{!! $slot->isEmpty() ? $output : $slot !!}</td>
