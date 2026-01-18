/**
 * 響應式圖片 URL 生成工具
 *
 * 後端會自動將上傳的圖片產生三種尺寸：
 * - 檔名_large.webp：桌機用
 * - 檔名_medium.webp：平板用
 * - 檔名_small.webp：手機用
 * - 檔名.webp：原始版本（作為 fallback）
 *
 * 此工具根據傳入的 webp URL 產生對應的各尺寸 URL
 */

/**
 * 從 webp URL 產生響應式圖片 URL 集合
 * @param {string} webpUrl - 原始 webp 圖片 URL (例如: https://example.com/webp/fish.webp)
 * @returns {{ desktop: string, tablet: string, mobile: string } | null}
 */
export function getResponsiveImageUrls(webpUrl) {
  if (!webpUrl) return null

  // 只處理 webp 格式的圖片
  if (!webpUrl.toLowerCase().endsWith('.webp')) {
    return null
  }

  // 若是本地預設圖片則不處理
  if (webpUrl.startsWith('/images/')) {
    return null
  }

  // 檢查是否已經是 _large, _medium 或 _small 版本，若是則取得基礎 URL
  const baseUrl = webpUrl
    .replace(/_large\.webp$/i, '.webp')
    .replace(/_medium\.webp$/i, '.webp')
    .replace(/_small\.webp$/i, '.webp')

  // 產生各尺寸的 URL
  const desktopUrl = baseUrl.replace(/\.webp$/i, '_large.webp')
  const tabletUrl = baseUrl.replace(/\.webp$/i, '_medium.webp')
  const mobileUrl = baseUrl.replace(/\.webp$/i, '_small.webp')
  const originalUrl = baseUrl // 原始版本作為 fallback

  return {
    desktop: desktopUrl,
    tablet: tabletUrl,
    mobile: mobileUrl,
    original: originalUrl,
  }
}

/**
 * 判斷 URL 是否為可產生響應式版本的 webp 圖片
 * @param {string} url - 圖片 URL
 * @returns {boolean}
 */
export function isResponsiveWebp(url) {
  if (!url) return false
  if (!url.toLowerCase().endsWith('.webp')) return false
  if (url.startsWith('/images/')) return false
  return true
}

/**
 * 響應式圖片的斷點設定（與 Tailwind 一致）
 */
export const RESPONSIVE_BREAKPOINTS = {
  // 手機：< 768px
  mobile: '(max-width: 767px)',
  // 平板：768px - 1023px
  tablet: '(min-width: 768px) and (max-width: 1023px)',
  // 桌機：>= 1024px
  desktop: '(min-width: 1024px)',
}
