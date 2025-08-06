<div>
    {{-- Header + Controls --}}
    <div class="container py-1">
        <div class="row g-1 align-items-center mb-3">
            <div class="col-auto">
                <input id="search" type="text" class="form-control form-control-sm" wire:model="search"
                    wire:keydown.enter="searchReport" style="min-width: 120px;" placeholder="Type to search" />
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm" wire:click='searchReport'
                    :disabled="$wire.search.trim() === ''">Search</button>
                <button type="submit" class="btn btn-danger btn-sm" wire:click='resetReport'>Reset</button>
            </div>
            <div class="col-auto ms-auto">
                <select wire:model.change="export" class="form-select form-select-sm">
                    <option value="">Export / Print</option>
                    @foreach (config('i-reports.export_options') as $export_option)
                        <option value="{{ $export_option['type'] }}">{{ $export_option['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-auto">
                <select wire:model.change="per_page" class="form-select form-select-sm">
                    @foreach ($per_page_list as $perPageList)
                        <option value="{{ $perPageList }}">{{ $perPageList }} Per Page</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Iframe container (fixed height, allows page scroll) --}}
    <div class="d-flex justify-content-center px-2 mb-2">
        <div class="bg-white shadow rounded border w-100"
            style="height: calc(100vh - 220px); max-width: 100%; overflow: hidden;">
            <iframe src="{{ $reportUrl }}"
                style="width: 100%; height: 100%; border: none; background: white; margin-bottom: 10px;"></iframe>
        </div>
    </div>


    {{-- Pagination controls --}}



    <div class="container">
        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
            {{-- Left: Showing summary --}}
            <div class="text-muted small" style="white-space: nowrap;">
                Showing {{ $this->showingFrom() }}–{{ $this->showingTo() }} of {{ $this->total }} results
            </div>

            {{-- Right: Pagination Controls --}}
            <div class="d-flex flex-wrap align-items-center gap-2 justify-content-center">
                <button class="btn btn-outline-primary btn-sm" wire:click="firstPage" @disabled($page <= 1)
                    style="min-width: 75px;">First</button>

                <button class="btn btn-outline-primary btn-sm" wire:click="prevPage" @disabled($page <= 1)
                    style="min-width: 75px;">Prev</button>

                <span class="text-muted">Page {{ $page }} of {{ $last_page }}</span>

                <button class="btn btn-outline-primary btn-sm" wire:click="nextPage" @disabled($page >= $last_page)
                    style="min-width: 75px;">Next</button>

                <button class="btn btn-outline-primary btn-sm" wire:click="lastPage" @disabled($page >= $last_page)
                    style="min-width: 75px;">Last</button>

                <form wire:submit.prevent="goToPage" class="input-group input-group-sm" style="width: 140px;">
                    <input type="number" min="1" max="{{ $last_page }}" wire:model.defer="page"
                        class="form-control" placeholder="Jump to page" />
                    <button class="btn btn-primary" type="submit" title="Go to page">Go</button>
                </form>
            </div>
        </div>
    </div>


</div>

@assets
    <style>
        @media (max-width: 600px) {
            .row.g-3>.col-auto {
                flex: 1 1 100% !important;
                max-width: 100% !important;
            }

            .form-control,
            .form-select {
                width: 100% !important;
            }

            .btn-group>.btn {
                flex: 1;
            }
        }
    </style>
@endassets

@script
    {{-- JS for export events --}}
    <script>
        window.addEventListener("exportIframe", (event) => {
            const exportUrl = event.detail.url ?? event.detail[0]?.url;
            if (exportUrl) {
                window.open(exportUrl, '_blank');
            }
        });

        $wire.on('exportEvent', (event) => {
            const exportUrl = event.url ?? event[0]?.url;
            if (exportUrl) {
                window.open(exportUrl, '_blank');
            }
        });
    </script>
@endscript
