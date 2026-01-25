<!DOCTYPE html>
<html lang="{{ config('app.locale', 'en') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }}</title>
    <link rel="stylesheet" href="{{ $asset('css/app.css') }}">
</head>
<body>
    <main>
        {!! $content !!}
    </main>

    <footer>
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'VelvetCMS') }}. Powered by VelvetCMS.</p>
    </footer>
</body>
</html>
