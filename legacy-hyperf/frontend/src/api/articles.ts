import { request } from './index'
import type { Article, ArticleDetail, Category, PaginatedResponse, QueryParams } from '@/types'
import { mockArticles, mockCategories } from './mock'

export const articlesApi = {
  /**
   * 获取文章列表
   */
  async getList(params?: QueryParams): Promise<PaginatedResponse<Article>> {
    try {
      return await request.get('/articles', { params })
    } catch (error) {
      console.warn('Articles API failed, using mock data')
      let items = [...mockArticles] as Article[]

      // Filter by search keyword if provided
      if (params?.search) {
        const keyword = params.search.toLowerCase()
        items = items.filter(a =>
          a.title.toLowerCase().includes(keyword) ||
          a.summary?.toLowerCase().includes(keyword) ||
          a.content?.toLowerCase().includes(keyword)
        )
      }

      // Filter by category_id if provided
      if (params?.category_id) {
        items = items.filter(a => a.category_id === params.category_id)
      }
      // Filter by category slug if provided
      else if (params?.category) {
        items = items.filter(a => a.category_slug === params.category)
      }

      // Pagination
      const page = params?.page || 1
      const page_size = params?.per_page || params?.page_size || 12
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
   * 获取文章详情
   */
  async getDetail(id: number): Promise<ArticleDetail> {
    try {
      return await request.get(`/articles/${id}`)
    } catch (error) {
      console.warn('Article detail API failed, using mock data')
      const article = mockArticles.find(a => a.id === id) || mockArticles[0]
      return { ...article, content: article.content || '暂无内容' } as any
    }
  },

  /**
   * 获取分类列表
   */
  async getCategories(type?: string): Promise<{ items: Category[] }> {
    try {
      return await request.get('/categories', { params: { type } })
    } catch (error) {
      console.warn('Categories API failed, using mock data')
      return { items: mockCategories as Category[] }
    }
  },
}

// 同时导出为默认导出，以支持不同的导入方式
export default articlesApi
