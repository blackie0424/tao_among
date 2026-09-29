<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>權限不足</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f3f4f6;
            color: #111827;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        main {
            width: 100%;
            max-width: 640px;
            padding: 40px 28px;
            border: 2px solid #d1d5db;
            border-radius: 16px;
            background: #ffffff;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        h1 {
            margin: 0 0 20px;
            font-size: 2rem;
            line-height: 1.3;
        }
        p {
            margin: 0 0 12px;
            font-size: 1.25rem;
            line-height: 1.7;
        }
        a {
            min-height: 56px;
            margin-top: 24px;
            padding: 14px 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #1d4ed8;
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 700;
            text-decoration: none;
        }
        a:hover { background: #1e40af; }
        a:focus-visible { outline: 4px solid #f59e0b; outline-offset: 4px; }
    </style>
</head>
<body>
    <main>
        <h1>權限不足</h1>
        <p>你目前的帳號權限不足，無法瀏覽此頁面。</p>
        <p>如需開通權限，請聯繫管理者。</p>
        <a href="/">回首頁</a>
    </main>
</body>
</html>
