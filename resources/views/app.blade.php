<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite(['resources/css/app.css', 'resources/css/fish.css', 'resources/js/app.js'])
    @inertiaHead
    <link rel="icon" type="image/png" href="{{ asset('icons/icon.png') }}">
    <link rel="manifest" href="/manifest.json">

    <meta name="theme-color" content="#1976d2">
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
          navigator.serviceWorker.register('/service-worker.js').then(function(registration) {
            // 檢查更新
            registration.update();
            
            // 監聽更新
            registration.addEventListener('updatefound', function() {
              const newWorker = registration.installing;
              console.log('[App] Service Worker 更新中...');
              
              newWorker.addEventListener('statechange', function() {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                  console.log('[App] 新版 Service Worker 已安裝，準備啟用');
                  // 強制啟用新版本
                  newWorker.postMessage({ type: 'SKIP_WAITING' });
                  // 重新載入頁面以使用新版 SW
                  window.location.reload();
                }
              });
            });
          }).catch(function(err) {
            console.error('[App] Service Worker 註冊失敗:', err);
          });
          
          // 監聽 controller 變更（新 SW 已啟用）
          navigator.serviceWorker.addEventListener('controllerchange', function() {
            console.log('[App] Service Worker 控制器已更新');
          });
        });
      }
    </script>
  </head>
  <body>
    @inertia
  </body>
</html>
