@props([
    'status' => 'draft',
])

@php
    $map = [
        'draft' => ['label' => 'Draft', 'class' => 'bg-brand-soft text-muted'],
        'published' => ['label' => 'Published', 'class' => 'bg-info-soft text-info-ink'],
        'active' => ['label' => 'Active', 'class' => 'bg-success-soft text-success-ink'],
        'closed' => ['label' => 'Closed', 'class' => 'bg-brand-soft text-muted'],
        'archived' => ['label' => 'Closed', 'class' => 'bg-brand-soft text-muted'],
        'paused' => ['label' => 'Pending', 'class' => 'bg-warning-soft text-warning-ink'],
        'passed' => ['label' => 'Passed', 'class' => 'bg-success-soft text-success-ink'],
        'failed' => ['label' => 'Failed', 'class' => 'bg-danger-soft text-danger-ink'],
        'pending' => ['label' => 'Pending', 'class' => 'bg-warning-soft text-warning-ink'],
        'rejected' => ['label' => 'Rejected', 'class' => 'bg-danger-soft text-danger-ink'],
        'approved' => ['label' => 'Approved', 'class' => 'bg-success-soft text-success-ink'],
        'pending_grading' => ['label' => 'Pending', 'class' => 'bg-warning-soft text-warning-ink'],
        'for_review' => ['label' => 'Pending', 'class' => 'bg-warning-soft text-warning-ink'],
        'synced' => ['label' => 'Synced', 'class' => 'bg-success-soft text-success-ink'],
        'offline' => ['label' => 'Offline', 'class' => 'bg-brand-soft text-muted'],
    ];

    $key = strtolower((string) $status);
    $config = $map[$key] ?? ['label' => ucfirst(str_replace('_', ' ', $key)), 'class' => 'bg-brand-soft text-muted'];
    $label = trim((string) $slot) !== '' ? $slot : $config['label'];
@endphp

<span {{ $attributes->merge(['class' => 'badge badge-dot '.$config['class']]) }}>
    {{ $label }}
</span>
