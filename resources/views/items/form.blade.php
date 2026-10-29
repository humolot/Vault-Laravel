@extends('layouts.app')
@section('title', $item ? __('app.edit_item') : __('app.new_item'))
@section('content')
@php $fieldsByType = collect($types)->map(fn ($t) => $t['fields'])->all(); @endphp
@php $rotatable = config('vault.rotatable_types'); @endphp
<div class="page-head">
    <div>
        <a href="{{ $item ? route('items.show', $item) : route('dashboard') }}" class="small link-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.back') }}</a>
        <h1 class="page-title mt-1">{{ $item ? __('app.edit_item') : __('app.new_item') }}</h1>
    </div>
</div>

<form method="POST" action="{{ $item ? route('items.update', $item) : route('items.store') }}" id="itemForm"
      data-fields='@json($fieldsByType)' data-rotatable='@json($rotatable)' novalidate autocomplete="off">
    @csrf
    @if ($item) @method('PUT') @endif

    <div class="card card-pad mb-4">
        <label class="form-label">{{ __('app.type') }}</label>
        <div class="type-picker">
            @foreach ($types as $key => $t)
                <input type="radio" class="btn-check" name="type" id="type_{{ $key }}" value="{{ $key }}" @checked(old('type', $type) === $key)
                       @disabled($item && (int) $item->owner_id !== (int) auth()->id())>
                <label class="type-option" for="type_{{ $key }}">
                    <i class="bi {{ $t['icon'] }} type-ico type-{{ $key }}"></i><span>{{ __('app.type_' . $key) }}</span>
                </label>
            @endforeach
        </div>
        @if ($item && (int) $item->owner_id !== (int) auth()->id())<input type="hidden" name="type" value="{{ $type }}">@endif
    </div>

    <div class="card card-pad mb-4">
        <div class="row g-3">
            <div class="col-md-7">
                <label for="name" class="form-label">{{ __('app.name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" maxlength="150" required
                       value="{{ old('name', $data['name'] ?? '') }}" placeholder="{{ __('app.name_placeholder') }}">
            </div>
            <div class="col-md-5">
                <label for="tags" class="form-label">{{ __('app.label') }}</label>
                <input type="text" class="form-control" id="tags" name="tags" maxlength="300"
                       value="{{ old('tags', implode(', ', $data['tags'] ?? [])) }}" placeholder="{{ __('app.tags_placeholder') }}">
                <div class="form-text">{{ __('app.tags_help') }}</div>
            </div>

            <div class="col-md-6" data-field="username">
                <label for="username" class="form-label" data-label-default="{{ __('app.username_email') }}" data-label-server="{{ __('app.f_server_user') }}" data-label-wifi="{{ __('app.f_wifi_ssid') }}" data-label-recovery_codes="{{ __('app.f_account') }}" data-label-seed_phrase="{{ __('app.f_wallet') }}" data-label-api_key="{{ __('app.f_account') }}">{{ __('app.username_email') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" maxlength="300" value="{{ old('username', $data['username'] ?? '') }}" autocomplete="off" spellcheck="false">
                </div>
            </div>

            <div class="col-md-6" data-field="password">
                <label for="password" class="form-label" data-label-default="{{ __('app.password') }}" data-label-wifi="{{ __('app.f_wifi_password') }}">{{ __('app.password') }}</label>
                @include('partials.password-input', ['name' => 'password', 'value' => old('password', $data['password'] ?? ''), 'generator' => true, 'meter' => true])
                <div class="generator-options small mt-2 d-none" id="genOptions">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <label class="d-flex align-items-center gap-2">{{ __('app.length') }} <input type="range" min="8" max="64" value="20" id="genLength" class="form-range w-auto"> <span id="genLengthVal" class="font-mono">20</span></label>
                        <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" id="genSymbols" checked> !@#</label>
                        <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" id="genNumbers" checked> 0-9</label>
                        <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" id="genAmbiguous"> {{ __('app.avoid_ambiguous') }}</label>
                    </div>
                </div>
            </div>

            <div class="col-md-8" data-field="host">
                <label for="host" class="form-label">{{ __('app.f_host') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-hdd-network"></i></span>
                    <input type="text" class="form-control font-mono" id="host" name="host" maxlength="255" value="{{ old('host', $data['host'] ?? '') }}" spellcheck="false">
                </div>
            </div>
            <div class="col-md-4" data-field="port">
                <label for="port" class="form-label">{{ __('app.f_port') }}</label>
                <input type="text" class="form-control font-mono" id="port" name="port" maxlength="10" value="{{ old('port', $data['port'] ?? '') }}" placeholder="22">
            </div>

            <div class="col-md-6" data-field="phone">
                <label for="phone" class="form-label">{{ __('app.f_phone') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                    <input type="tel" class="form-control font-mono" id="phone" name="phone" maxlength="50" value="{{ old('phone', $data['phone'] ?? '') }}" placeholder="+507 6000-0000">
                </div>
            </div>
            <div class="col-md-6" data-field="carrier">
                <label for="carrier" class="form-label">{{ __('app.f_carrier') }}</label>
                <input type="text" class="form-control" id="carrier" name="carrier" maxlength="100" value="{{ old('carrier', $data['carrier'] ?? '') }}" placeholder="{{ __('app.f_carrier_placeholder') }}">
            </div>
            <div class="col-12" data-field="used_for">
                <label for="used_for" class="form-label">{{ __('app.f_used_for') }}</label>
                <textarea class="form-control" id="used_for" name="used_for" rows="3" maxlength="2000" placeholder="{{ __('app.f_used_for_placeholder') }}">{{ old('used_for', $data['used_for'] ?? '') }}</textarea>
            </div>

            <div class="col-12" data-field="url">
                <label for="url" class="form-label">{{ __('app.f_url') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-globe2"></i></span>
                    <input type="text" class="form-control" id="url" name="url" maxlength="2000" value="{{ old('url', $data['url'] ?? '') }}" placeholder="https://" spellcheck="false">
                </div>
            </div>

            <div class="col-12" data-field="secret">
                <div class="d-flex justify-content-between align-items-end">
                    <label for="secret" class="form-label" data-label-default="{{ __('app.f_secret') }}" data-label-recovery_codes="{{ __('app.f_recovery_codes') }}" data-label-seed_phrase="{{ __('app.f_seed_phrase') }}" data-label-api_key="{{ __('app.f_api_key') }}" data-label-note="{{ __('app.f_note_content') }}">{{ __('app.f_secret') }}</label>
                    <button type="button" class="btn btn-sm btn-ghost mb-1" data-toggle-blur="#secret"><i class="bi bi-eye"></i></button>
                </div>
                <textarea class="form-control font-mono masked" id="secret" name="secret" rows="5" maxlength="20000" spellcheck="false" autocomplete="off">{{ old('secret', $data['secret'] ?? '') }}</textarea>
                <div class="form-text">{{ __('app.f_secret_help') }}</div>
            </div>

            <div class="col-12" data-field="notes">
                <label for="notes" class="form-label">{{ __('app.notes') }}</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="20000">{{ old('notes', $data['notes'] ?? '') }}</textarea>
            </div>

            <div class="col-md-4" id="rotationField">
                <label for="rotation_days" class="form-label">{{ __('app.rotation_days') }}</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="rotation_days" name="rotation_days" min="1" max="3650"
                           value="{{ old('rotation_days', $item?->rotation_days) }}" placeholder="{{ auth()->user()->rotation_days }}">
                    <span class="input-group-text">{{ __('app.days') }}</span>
                </div>
                <div class="form-text">{{ __('app.rotation_days_help', ['days' => auth()->user()->rotation_days]) }}</div>
            </div>
        </div>
    </div>

    @unless ($item)
        <p class="small text-body-secondary"><i class="bi bi-paperclip"></i> {{ __('app.attach_after_save') }}</p>
    @endunless

    <div class="d-flex gap-2 justify-content-end">
        <a href="{{ $item ? route('items.show', $item) : route('dashboard') }}" class="btn btn-ghost">{{ __('app.cancel') }}</a>
        <button class="btn btn-accent px-4"><i class="bi bi-check-lg me-1"></i>{{ __('app.save') }}</button>
    </div>
</form>
@endsection
