import { describe, it, expect, vi, afterEach } from 'vitest'
import { getFishNotesList } from '../api/fishApi'

describe('getFishNotesList', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('成功時 fetch 呼叫正確 URL，json.data 為陣列', async () => {
    const mockData = [{ id: 1, fish_id: 1, note: '飛魚筆記', note_type: 'observation' }]
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: mockData }),
      })
    )

    const result = await getFishNotesList(1)

    expect(fetch).toHaveBeenCalledWith('/prefix/api/fish/1/notes')
    expect(Array.isArray(result)).toBe(true)
    expect(result[0]).toMatchObject({ id: expect.any(Number), note: expect.any(String) })
  })

  it('HTTP 非 ok 時回傳 null', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 404 }))

    const result = await getFishNotesList(99999)

    expect(result).toBeNull()
  })

  it('網路錯誤時向上拋出例外', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network Error')))

    await expect(getFishNotesList(1)).rejects.toThrow('Network Error')
  })
})
