{{-- Print / PDF page setup dialog (Bootstrap 5.3 modal markup, toggled by Alpine). Choices stay for the rest of the visit. --}}
@php
    $setupOptions = $this->pageSetupOptions();
    $setupDefaults = $setupOptions['defaults'];
@endphp
<div x-show="setupOpen" x-cloak class="modal-backdrop fade show"></div>
<div x-show="setupOpen" x-cloak class="modal fade show" x-bind:class="{ 'd-block': setupOpen }" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="i-reports-setup-title" x-on:click.self="setupOpen = false" id="i-reports-page-setup">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-semibold" id="i-reports-setup-title" x-text="setupFormat === 'print' ? 'Print setup' : 'PDF setup'">Page setup</h5>
                    <div class="small text-body-secondary">{{ $this->reportTitle() }}</div>
                </div>
                <button type="button" class="btn-close" aria-label="Close" x-on:click="setupOpen = false"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 align-items-stretch">
                    <div class="col-8">
                        <div class="mb-3">
                            <span class="form-label d-block small fw-semibold">Orientation</span>
                            <div class="btn-group w-100" role="group" aria-label="Orientation">
                                @foreach ($setupOptions['orientations'] as $orientation)
                                    <button type="button" class="btn btn-sm d-inline-flex align-items-center justify-content-center gap-2" data-orientation="{{ $orientation }}"
                                        x-bind:class="setup.orientation === @js($orientation) ? 'btn-primary' : 'btn-outline-secondary'"
                                        x-bind:aria-pressed="setup.orientation === @js($orientation)"
                                        x-on:click="setup.orientation = @js($orientation)">
                                        <span class="border border-2 border-current rounded-1" style="{{ $orientation === 'portrait' ? 'width: 10px; height: 14px;' : 'width: 14px; height: 10px;' }} border-color: currentColor !important;" aria-hidden="true"></span>
                                        {{ ucfirst($orientation) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="i-reports-setup-paper">Paper size</label>
                            <select id="i-reports-setup-paper" class="form-select form-select-sm" x-model="setup.paper">
                                @foreach ($setupOptions['papers'] as $paper)
                                    <option value="{{ $paper }}">{{ $paper }}{{ $paper === $setupDefaults['paper'] ? ' (default)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold" for="i-reports-setup-font">Font size</label>
                                <select id="i-reports-setup-font" class="form-select form-select-sm" x-model="setup.font_size">
                                    <option value="">As designed{{ $setupDefaults['font_size'] === null ? ' (default)' : '' }}</option>
                                    @foreach ($setupOptions['font_sizes'] as $size)
                                        <option value="{{ $size + 0 }}">{{ $size + 0 }} pt{{ $setupDefaults['font_size'] !== null && (float) $size === (float) $setupDefaults['font_size'] ? ' (default)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold" for="i-reports-setup-scale">Scale</label>
                                <select id="i-reports-setup-scale" class="form-select form-select-sm" x-model.number="setup.scale">
                                    @foreach ($setupOptions['scales'] as $scale)
                                        <option value="{{ $scale }}">{{ $scale }}%{{ $scale === $setupDefaults['scale'] ? ' (default)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Miniature page: shape follows the orientation, text lines follow font size and scale. --}}
                    <div class="col-4 d-flex align-items-center justify-content-center bg-body-tertiary rounded-3 p-2" aria-hidden="true">
                        <div class="bg-white border shadow-sm d-flex flex-column gap-1 p-2 overflow-hidden"
                            x-bind:style="setup.orientation === 'landscape' ? 'width: 108px; height: 76px;' : 'width: 76px; height: 108px;'">
                            <div class="rounded-1" style="height: 5px; width: 55%; background: var(--bs-primary);"></div>
                            <div class="rounded-1 mb-1" style="height: 2px; width: 100%; background: var(--bs-primary); opacity: .5;"></div>
                            <template x-for="line in 14">
                                <div class="rounded-1 flex-shrink-0 bg-secondary-subtle" x-bind:style="'width: 100%; height: ' + Math.max(1.5, (Number(setup.font_size) || 9) * setup.scale / 300).toFixed(1) + 'px;'"></div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-sm btn-link text-decoration-none px-0" x-on:click="setup = { ...setupDefaults }">Reset to defaults</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="setupOpen = false">Cancel</button>
                    <button type="button" id="i-reports-setup-submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled"
                        x-on:click="$wire.exportAs(setupFormat, setup); setupOpen = false"
                        x-text="setupFormat === 'print' ? 'Print' : 'Export PDF'">Export</button>
                </div>
            </div>
        </div>
    </div>
</div>
