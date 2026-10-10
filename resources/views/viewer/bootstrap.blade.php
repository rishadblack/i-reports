{{-- Bootstrap 5.3 report viewer. Needs Bootstrap CSS only; menus and the filter dialog run on Alpine (bundled with Livewire). --}}
@php
    $ui = [
        'filter_wrapper' => 'col-12 col-md-6',
        'label' => 'form-label',
        'input' => 'form-control',
        'select' => 'form-select',
        'range' => 'input-group',
    ];
    $exportIcons = [
        'print' => '<path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/>',
        'pdf' => '<path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/><path d="M4.6 11.85V9.6h.83c.6 0 .98.33.98.85 0 .53-.38.86-.98.86h-.36v.54zm.47-1.2h.31c.3 0 .47-.14.47-.4s-.17-.4-.47-.4h-.31zm1.62 1.2V9.6h.84c.73 0 1.12.42 1.12 1.12s-.39 1.13-1.12 1.13zm.47-.4h.33c.43 0 .67-.26.67-.73 0-.46-.24-.72-.67-.72h-.33zm1.82.4V9.6h1.5v.4H9.45v.55h.93v.38h-.93v.92z"/>',
        'xlsx' => '<path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z"/><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>',
        'csv' => '<path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/><path d="M4.5 12.5A.5.5 0 0 1 5 12h3a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5m0-2A.5.5 0 0 1 5 10h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5m0-2A.5.5 0 0 1 5 8h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5"/>',
    ];
    $activeFilters = $this->activeFilters();
    $filterCount = $this->activeFilterCount();
    $pageSetupEnabled = $this->pageSetupEnabled();
    $pageSetupDefaults = $pageSetupEnabled ? $this->pageSetupOptions()['defaults'] : [];
@endphp

