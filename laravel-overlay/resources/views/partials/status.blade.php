@php
    $map = ['completed' => 'success', 'processing' => 'info', 'pending' => 'secondary', 'failed' => 'danger',
            'verified' => 'success', 'rejected' => 'danger', 'needs_correction' => 'warning',
            'answered' => 'primary', 'insufficient_evidence' => 'secondary', 'conflicting_evidence' => 'warning', 'error' => 'danger'];
@endphp
<span class="badge status-badge bg-{{ $map[$status] ?? 'secondary' }}">{{ str_replace('_', ' ', $status) }}</span>
