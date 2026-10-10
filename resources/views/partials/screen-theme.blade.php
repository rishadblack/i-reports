{{-- On-screen palette for config('i-reports.screen_style'): light by default, dark under Bootstrap 5.3's data-bs-theme="dark". --}}
:root,
[data-bs-theme="light"] {
    --ir-page-bg: #ffffff;
    --ir-text: #1f2937;
    --ir-th-color: #ffffff;
    --ir-th-bg: #1f2937;
    --ir-th-border: #1f2937;
    --ir-td-color: #1f2937;
    --ir-td-border: #e5e7eb;
    --ir-zebra-bg: #f8fafc;
    --ir-group-color: #1f2937;
    --ir-group-bg: #e8edf3;
    --ir-group-border: #d5dde6;
    --ir-aggregate-color: #1f2937;
    --ir-aggregate-bg: #eef2f6;
    --ir-aggregate-border: #cbd5e1;
}

[data-bs-theme="dark"] {
    --ir-page-bg: #212529;
    --ir-text: #dee2e6;
    --ir-th-color: #f8f9fa;
    --ir-th-bg: #343a40;
    --ir-th-border: #495057;
    --ir-td-color: #dee2e6;
    --ir-td-border: #3b4148;
    --ir-zebra-bg: #2b3035;
    --ir-group-color: #f8f9fa;
    --ir-group-bg: #2f353b;
    --ir-group-border: #495057;
    --ir-aggregate-color: #f8f9fa;
    --ir-aggregate-bg: #31373d;
    --ir-aggregate-border: #495057;
}

.i-reports-table tbody tr:nth-child(even) td {
    background-color: var(--ir-zebra-bg);
}
