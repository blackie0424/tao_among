import { describe, it, expect, vi, afterEach } from 'vitest'
import { createFishNote } from '../api/fishApi'

describe('createFishNote', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時以正確 body 呼叫 POST /prefix/api/fish/{id}/note', async () => {
    const noteData = { note: '這是一條飛魚', note_type: 'observation', locate: 'yayo' }
    const mockResponse = { message: 'note created', data: { id: 1, fish_id: 1, ...noteData } }
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve(mockResponse),
      })
    )

    const result = await createFishNote(1, noteData)

    expect(fetch).toHaveBeenCalledWith(
      '/prefix/api/fish/1/note',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(noteData),
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

    await expect(createFishNote(1, { note: '筆記' })).rejects.toThrow()
  })

  it('網路錯誤時向上拋出例外', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network Error')))

    await expect(createFishNote(1, { note: '筆記' })).rejects.toThrow('Network Error')
  })
})
