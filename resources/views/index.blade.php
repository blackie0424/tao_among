<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Tao Among</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
           
        @endif

        @vite(['resources/css/index.css'])
    </head>
    <body class="font-sans antialiased">
        <div class="container mx-auto">
            @include('header')
            <div class="main"> 
                <picture>
                    <source media="(min-width: 1025px)" srcset="{{secure_asset('/images/header-m.png')}}">
                    <source media="(min-width: 481px)" srcset="{{secure_asset('/images/header-s.png')}}">
                    <img src="{{secure_asset('/images/header-l.png')}}" class="responsive-img" loading="lazy" alt="Header Image">
                </picture>
                <div class="header-content">
                    <h1><a href="/list">nivasilan ko a among</a></h1>
                </div>
            </div>
            
            <footer>Copyright © 2025 Chungyueh</footer>
        </div>
    </body>
</html>
