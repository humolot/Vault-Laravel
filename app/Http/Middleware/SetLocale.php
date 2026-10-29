<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['pt_BR' => 'Português (BR)', 'es' => 'Español', 'en' => 'English'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');
        if (! $locale && $request->user()) {
            $locale = $request->user()->locale;
        }
        if (! $locale) {
            $pref = $request->getPreferredLanguage(['pt_BR', 'pt', 'es', 'en']);
            $locale = match (true) {
                str_starts_with((string) $pref, 'pt') => 'pt_BR',
                str_starts_with((string) $pref, 'es') => 'es',
                str_starts_with((string) $pref, 'en') => 'en',
                default => config('app.locale', 'pt_BR'),
            };
        }
        if (! array_key_exists($locale, self::SUPPORTED)) {
            $locale = 'pt_BR';
        }
        app()->setLocale($locale);
        return $next($request);
    }
}
