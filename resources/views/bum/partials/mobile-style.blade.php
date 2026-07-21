<style>
    .bum-page .card {
        border-radius: 8px;
    }

    .bum-page .table-responsive {
        border-radius: 8px;
    }

    .bum-page .table th,
    .bum-page .table td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .bum-page .table td.wrap,
    .bum-page .table th.wrap {
        white-space: normal;
        min-width: 180px;
    }

    .bum-action-row {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    @media (max-width: 767.98px) {
        .bum-page {
            padding-left: .75rem;
            padding-right: .75rem;
        }

        .bum-page-header {
            align-items: stretch !important;
            gap: 1rem;
        }

        .bum-page-header > div:first-child {
            min-width: 0;
        }

        .bum-page-header h3 {
            font-size: 1.35rem;
        }

        .bum-action-row,
        .bum-page-header .btn,
        .bum-page-header form,
        .bum-page-header form .btn {
            width: 100%;
        }

        .bum-page-header form {
            flex-direction: column;
        }

        .bum-page .card-body {
            padding: 1rem;
        }

        .bum-page .form-control,
        .bum-page .form-select,
        .bum-page .btn {
            min-height: 44px;
        }

        .bum-stock-card-table thead {
            display: none;
        }

        .bum-stock-card-table,
        .bum-stock-card-table tbody,
        .bum-stock-card-table tr,
        .bum-stock-card-table td {
            display: block;
            width: 100%;
        }

        .bum-stock-card-table tr {
            border-bottom: 1px solid #e5e7eb;
            padding: .85rem 1rem;
        }

        .bum-stock-card-table td {
            border: 0;
            padding: .35rem 0;
            white-space: normal;
        }

        .bum-stock-card-table td::before {
            content: attr(data-label);
            display: block;
            color: #6b7280;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: .1rem;
        }

        .bum-stock-card-table td.text-end {
            text-align: left !important;
        }
    }
</style>
