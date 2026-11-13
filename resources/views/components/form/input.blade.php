@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'optional' => false, 'suffix' => null, 'id' => null])
@php
    $id = $id ?? str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
    $described = trim(($help ? $id.'-help ' : '').($hasError ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    <label class="form-label" for="{{ $id }}">{{ $label }}@if ($optional) <span class="optional">(facultatif)</span>@endif</label>
    @if ($suffix)<div class="input-group @if ($hasError) has-validation @endif">@endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"
        @if ($type !== 'password' && $type !== 'file') value="{{ old($errorKey, $value) }}" @endif
        {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
        @if (! $optional && $type !== 'file') required @endif
        @if ($described) aria-describedby="{{ $described }}" @endif
        @if ($hasError) aria-invalid="true" @endif>
    @if ($suffix)<span class="input-group-text">{{ $suffix }}</span>@endif
    @if ($hasError)<div class="invalid-feedback" id="{{ $id }}-error">{{ $errors->first($errorKey) }}</div>@endif
    @if ($suffix)</div>@endif
    @if ($help)<div class="form-text" id="{{ $id }}-help">{{ $help }}</div>@endif
</div>
