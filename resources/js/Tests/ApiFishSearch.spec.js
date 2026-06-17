import { describe, it, expect, vi, afterEach } from 'vitest'
import { searchFishs } from '../api/fishApi'

describe('searchFishs', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('GET /prefix/api/fishs/search?q=飛 成功，fetch 呼叫正確 URL，json.data 為陣列', async () => {
    const mockData = [{ id: 1, name: '飛魚', image_url: 'https://cdn.example/fish/1.jpg' }]
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: mockData }),
      })
    )

    const result = await searchFishs('飛')

    expect(fetch).toHaveBeenCalledWith('/prefix/api/fishs/search?q=%E9%A3%9B')
    expect(Array.isArray(result)).toBe(true)
  })

  it('GET /prefix/api/fishs/search?q= 帶查詢字串，URL 含 q 參數', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: [] }),
      })
    )

    await searchFishs('')

    const calledUrl = fetch.mock.calls[0][0]
    expect(calledUrl).toContain('q=')
  })

  it('網路錯誤時向上拋出例外', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network Error')))

    await expect(searchFishs('飛')).rejects.toThrow('Network Error')
  })
})
