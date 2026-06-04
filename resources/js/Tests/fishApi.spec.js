import { describe, it, expect, vi, afterEach } from 'vitest'
import { getFishCompact, getFishLatestAt } from '../api/fishApi'

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

describe('getFishLatestAt', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時回傳 latest_at 數值', async () => {
    const timestamp = 1748998800000
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: { latest_at: timestamp } }),
      })
    )

    const result = await getFishLatestAt()

    expect(fetch).toHaveBeenCalledWith('/prefix/api/fishs/latest-at')
    expect(result).toBe(timestamp)
  })

  it('HTTP 回應非 ok 時回傳 null', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: false, status: 500 })
    )

    const result = await getFishLatestAt()

    expect(result).toBeNull()
  })

  it('latest_at 為 null 時回傳 null', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: { latest_at: null } }),
      })
    )

    const result = await getFishLatestAt()

    expect(result).toBeNull()
  })

  it('fetch 拋出例外時回傳 null（降級處理）', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network Error')))

    const result = await getFishLatestAt()

    expect(result).toBeNull()
  })
})
