<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fish Details</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS 變數定義 */
        :root {
            --bg-primary: #f3f4f6; /* bg-gray-100 */
            --bg-section: #ffffff; /* 圖片區塊白色背景 */
            --bg-name: #eff6ff; /* bg-blue-50 */
            --bg-buttons: #e5e7eb; /* bg-gray-200 */
            --bg-note: #f5f5dc; /* bg-beige-100 */
            --text-primary: #1f2937; /* text-gray-800 */
            --text-secondary: #4b5563; /* text-gray-600 */
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* 黑夜模式 */
        .dark {
            --bg-primary: #1f2937; /* bg-gray-800 */
            --bg-section: #374151; /* 圖片區塊深灰 */
            --bg-name: #1e3a8a; /* bg-blue-900 */
            --bg-buttons: #4b5563; /* bg-gray-600 */
            --bg-note: #4a4532; /* 深米色 */
            --text-primary: #e5e7eb; /* text-gray-200 */
            --text-secondary: #d1d5db; /* text-gray-300 */
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }

        /* 應用變數 */
        body {
            background-color: var(--bg-primary);
        }

        .bg-beige-100 {
            background-color: var(--bg-note);
        }

        .show_image {
            background-color: var(--bg-section);
        }

        .section-name {
            background-color: var(--bg-name);
        }

        .section-buttons {
            background-color: var(--bg-buttons);
        }

        .text-primary {
            color: var(--text-primary);
        }

        .text-secondary {
            color: var(--text-secondary);
        }

        .shadow-custom {
            box-shadow: var(--shadow);
        }

        /* 圖片響應式調整 */
        .show_image img {
            max-width: 100%;
            height: auto;
        }

        /* 根據螢幕大小調整圖片容器 */
        @media (max-width: 430px) { /* iPhone 16 Plus */
            .show_image {
                max-width: 380px;
            }
        }

        @media (min-width: 431px) and (max-width: 1024px) { /* iPad Pro */
            .show_image {
                max-width: 800px;
            }
        }

        @media (min-width: 1025px) { /* MacBook Pro */
            .show_image {
                max-width: 720px;
            }
        }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="container mx-auto py-6">
        <div class="main flex flex-col items-center">
        <button id="theme-toggle" class="fixed top-4 right-4 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded">
            版面色調切換
        </button>
        <a href="/" class="fixed top-4 left-4 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200">
            nivasilan ko a among
        </a>
            <!-- 圖片區塊 -->
            <div class="show_image w-full max-w-3xl mx-auto mb-6 p-4 rounded-lg shadow-custom">
                <img src="{{$fish->image}}" alt="{{$fish->name}}" loading="lazy" class="w-full h-auto rounded-lg object-contain">
            </div>

            <!-- 魚類名稱 -->
            <div class="section section-name w-full max-w-md text-center p-4 rounded-lg shadow-custom mb-4">
                <div class="text text-xl text-secondary">ngaran no among</div>
                <div class="section-title text-2xl font-bold text-primary mb-2">{{$fish->name}}</div>
            </div>

            <!-- 按鈕區塊 -->
            <div class="section-buttons flex space-x-4 my-4 p-4 rounded-lg">
                <a href="?locate=iraraley" class="locate-filter px-6 py-2 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full hover:bg-blue-200 dark:hover:bg-blue-700 {{ request()->query('locate') === 'iraraley' ? 'active bg-yellow-500 dark:bg-yellow-600' : '' }}">Iraraley</a>
                <a href="?locate=iranmailek" class="locate-filter px-6 py-2 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full hover:bg-blue-200 dark:hover:bg-blue-700 {{ request()->query('locate') === 'iranmailek' ? 'active bg-yellow-500 dark:bg-yellow-600' : '' }}">Iranmailek</a>
                <a href="?locate=ivalino" class="locate-filter px-6 py-2 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full hover:bg-blue-200 dark:hover:bg-blue-700 {{ request()->query('locate') === 'ivalino' ? 'active bg-yellow-500 dark:bg-yellow-600' : '' }}">Ivalino</a>
                <a href="?locate=imorod" class="locate-filter px-6 py-2 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full hover:bg-blue-200 dark:hover:bg-blue-700 {{ request()->query('locate') === 'imorod' ? 'active bg-yellow-500 dark:bg-yellow-600' : '' }}">Imorod</a>
                <a href="?locate=iratay" class="locate-filter px-6 py-2 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full hover:bg-blue-200 dark:hover:bg-blue-700 {{ request()->query('locate') === 'iratay' ? 'active bg-yellow-500 dark:bg-yellow-600' : '' }}">Iratay | Iratey</a>
                <a href="?locate=yayo" class="locate-filter px-6 py-2 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full hover:bg-blue-200 dark:hover:bg-blue-700 {{ request()->query('locate') === 'yayo' ? 'active bg-yellow-500 dark:bg-yellow-600' : '' }}">Yayo</a>
            </div>

            <!-- 筆記區塊 -->
            @if($fish->notes)
                @foreach($fish->notes as $note)
                    <div class="section w-full max-w-md p-4 bg-beige-100 rounded-lg shadow-custom mb-4">
                        <div class="section-title text-xl font-semibold text-primary mb-2">{{$note->note_type}}</div>
                        <div class="text text-secondary">{{$note->note}}</div>
                    </div>
                @endforeach
            @endif
        </div>
        <footer class="text-center text-secondary mt-8">Copyright © 2025 Chungyueh</footer>
    </div>

    <!-- JavaScript 切換黑夜模式 -->
    <script>
        function toggleDarkMode() {
            const now = new Date();
            const hour = now.getHours();
            // 白天：6:00 AM - 6:00 PM，否則為黑夜
            const isDayTime = hour >= 6 && hour < 18;
            document.documentElement.classList.toggle('dark', !isDayTime);
        }

        // 初次載入時檢查
        toggleDarkMode();

        // 可選：每分鐘檢查一次（如果需要動態更新）
        setInterval(toggleDarkMode, 60000);
    </script>
</body>

<script>
    const toggleButton = document.getElementById('theme-toggle');
    toggleButton.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    });

    // 載入時檢查使用者偏好
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        toggleDarkMode();
    }
</script>
</html>