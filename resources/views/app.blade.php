<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite(['resources/css/app.css', 'resources/css/fish.css', 'resources/js/app.js'])
    @inertiaHead
    <link rel="icon" type="image/png" href="{{ asset('icons/icon.png') }}">
  </head>
  <body>
    @inertia
  </body>
</html>
