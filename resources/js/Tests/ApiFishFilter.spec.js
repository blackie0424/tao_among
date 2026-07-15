import { describe, it, expect, vi, afterEach } from 'vitest'
import { filterFishs } from '../api/fishApi'

describe('filterFishs', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('GET /prefix/api/fishs/filter 成功，fetch 呼叫正確 URL，json.data 為陣列', async () => {
    const mockData = [{ id: 1, name: '飛魚', image_url: 'https://cdn.example/fish/1.jpg' }]
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: mockData }),
      })
    )

    const result = await filterFishs()

    expect(fetch).toHaveBeenCalledWith('/prefix/api/fishs/filter')
    expect(Array.isArray(result)).toBe(true)
  })

  it('GET /prefix/api/fishs/filter?tribe=ivalino，URL 含 tribe 參數', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: [] }),
      })
    )

    await filterFishs({ tribe: 'ivalino' })

    const calledUrl = fetch.mock.calls[0][0]
    expect(calledUrl).toContain('tribe=ivalino')
  })

  it('網路錯誤時向上拋出例外', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network Error')))

    await expect(filterFishs()).rejects.toThrow('Network Error')
  })
})
