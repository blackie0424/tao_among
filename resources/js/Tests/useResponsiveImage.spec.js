import { describe, it, expect } from 'vitest'
import {
  getResponsiveImageUrls,
  isResponsiveWebp,
  RESPONSIVE_BREAKPOINTS,
} from '@/composables/useResponsiveImage.js'

describe('useResponsiveImage', () => {
  describe('getResponsiveImageUrls', () => {
    it('should return responsive URLs for a valid webp image', () => {
      const url = 'https://example.com/webp/fish.webp'
      const result = getResponsiveImageUrls(url)

      expect(result).toEqual({
        desktop: 'https://example.com/webp/fish_large.webp',
        tablet: 'https://example.com/webp/fish_medium.webp',
        mobile: 'https://example.com/webp/fish_small.webp',
        original: 'https://example.com/webp/fish.webp',
      })
    })

    it('should normalize _medium.webp URL and return correct responsive URLs', () => {
      const url = 'https://example.com/webp/fish_medium.webp'
      const result = getResponsiveImageUrls(url)

      expect(result).toEqual({
        desktop: 'https://example.com/webp/fish_large.webp',
        tablet: 'https://example.com/webp/fish_medium.webp',
        mobile: 'https://example.com/webp/fish_small.webp',
        original: 'https://example.com/webp/fish.webp',
      })
    })

    it('should normalize _small.webp URL and return correct responsive URLs', () => {
      const url = 'https://example.com/webp/fish_small.webp'
      const result = getResponsiveImageUrls(url)

      expect(result).toEqual({
        desktop: 'https://example.com/webp/fish_large.webp',
        tablet: 'https://example.com/webp/fish_medium.webp',
        mobile: 'https://example.com/webp/fish_small.webp',
        original: 'https://example.com/webp/fish.webp',
      })
    })

    it('should normalize _large.webp URL and return correct responsive URLs', () => {
      const url = 'https://example.com/webp/fish_large.webp'
      const result = getResponsiveImageUrls(url)

      expect(result).toEqual({
        desktop: 'https://example.com/webp/fish_large.webp',
        tablet: 'https://example.com/webp/fish_medium.webp',
        mobile: 'https://example.com/webp/fish_small.webp',
        original: 'https://example.com/webp/fish.webp',
      })
    })

    it('should return null for non-webp images', () => {
      const jpgUrl = 'https://example.com/images/fish.jpg'
      const pngUrl = 'https://example.com/images/fish.png'

      expect(getResponsiveImageUrls(jpgUrl)).toBeNull()
      expect(getResponsiveImageUrls(pngUrl)).toBeNull()
    })

    it('should return null for local default images', () => {
      const localUrl = '/images/default.png'
      expect(getResponsiveImageUrls(localUrl)).toBeNull()
    })

    it('should return null for empty or null URL', () => {
      expect(getResponsiveImageUrls(null)).toBeNull()
      expect(getResponsiveImageUrls('')).toBeNull()
      expect(getResponsiveImageUrls(undefined)).toBeNull()
    })

    it('should handle URLs with query parameters', () => {
      const url = 'https://example.com/webp/fish.webp?v=123'
      // 由於有 query string，目前不會被正確處理
      // 這是一個已知限制，但在實際使用中不常見
      const result = getResponsiveImageUrls(url)
      expect(result).toBeNull() // 因為不符合 .webp 結尾的條件
    })

    it('should handle complex file paths', () => {
      const url = 'https://bucket.s3.amazonaws.com/prod-webp/subfolder/my-fish-image.webp'
      const result = getResponsiveImageUrls(url)

      expect(result).toEqual({
        desktop: 'https://bucket.s3.amazonaws.com/prod-webp/subfolder/my-fish-image_large.webp',
        tablet: 'https://bucket.s3.amazonaws.com/prod-webp/subfolder/my-fish-image_medium.webp',
        mobile: 'https://bucket.s3.amazonaws.com/prod-webp/subfolder/my-fish-image_small.webp',
        original: 'https://bucket.s3.amazonaws.com/prod-webp/subfolder/my-fish-image.webp',
      })
    })
  })

  describe('isResponsiveWebp', () => {
    it('should return true for webp URLs from remote storage', () => {
      expect(isResponsiveWebp('https://example.com/webp/fish.webp')).toBe(true)
      expect(isResponsiveWebp('https://s3.amazonaws.com/bucket/fish.webp')).toBe(true)
    })

    it('should return false for non-webp images', () => {
      expect(isResponsiveWebp('https://example.com/images/fish.jpg')).toBe(false)
      expect(isResponsiveWebp('https://example.com/images/fish.png')).toBe(false)
      expect(isResponsiveWebp('https://example.com/images/fish.gif')).toBe(false)
    })

    it('should return false for local default images', () => {
      expect(isResponsiveWebp('/images/default.png')).toBe(false)
      expect(isResponsiveWebp('/images/default.webp')).toBe(false)
    })

    it('should return false for empty or null values', () => {
      expect(isResponsiveWebp(null)).toBe(false)
      expect(isResponsiveWebp('')).toBe(false)
      expect(isResponsiveWebp(undefined)).toBe(false)
    })
  })

  describe('RESPONSIVE_BREAKPOINTS', () => {
    it('should define correct mobile breakpoint', () => {
      expect(RESPONSIVE_BREAKPOINTS.mobile).toBe('(max-width: 767px)')
    })

    it('should define correct tablet breakpoint', () => {
      expect(RESPONSIVE_BREAKPOINTS.tablet).toBe('(min-width: 768px) and (max-width: 1023px)')
    })

    it('should define correct desktop breakpoint', () => {
      expect(RESPONSIVE_BREAKPOINTS.desktop).toBe('(min-width: 1024px)')
    })
  })
})
