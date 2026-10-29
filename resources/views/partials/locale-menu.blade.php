@php $locales = \App\Http\Middleware\SetLocale::SUPPORTED; @endphp
<div class="dropdown {{ $class ?? '' }}">
    <button class="btn btn-sm btn-ghost dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-translate"></i> <span class="text-uppercase small">{{ str_replace('_', '-', app()->getLocale()) }}</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach ($locales as $code => $label)
            <li><a class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}" href="{{ route('locale', $code) }}">{{ $label }}</a></li>
        @endforeach
    </ul>
</div>
