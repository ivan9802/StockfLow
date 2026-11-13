@props(['name', 'label', 'value' => null, 'help' => null, 'optional' => false, 'rows' => 3, 'id' => null])
@php
    $id = $id ?? str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $hasError = $errors->has($name);
@endphp
<div class="mb-3">
    <label class="form-label" for="{{ $id }}">{{ $label }}@if ($optional) <span class="optional">(facultatif)</span>@endif</label>
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
        @if (! $optional) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>{{ old($name, $value) }}</textarea>
    @if ($hasError)<div class="invalid-feedback" id="{{ $id }}-error">{{ $errors->first($name) }}</div>@endif
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
