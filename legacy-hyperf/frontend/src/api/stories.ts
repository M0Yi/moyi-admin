import { request } from './index'
import type { Story, PaginatedResponse, QueryParams } from '@/types'
import { mockStories } from './mock'

export const storiesApi = {
  /**
   * 获取故事列表
   */
  async getList(params?: QueryParams): Promise<PaginatedResponse<Story>> {
    try {
      return await request.get('/stories', { params })
    } catch (error) {
      console.warn('Stories API failed, using mock data')
      let items = mockStories as Story[]

      // Pagination
      const page = params?.page || 1
      const page_size = params?.page_size || 12
      const start = (page - 1) * page_size
      const paginatedItems = items.slice(start, start + page_size)

      return {
        items: paginatedItems,
        meta: {
          total: items.length,
          current_page: page,
          per_page: page_size,
          last_page: Math.ceil(items.length / page_size)
        }
      }
    }
  },

  /**
   * 获取故事详情
   */
  async getDetail(id: number): Promise<Story> {
    try {
      return await request.get(`/stories/${id}`)
    } catch (error) {
      console.warn('Story detail API failed, using mock data')
      const story = mockStories.find(s => s.id === id) || mockStories[0]
      return { ...story, content: story.content || '暂无内容' } as any
    }
  },
}