<div class="i-reports-viewer card border-0 shadow-sm overflow-hidden"
    x-data="{ filtersOpen: false, exportOpen: false, presetsOpen: false, columnsOpen: false, setupOpen: false, setupFormat: 'pdf', setupDefaults: @js($pageSetupDefaults), setup: @js($pageSetupDefaults) }"
    x-on:i-reports:filters-applied.window="filtersOpen = false"
    x-on:keydown.escape.window="filtersOpen = false; exportOpen = false; presetsOpen = false; columnsOpen = false; setupOpen = false">

    {{-- Header: title, record count, export menu --}}
    <div class="card-header bg-body border-bottom px-3 py-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="me-auto">
                <h2 class="h5 fw-semibold mb-0">{{ $this->reportTitle() }}</h2>
                <div class="small text-body-secondary">
                    {{ number_format($total) }} {{ $total === 1 ? 'record' : 'records' }}
                    @if ($filterCount > 0)
                        &middot; {{ $filterCount }} {{ $filterCount === 1 ? 'filter' : 'filters' }} applied
                    @endif
                </div>
            </div>

            @if ($presets_enabled)
                <div class="dropdown" x-on:click.outside="presetsOpen = false">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" x-on:click="presetsOpen = ! presetsOpen" aria-haspopup="menu" x-bind:aria-expanded="presetsOpen">
                        Saved views
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm p-2" x-bind:class="{ 'show': presetsOpen }" style="right: 0; left: auto; min-width: 260px;">
                        @forelse ($this->presets() as $preset)
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="dropdown-item rounded flex-grow-1" wire:click="applyPreset({{ $preset['id'] }})" x-on:click="presetsOpen = false">{{ $preset['name'] }}</button>
                                <button type="button" class="btn btn-sm btn-link text-danger p-1" wire:click="deletePreset({{ $preset['id'] }})" aria-label="Delete {{ $preset['name'] }}">&times;</button>
                            </div>
                        @empty
                            <div class="small text-body-secondary px-2 py-1">No saved views yet.</div>
                        @endforelse
                        <hr class="dropdown-divider">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control @error('preset_name') is-invalid @enderror" wire:model="preset_name" placeholder="Save current view as…" wire:keydown.enter="savePreset" aria-label="Preset name" />
                            <button type="button" class="btn btn-primary" wire:click="savePreset">Save</button>
                        </div>
                        @error('preset_name') <div class="invalid-feedback d-block small">{{ $message }}</div> @enderror
                    </div>
                </div>
            @endif

            @if (config('i-reports.show_export_button') && count($exportOptions) > 0)
                <div class="dropdown" x-on:click.outside="exportOpen = false">
                    <button type="button" id="i-reports-export-button" class="btn btn-primary btn-sm dropdown-toggle d-inline-flex align-items-center gap-2" x-on:click="exportOpen = ! exportOpen" aria-haspopup="menu" x-bind:aria-expanded="exportOpen">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708z"/></svg>
                        Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" x-bind:class="{ 'show': exportOpen }" style="right: 0; left: auto;" role="menu">
                        @foreach ($exportOptions as $exportOption)
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center gap-2" role="menuitem" data-export="{{ $exportOption['type'] }}"
                                    @if ($pageSetupEnabled && in_array($exportOption['type'], ['print', 'pdf'], true))
                                        x-on:click="exportOpen = false; setupFormat = @js($exportOption['type']); setupOpen = true"
                                    @else
                                        wire:click="exportAs('{{ $exportOption['type'] }}')" x-on:click="exportOpen = false"
                                    @endif
                                    >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="text-body-secondary" viewBox="0 0 16 16" aria-hidden="true">{!! $exportIcons[$exportOption['type']] ?? $exportIcons['csv'] !!}</svg>
                                    {{ $exportOption['name'] }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    {{-- Toolbar: search, filters, sort, page size, reset --}}
    <div class="card-body border-bottom px-3 py-2 bg-body-tertiary">
        <div class="d-flex flex-wrap align-items-center gap-2">
            @if (config('i-reports.show_search'))
                <div class="input-group input-group-sm" style="max-width: 340px;">
                    <span class="input-group-text bg-body" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242.656a5 5 0 1 1 0-10 5 5 0 0 1 0 10"/></svg>
                    </span>
                    <input id="i-reports-search" type="search" class="form-control" wire:model="search" wire:keydown.enter="searchReport" placeholder="Search…" aria-label="Search" />
                    <button type="button" id="i-reports-search-button" class="btn btn-primary" wire:click="searchReport">Search</button>
                </div>
            @endif

            @if (config('i-reports.show_filter_button') && (count($filter_list) > 0 || $filter_extended_view))
                <button type="button" class="btn btn-sm btn-outline-secondary bg-body d-inline-flex align-items-center gap-2" x-on:click="filtersOpen = true" aria-haspopup="dialog">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5z"/></svg>
                    Filters
                    @if ($filterCount > 0)
                        <span class="badge rounded-pill text-bg-primary">{{ $filterCount }}</span>
                    @endif
                </button>
            @endif

            @if (count($this->hideableColumns()) > 0)
                @php
                    $columnChoices = $this->hideableColumns();
                    $hiddenCount = count(array_filter($columnChoices, fn ($choice) => ! $choice['visible']));
                @endphp
                <div class="dropdown" x-on:click.outside="columnsOpen = false">
                    <button type="button" id="i-reports-columns-button" class="btn btn-sm btn-outline-secondary bg-body d-inline-flex align-items-center gap-2" x-on:click="columnsOpen = ! columnsOpen" aria-haspopup="menu" x-bind:aria-expanded="columnsOpen">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 2a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1zm5 0v12h6V2zM1 2v12h3V2zm11 0v12h3V2z"/></svg>
                        Columns
                        @if ($hiddenCount > 0)
                            <span class="badge rounded-pill text-bg-secondary">{{ $hiddenCount }} hidden</span>
                        @endif
                    </button>
                    <div class="dropdown-menu shadow-sm p-2" x-bind:class="{ 'show': columnsOpen }" style="min-width: 230px; max-height: 360px; overflow-y: auto;">
                        <div class="d-flex justify-content-between align-items-center px-2 pb-2 mb-1 border-bottom">
                            <span class="small fw-semibold text-body-secondary">Show columns</span>
                            @if ($hiddenCount > 0)
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" wire:click="showAllColumns">Show all</button>
                            @endif
                        </div>
                        @foreach ($columnChoices as $choice)
                            <label class="dropdown-item d-flex align-items-center gap-2 rounded" style="cursor: pointer;">
                                <input type="checkbox" class="form-check-input m-0" @checked($choice['visible']) wire:click="toggleColumn('{{ $choice['name'] }}')" data-column="{{ $choice['name'] }}" />
                                <span>{{ $choice['title'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (count($this->sortableColumns()) > 0)
                <div class="input-group input-group-sm w-auto">
                    <span class="input-group-text bg-body">Sort</span>
                    <select wire:model.live="sort_field" class="form-select" aria-label="Sort by">
                        <option value="">Default order</option>
                        @foreach ($this->sortableColumns() as $sortable)
                            <option value="{{ $sortable['name'] }}">{{ $sortable['title'] }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="sort_direction" class="form-select" aria-label="Sort direction" style="min-width: 8.75rem;">
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </select>
                </div>
            @endif

            <div class="d-flex align-items-center gap-2 ms-auto">
                @if (config('i-reports.show_pagination'))
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text bg-body">Rows</span>
                        <select wire:model.live="per_page" class="form-select" aria-label="Rows per page">
                            @foreach ($per_page_list as $perPageOption)
                                <option value="{{ $perPageOption }}">{{ $perPageOption }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (config('i-reports.show_reset_button'))
                    <button type="button" class="btn btn-sm btn-outline-secondary bg-body" wire:click="resetReport" title="Clear search, filters and sorting">Reset</button>
                @endif
            </div>
        </div>

        @if (count($activeFilters) > 0)
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2" aria-label="Active filters">
                @foreach ($activeFilters as $applied)
                    <span class="badge rounded-pill bg-body text-body border fw-normal d-inline-flex align-items-center gap-1 py-1 ps-2 pe-1">
                        <span class="text-body-secondary">{{ $applied['label'] }}:</span>
                        <span class="fw-semibold">{{ \Illuminate\Support\Str::limit($applied['value'], 40) }}</span>
                        <button type="button" class="btn-close ms-1" style="font-size: .5rem;" wire:click="removeFilter('{{ $applied['key'] }}')" aria-label="Remove {{ $applied['label'] }}"></button>
                    </span>
                @endforeach
                @if (count($activeFilters) > 1)
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" wire:click="resetReport">Clear all</button>
                @endif
            </div>
        @endif

        @php
            $recentExports = $this->recentExports();
            $statusBadges = [
                'queued' => ['Queued', 'text-bg-secondary'],
                'processing' => ['Preparing…', 'text-bg-warning'],
                'ready' => ['Ready', 'text-bg-success'],
                'failed' => ['Failed', 'text-bg-danger'],
            ];
        @endphp

        @if ($export_message !== '' || count($recentExports) > 0)
            <div class="mt-2 border rounded bg-body" @if ($this->hasPendingExports() && $realtime === 'poll') wire:poll.{{ max(1, (int) config('i-reports.queue.poll_seconds', 3)) }}s="refreshExports" @endif>
                @if ($this->hasPendingExports() && $realtime === 'broadcast')
                    {{-- Socket mode: updates arrive over Echo. Poll slowly as a safety net, or at the normal
                         rate when this page has no window.Echo or the user cannot join a private channel. --}}
                    <div class="d-none" data-i-reports-fallback-poll wire:key="fallback-poll-{{ implode('-', $pending_export_ids) }}"
                        x-data="{
                            timer: null,
                            init() {
                                const live = {{ $this->canListenForBroadcasts() ? 'true' : 'false' }} && typeof window.Echo !== 'undefined';
                                const seconds = live ? {{ max(5, (int) config('i-reports.queue.fallback_poll_seconds', 30)) }} : {{ max(1, (int) config('i-reports.queue.poll_seconds', 3)) }};
                                this.timer = setInterval(() => $wire.refreshExports(), seconds * 1000);
                            },
                            destroy() { clearInterval(this.timer); },
                        }"></div>
                @endif

                @if ($export_message !== '')
                    <div class="d-flex align-items-center gap-2 px-3 py-2 small border-bottom text-body-secondary" role="status">
                        @if ($this->hasPendingExports())
                            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
                        @endif
                        {{ $export_message }}
                    </div>
                @endif

                @if (count($recentExports) > 0)
                    <ul class="list-group list-group-flush small" aria-label="Background exports">
                        @foreach ($recentExports as $queued)
                            <li class="list-group-item d-flex flex-wrap align-items-center gap-2 py-2" wire:key="export-{{ $queued['id'] }}">
                                <span class="fw-semibold text-uppercase" style="width: 3rem;">{{ $queued['format'] }}</span>
                                <span class="badge {{ $statusBadges[$queued['status']][1] ?? 'text-bg-light' }}">{{ $statusBadges[$queued['status']][0] ?? $queued['status'] }}</span>
                                <span class="text-body-secondary">{{ $queued['created'] }}@if ($queued['size']) &middot; {{ $queued['size'] }}@endif</span>
                                @if ($queued['error'])
                                    <span class="text-danger text-truncate" style="max-width: 320px;" title="{{ $queued['error'] }}">{{ $queued['error'] }}</span>
                                @endif
                                <span class="ms-auto d-flex gap-1">
                                    @if ($queued['url'])
                                        <a href="{{ $queued['url'] }}" class="btn btn-sm btn-success py-0" data-download="{{ $queued['id'] }}">Download</a>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-link text-body-secondary py-0" wire:click="dismissExport({{ $queued['id'] }})" aria-label="Remove export">&times;</button>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>

    {{-- Report body with loading overlay --}}
    <div class="position-relative">
        <div wire:loading.flex class="position-absolute top-0 start-0 w-100 h-100 align-items-center justify-content-center bg-body bg-opacity-75" style="z-index: 5;">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>

        @if ($mode === 'iframe')
            <iframe src="{{ $reportUrl }}" title="{{ $this->reportTitle() }}" class="d-block w-100 border-0 bg-body" style="height: calc(100vh - 300px); min-height: 420px;"></iframe>
        @else
            <div class="i-reports-inline table-responsive p-2">
                <style>@include('i-reports::partials.screen-theme')</style>
                {!! $reportHtml !!}
            </div>
        @endif
    </div>

    {{-- Footer: summary and pagination --}}
    @if (config('i-reports.show_pagination'))
        <div class="card-footer bg-body px-3 py-2">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div class="small text-body-secondary text-nowrap">
                    Showing {{ $this->showingFrom() }}–{{ $this->showingTo() }} of {{ $total }} results
                    <span class="d-none d-sm-inline">&middot; Page {{ $page }} of {{ $last_page }}</span>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <nav aria-label="Report pages">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item @if ($page <= 1) disabled @endif">
                                <button type="button" class="page-link" wire:click="firstPage" @disabled($page <= 1) aria-label="First page">First</button>
                            </li>
                            <li class="page-item @if ($page <= 1) disabled @endif">
                                <button type="button" class="page-link" wire:click="prevPage" @disabled($page <= 1) aria-label="Previous page">Prev</button>
                            </li>
                            @foreach ($this->pageWindow() as $number)
                                @if ($number === null)
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                @else
                                    <li class="page-item @if ($number === $page) active @endif" @if ($number === $page) aria-current="page" @endif>
                                        <button type="button" class="page-link" wire:click="goTo({{ $number }})">{{ $number }}</button>
                                    </li>
                                @endif
                            @endforeach
                            <li class="page-item @if ($page >= $last_page) disabled @endif">
                                <button type="button" class="page-link" wire:click="nextPage" @disabled($page >= $last_page) aria-label="Next page">Next</button>
                            </li>
                            <li class="page-item @if ($page >= $last_page) disabled @endif">
                                <button type="button" class="page-link" wire:click="lastPage" @disabled($page >= $last_page) aria-label="Last page">Last</button>
                            </li>
                        </ul>
                    </nav>

                    <form wire:submit="goToPage" class="input-group input-group-sm" style="width: 120px;">
                        <input type="number" min="1" max="{{ $last_page }}" wire:model="page" class="form-control" aria-label="Go to page" />
                        <button id="i-reports-go-button" class="btn btn-outline-secondary" type="submit">Go</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <style>[x-cloak] { display: none !important; }</style>

    @if ($pageSetupEnabled)
        @include('i-reports::viewer.page-setup')
    @endif

    {{-- Filter dialog (Bootstrap 5.3 modal markup, toggled by Alpine; no Bootstrap JS needed) --}}
    @if (count($filter_list) > 0 || $filter_extended_view)
        <div x-show="filtersOpen" x-cloak class="modal-backdrop fade show"></div>
        <div x-show="filtersOpen" x-cloak id="i-reports-filter-dialog" class="modal fade show" x-bind:class="{ 'd-block': filtersOpen }" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="i-reports-filter-title" x-on:click.self="filtersOpen = false">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title fw-semibold" id="i-reports-filter-title">Filters</h5>
                            <div class="small text-body-secondary">Narrow down {{ $this->reportTitle() }}</div>
                        </div>
                        <button type="button" class="btn-close" aria-label="Close" x-on:click="filtersOpen = false; $wire.discardFilters()"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            @include('i-reports::viewer.filters', ['ui' => $ui])
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none text-danger px-0" wire:click="filterReset" wire:loading.attr="disabled">Clear filters</button>
                        <div class="d-flex gap-2">
                            @if ($this->hasPendingFilters)
                                <span class="small text-body-secondary align-self-center" data-pending-filters>Changes not applied yet</span>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="filtersOpen = false; $wire.discardFilters()">Cancel</button>
                            <button type="button" class="btn btn-sm btn-primary" wire:click="filterSubmit" wire:loading.attr="disabled">Apply filters</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
