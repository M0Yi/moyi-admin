import { request } from './index'
import type { Project, Article, Story, QueryParams } from '@/types'

export interface SearchResult {
  projects: {
    items: Project[]
    total: number
  }
  articles: {
    items: Article[]
    total: number
  }
  stories: {
    items: Story[]
    total: number
  }
  meta: {
    current_page: number
    per_page: number
  }
}

export const searchApi = {
  /**
   * 全局搜索
   */
  search(params: QueryParams & { q: string }): Promise<SearchResult> {
    return request.get('/search', { params })
  },
}
