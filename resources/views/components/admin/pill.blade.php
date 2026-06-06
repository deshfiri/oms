@props(['status' => null, 'label' => null])
@php
    $label = $label ?? ucfirst(str_replace('_',' ', (string)$status));
@endphp
<span class="pill pill-{{ $status }}">{{ $label }}</span>
