<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <nav class="bg-white border-b px-6 py-3 flex justify-between items-center">
        <a href="{{ route('dashboard') }}" class="font-semibold">{{ config('app.name') }}</a>
        <div class="text-sm space-x-4">
            <a href="{{ route('forms.generate') }}" class="hover:text-indigo-600">Generate with AI</a>
            <a href="{{ route('forms.import') }}" class="hover:text-indigo-600">Import</a>
        </div>
    </nav>

    <main class="py-6">
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
