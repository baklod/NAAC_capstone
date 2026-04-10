<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="container">
            <section class="card">
                <h1>Welcome to {{ config('app.name', 'Laravel') }}</h1>
                <p>Your application styles are configured with plain CSS.</p>
            </section>
        </main>
    </body>
</html>