{{--
    Everything a browser needs to treat this site as an installable app.

    The system stays one responsive web application. This only lets Android,
    Chrome on desktop and iOS Safari add it to the home screen and open it
    without browser chrome, which is how most farmers will use it.
--}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

<meta name="theme-color" content="#0d3d1b">
<meta name="application-name" content="Tanza FITS">
<meta name="mobile-web-app-capable" content="yes">

{{-- iOS does not read the manifest, so it needs these separately --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Tanza FITS">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
