@props(['name', 'label', 'options' => [], 'value' => null, 'help' => null, 'optional' => false, 'placeholder' => null, 'id' => null])
@php
    $id = $id ?? str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $hasError = $errors->has($name);
    $selected = old($name, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div class="mb-3">
    <label class="form-label" for="{{ $id }}">{{ $label }}@if ($optional) <span class="optional">(facultatif)</span>@endif</label>
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->class(['form-select', 'is-invalid' => $hasError]) }}
        @if (! $optional) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hasError)<div class="invalid-feedback" id="{{ $id }}-error">{{ $errors->first($name) }}</div>@endif
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
