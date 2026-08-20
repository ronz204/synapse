<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- $title arrives as a translation key, not a finished string — same
     reason as x-siga.topbar (#[Layout] only accepts constant expressions,
     so __() can't run at the call site). Must be translated here too, or
     the browser tab shows the raw English key while the visible header
     shows the translated title. --}}
<title>
    {{ filled($title ?? null) ? __($title).' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
