import { request } from './index'

export interface PartnerCategory {
  id: number
  name: string
  slug: string
  description: string
  sortOrder: number
  partners: Partner[]
}

export interface Partner {
  id: number
  name: string
  logoUrl: string
  websiteUrl: string
  description: string
  sortOrder: number
}

export const partnersApi = {
  /**
   * 获取合作伙伴列表（按分类分组）
   */
  async getList(): Promise<PartnerCategory[]> {
    return await request.get('/partners')
  }
}
