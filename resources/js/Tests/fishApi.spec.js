import { describe, it, expect, vi, afterEach } from 'vitest'
import { getFishCompact } from '../api/fishApi'

describe('getFishCompact', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時回傳 data 欄位', async () => {
    const mockData = { id: 1, name: '飛魚', image_url: 'https://cdn.example/fish/1.jpg' }
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: mockData }),
      })
    )

    const result = await getFishCompact(1)

    expect(fetch).toHaveBeenCalledWith('/prefix/api/fish/1/compact')
    expect(result).toEqual(mockData)
  })

  it('HTTP 回應非 ok 時回傳 null', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 404,
      })
    )

    const result = await getFishCompact(99)

    expect(result).toBeNull()
  })

  it('data 為 undefined 時回傳 null', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: undefined }),
      })
    )

    const result = await getFishCompact(2)

    expect(result).toBeNull()
  })

  it('fetch 拋出例外時向上傳播', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network Error')))

    await expect(getFishCompact(1)).rejects.toThrow('Network Error')
  })
})
