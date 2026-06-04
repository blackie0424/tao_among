import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { apiFetch } from '@/utils/apiFetch'

global.fetch = vi.fn()

function setCookie(name, value) {
  document.cookie = `${name}=${encodeURIComponent(value)}`
}

function clearCookies() {
  document.cookie.split(';').forEach((c) => {
    const key = c.trim().split('=')[0]
    document.cookie = `${key}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/`
  })
}

describe('apiFetch', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    clearCookies()
    global.fetch.mockResolvedValue({ ok: true, json: async () => ({}) })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('預設帶 Accept: application/json', async () => {
    await apiFetch('/prefix/api/test', { method: 'POST' })

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/test',
      expect.objectContaining({
        headers: expect.objectContaining({ 'Accept': 'application/json' }),
      })
    )
  })

  it('預設帶 Content-Type: application/json', async () => {
    await apiFetch('/prefix/api/test', { method: 'POST' })

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/test',
      expect.objectContaining({
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
      })
    )
  })

  it('有 XSRF-TOKEN cookie 時自動帶 X-XSRF-TOKEN header', async () => {
    setCookie('XSRF-TOKEN', 'test-csrf-token')

    await apiFetch('/prefix/api/test', { method: 'POST' })

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/test',
      expect.objectContaining({
        headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'test-csrf-token' }),
      })
    )
  })

  it('無 XSRF-TOKEN cookie 時 X-XSRF-TOKEN 為空字串', async () => {
    await apiFetch('/prefix/api/test', { method: 'POST' })

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/test',
      expect.objectContaining({
        headers: expect.objectContaining({ 'X-XSRF-TOKEN': '' }),
      })
    )
  })

  it('呼叫者自訂 header 可覆蓋預設值', async () => {
    await apiFetch('/prefix/api/test', {
      method: 'POST',
      headers: { 'Accept': 'text/plain' },
    })

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/test',
      expect.objectContaining({
        headers: expect.objectContaining({ 'Accept': 'text/plain' }),
      })
    )
  })

  it('其他 fetch options 正常傳遞', async () => {
    const body = JSON.stringify({ filename: 'test.jpg' })
    await apiFetch('/prefix/api/test', { method: 'POST', body })

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/test',
      expect.objectContaining({ method: 'POST', body })
    )
  })
})
