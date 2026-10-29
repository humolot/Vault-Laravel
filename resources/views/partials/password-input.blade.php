{{-- campo de senha com mostrar/ocultar; opcionalmente gerador e medidor --}}
<div class="input-group {{ $groupClass ?? '' }}">
    @if (!empty($icon))<span class="input-group-text"><i class="bi {{ $icon }}"></i></span>@endif
    <input type="password" class="form-control font-mono @error($name) is-invalid @enderror" id="{{ $id ?? $name }}" name="{{ $name }}"
           value="{{ $value ?? '' }}" autocomplete="{{ $autocomplete ?? 'new-password' }}" @if(!empty($required)) required @endif
           @if(!empty($placeholder)) placeholder="{{ $placeholder }}" @endif
           @if(!empty($meter)) data-strength="#{{ ($id ?? $name) }}-meter" @endif spellcheck="false">
    <button class="btn btn-outline-secondary" type="button" data-toggle-visibility="#{{ $id ?? $name }}" title="{{ __('app.show') }}"><i class="bi bi-eye"></i></button>
    @if (!empty($generator))
        <button class="btn btn-outline-secondary" type="button" data-generate="#{{ $id ?? $name }}" title="{{ __('app.generate') }}"><i class="bi bi-magic"></i></button>
    @endif
</div>
@if (!empty($meter))
    <div class="strength mt-2" id="{{ ($id ?? $name) }}-meter"><div class="strength-bar"><span></span></div><small class="strength-label text-body-secondary"></small></div>
@endif
