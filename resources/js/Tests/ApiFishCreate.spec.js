import { describe, it, expect, vi, afterEach } from 'vitest'
import { createFish } from '../api/fishApi'

describe('createFish', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時以正確 body 呼叫 POST /prefix/api/fish，回傳 response json', async () => {
    const fishData = { name: '飛魚', image: 'fish.jpg' }
    const mockResponse = { message: 'fish created successfully', data: { id: 1, ...fishData } }
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      })
    )

    const result = await createFish(fishData)

    expect(fetch).toHaveBeenCalledWith(
      '/prefix/api/fish',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(fishData),
      })
    )
    expect(result).toEqual(mockResponse)
  })

  it('HTTP 非 ok 時拋出例外', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
      })
    )

    await expect(createFish({ name: '飛魚', image: 'fish.jpg' })).rejects.toThrow()
  })

  it('網路錯誤時向上拋出例外', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network Error')))

    await expect(createFish({ name: '飛魚', image: 'fish.jpg' })).rejects.toThrow('Network Error')
  })
})
