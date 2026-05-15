import { request } from './index'
import type { Project, ProjectDetail, PaginatedResponse, QueryParams } from '@/types'
import { mockProjects } from './mock'

export const projectsApi = {
  /**
   * 获取项目列表
   */
  async getList(params?: QueryParams): Promise<PaginatedResponse<Project>> {
    try {
      return await request.get('/projects', { params })
    } catch (error) {
      console.warn('Projects API failed, using mock data')
      let items = [...mockProjects] as Project[]

      // Filter by search keyword if provided
      if (params?.search) {
        const keyword = params.search.toLowerCase()
        items = items.filter(p =>
          p.title.toLowerCase().includes(keyword) ||
          p.description?.toLowerCase().includes(keyword) ||
          p.subtitle?.toLowerCase().includes(keyword)
        )
      }

      return {
        items: items,
        meta: {
          total: items.length,
          page: 1,
          per_page: params?.per_page || 12
        }
      }
    }
  },

  /**
   * 获取项目详情
   */
  async getDetail(id: number): Promise<ProjectDetail> {
    try {
      return await request.get(`/projects/${id}`)
    } catch (error) {
      console.warn('Project detail API failed, using mock data')
      const project = mockProjects.find(p => p.id === id) || mockProjects[0]
      return { ...project, progress: [], donations: [], related_projects: [] } as any
    }
  },

  /**
   * 获取精选项目
   */
  async getFeatured(limit = 6): Promise<{ items: Project[] }> {
    try {
      return await request.get('/projects/featured', { params: { limit } })
    } catch (error) {
      console.warn('Featured projects API failed, using mock data')
      return { items: mockProjects.slice(0, limit) as Project[] }
    }
  },
}
