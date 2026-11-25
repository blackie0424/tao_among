import defaultTheme from 'tailwindcss/defaultTheme'

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
        serif: ['"Noto Serif TC"', '"Source Han Serif TC"', '"Songti TC"', 'serif'],
      },
      spacing: {
        128: '32rem', // 512px
        160: '40rem', // 640px
        192: '48rem', // 768px
      },
      // 長者可用性字級規格 (C1)
      fontSize: {
        'elder-aux': ['0.9375rem', { lineHeight: '1.5rem' }], // 15px 輔助文字
        'elder-body': ['1.125rem', { lineHeight: '1.75rem' }], // 18px 內文
        'elder-name': ['1.375rem', { lineHeight: '2rem' }], // 22px 魚名/主要操作
        'elder-title': ['1.75rem', { lineHeight: '2.25rem' }], // 28px 頁標題
      },
      // 長者可用性顏色規格 (C3)
      colors: {
        elder: {
          text: '#2a2622',
          subtext: '#6b6358',
        },
        // 博物誌配色（見 design-spec.md）
        paper: { DEFAULT: '#f4efe6', raised: '#faf7f0' },
        rule: { DEFAULT: '#d9d0c0', strong: '#cfc5b3' },
        brand: { DEFAULT: '#2f5d62', dark: '#244a4e' },
        // 魚島配色（海島遊戲感，見 design-spec.md「魚島」）
        island: {
          sea: '#bfe6f2',
          land: '#8fd19e',
          'land-line': '#3d8b5a',
          ink: '#12394a',
          muted: '#4a6b78',
          line: '#cfe3ea',
          accent: '#c2410c',
        },
      },
      borderRadius: {
        island: '18px', // 魚島卡片、按鈕、選項
      },
      // 長者可用性觸控目標 (C2)
      minHeight: {
        'touch-primary': '3.5rem', // 56px 主要按鈕/清單列
        'touch-secondary': '3rem', // 48px 次要圖示鈕
      },
      minWidth: {
        'touch-primary': '3.5rem',
        'touch-secondary': '3rem',
      },
    },
  },
  plugins: [],
}
