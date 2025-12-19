<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'FreePanel') }}</title>

    @php
        $manifestPath = public_path('build/.vite/manifest.json');
        $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
        $entry = $manifest['src/index.tsx'] ?? $manifest['src/main.tsx'] ?? null;
    @endphp

    @if($entry)
        @if(isset($entry['css']))
            @foreach($entry['css'] as $css)
                <link rel="stylesheet" href="/build/{{ $css }}">
            @endforeach
        @endif
    @endif
</head>
<body>
    <div id="root"></div>

    @if($entry)
        <script type="module" src="/build/{{ $entry['file'] }}"></script>
    @endif
</body>
</html>
