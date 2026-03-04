<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Git Dash — Login</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen flex items-center justify-center font-[Instrument_Sans,sans-serif]">

    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-semibold">Git Dash</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Enter your password to continue</p>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm">
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Password
                    </label>
                    <input type="password" id="password" name="password" autofocus
                           class="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500
                                  {{ $errors->has('password') ? 'border-red-400 dark:border-red-500' : 'border-gray-300 dark:border-gray-700' }}
                                  bg-white dark:bg-gray-800">
                    @error('password')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full rounded-md bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-medium transition-colors">
                    Sign in
                </button>
            </form>
        </div>
    </div>

</body>
</html>
