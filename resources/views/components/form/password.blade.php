@props(['name', 'label', 'autocomplete' => 'current-password', 'help' => null])
@php $hasError = $errors->has($name); @endphp
{{-- Un seul champ, avec bouton pour afficher la saisie : pas de confirmation à retaper. --}}
<div class="mb-3">
    <label class="form-label" for="{{ $name }}">{{ $label }}</label>
    <div class="input-group @if ($hasError) has-validation @endif">
        <input type="password" name="{{ $name }}" id="{{ $name }}" autocomplete="{{ $autocomplete }}" required
            {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif>
        <button type="button" class="btn btn-outline-secondary" data-password-toggle="{{ $name }}" aria-pressed="false">Afficher</button>
        @if ($hasError)<div class="invalid-feedback" id="{{ $name }}-error">{{ $errors->first($name) }}</div>@endif
    </div>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
