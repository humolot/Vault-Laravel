@php $labels = ['password' => __('auth.step_password'), 'phrase' => __('auth.step_phrase'), 'totp' => __('auth.step_2fa')] + (!empty($passkey) ? ['passkey' => __('auth.step_passkey')] : []); @endphp
@php $keys = array_keys($labels); @endphp
@php $idx = array_search($current, $keys, true); @endphp
<ol class="steps">
    @foreach ($labels as $key => $label)
        @php $i = $loop->index; @endphp
        <li class="{{ $i < $idx ? 'done' : ($i === $idx ? 'current' : '') }}">
            <span class="dot">@if ($i < $idx)<i class="bi bi-check-lg"></i>@else{{ $i + 1 }}@endif</span>
            <span class="label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
