<?php

namespace Rishadblack\IReports\View\Components;

use Illuminate\Contracts\View\View;

/**
 * Marks a point where a custom PDF view may be split before it is handed to mPDF,
 * like laravel-mpdf's chunkLoadView. Place it between complete blocks (never inside a
 * table): the HTML is cut at each marker and every piece is written separately, which
 * keeps each piece under pcre.backtrack_limit. Renders nothing in other outputs.
 */
class Chunk extends BaseComponent
{
    public string $separator = '';

    public function render(): View
    {
        $this->separator = $this->export() === 'pdf' ? (string) config('i-reports.pdf_chunk_separator', '<html-separator/>') : '';

        return view('i-reports::components.chunk');
    }
}
