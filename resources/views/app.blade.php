<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    @vite(['resources/css/app.css', 'resources/css/fish.css', 'resources/js/app.js'])
    @inertiaHead
    <link rel="icon" type="image/png" href="{{ asset('icons/icon.png') }}">
    <link rel="manifest" href="/manifest.json">

    <meta name="theme-color" content="#1976d2">
    <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
          navigator.serviceWorker.register('/service-worker.js');
        });
      }
    </script>
  </head>
  <body>
    @inertia
  </body>
</html>
