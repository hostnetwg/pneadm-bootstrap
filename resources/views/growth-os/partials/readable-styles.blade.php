<style>
    .growth-readable {
        --growth-page-bg: #f8f9fb;
        --growth-surface: #eaeef4;
        --growth-surface-muted: #dfe5ed;
        --growth-border: #d0d8e2;
        --growth-input-border: #a3aebd;
        --growth-text: #1f2937;
        --growth-text-muted: #556070;
        --growth-done-surface: #e8f5ec;
        --growth-done-surface-muted: #d6ecdd;
        --growth-done-border: #a9d5b6;
        background: var(--growth-page-bg);
        border-radius: .75rem;
        padding: 1rem;
        color: var(--growth-text);
        line-height: 1.55;
    }

    @media (min-width: 992px) {
        .growth-readable {
            padding: 1.5rem;
        }
    }

    .growth-readable .text-secondary,
    .growth-readable .form-text {
        color: var(--growth-text-muted) !important;
    }

    .growth-readable .card,
    .growth-readable .list-group {
        background: var(--growth-surface);
        border-color: var(--growth-border) !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    }

    .growth-readable .card.border-primary {
        border-color: #9db8ec !important;
        border-left: 4px solid #2f6fdf !important;
    }

    .growth-readable .card-header,
    .growth-readable .card-footer {
        background: var(--growth-surface-muted) !important;
        border-color: var(--growth-border);
        padding: .85rem 1.25rem;
    }

    .growth-readable .card-header :is(h2, h3, .h5, .h6) {
        font-weight: 600;
    }

    .growth-readable .card-body {
        padding: 1.25rem;
    }

    .growth-readable .growth-hero {
        background: var(--growth-surface) !important;
        border-color: var(--growth-border) !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    }

    .growth-readable .growth-panel {
        background: #e2e8f0;
        border-color: var(--growth-border) !important;
    }

    .growth-readable .form-label {
        font-weight: 600;
        color: #344054;
        margin-bottom: .35rem;
    }

    .growth-readable .form-control,
    .growth-readable .form-select {
        background-color: #ffffff;
        border: 1px solid var(--growth-input-border);
        color: #111827;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, .05);
    }

    .growth-readable textarea.form-control {
        line-height: 1.6;
        resize: vertical;
    }

    .growth-readable .form-control:hover,
    .growth-readable .form-select:hover {
        border-color: #8793a5;
    }

    .growth-readable .form-control:focus,
    .growth-readable .form-select:focus {
        background-color: #ffffff;
        border-color: #2f6fdf;
        box-shadow: 0 0 0 .2rem rgba(47, 111, 223, .15);
    }

    .growth-readable .form-control:disabled,
    .growth-readable .form-control[readonly] {
        background-color: #f1f3f6;
    }

    .growth-readable .form-check-input {
        border-color: var(--growth-input-border);
    }

    .growth-readable .list-group-item.growth-material-approved {
        border-left: 4px solid var(--bs-success);
        background: var(--growth-done-surface);
    }

    .growth-readable .card.growth-done,
    .growth-readable .growth-hero.growth-done {
        background: var(--growth-done-surface) !important;
        border-color: var(--growth-done-border) !important;
        border-left: 4px solid var(--bs-success) !important;
    }

    .growth-readable .card.growth-done > .card-header,
    .growth-readable .card.growth-done > .card-footer {
        background: var(--growth-done-surface-muted) !important;
        border-color: var(--growth-done-border);
    }

    .growth-readable .list-group-item.growth-material-published {
        border-left: 4px solid var(--bs-primary);
    }

    .growth-readable .list-group-item.growth-material-skipped {
        border-left: 4px solid var(--bs-gray-400);
        background: var(--bs-gray-100);
        opacity: 0.6;
    }

    .growth-readable .list-group-item.growth-material-skipped:hover {
        opacity: 0.85;
    }

    .growth-readable .list-group-item.growth-material-review {
        border-left: 4px solid var(--bs-warning);
    }

    .growth-readable .growth-draft-editor {
        font-size: 1rem;
        line-height: 1.7;
        min-height: 22rem;
    }

    .growth-readable .growth-compare {
        background: #ffffff;
        border: 1px solid var(--growth-border);
        border-radius: .5rem;
        padding: 1rem;
        white-space: pre-line;
        line-height: 1.65;
        max-height: 32rem;
        overflow-y: auto;
    }

    .growth-readable .growth-compare-proposal {
        background: #f4f8ff;
        border-color: #b9cdf3;
    }

    .growth-ai-text {
        white-space: pre-line;
    }
</style>
