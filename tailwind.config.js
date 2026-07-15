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
      },
      spacing: {
        128: '32rem', // 512px
        160: '40rem', // 640px
        192: '48rem', // 768px
      },
      // 長者可用性字級規格 (C1)
      fontSize: {
        'elder-aux':  ['0.9375rem', { lineHeight: '1.5rem' }],   // 15px 輔助文字
        'elder-body': ['1.125rem',  { lineHeight: '1.75rem' }],  // 18px 內文
        'elder-name': ['1.375rem',  { lineHeight: '2rem' }],     // 22px 魚名/主要操作
        'elder-title':['1.75rem',   { lineHeight: '2.25rem' }],  // 28px 頁標題
      },
      // 長者可用性顏色規格 (C3)
      colors: {
        elder: {
          text:    '#16181d',
          subtext: '#3f454c',
        },
      },
      // 長者可用性觸控目標 (C2)
      minHeight: {
        'touch-primary':   '3.5rem',  // 56px 主要按鈕/清單列
        'touch-secondary': '3rem',    // 48px 次要圖示鈕
      },
      minWidth: {
        'touch-primary':   '3.5rem',
        'touch-secondary': '3rem',
      },
    },
  },
  plugins: [],
}
