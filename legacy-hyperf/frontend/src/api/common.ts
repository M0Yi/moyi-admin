import { request } from './index'
import type { Slide, NavigationItem } from '@/types'
import { mockSlides, mockNavigation } from './mock'

export const commonApi = {
  /**
   * 获取轮播图
   */
  async getSlides(): Promise<{ items: Slide[] }> {
    try {
      return await request.get('/slides')
    } catch (error) {
      console.warn('Slides API failed, using mock data')
      return { items: mockSlides as Slide[] }
    }
  },

  /**
   * 获取导航菜单
   */
  async getNavigation(): Promise<{ items: NavigationItem[] }> {
    try {
      const result = await request.get('/navigation')
      // 如果后端返回的数据太少（少于3个导航项），使用 mock 数据
      if (!result.items || result.items.length < 3) {
        console.warn('Navigation data incomplete, using mock data')
        return { items: mockNavigation as NavigationItem[] }
      }
      return result
    } catch (error) {
      console.warn('Navigation API failed, using mock data')
      return { items: mockNavigation as NavigationItem[] }
    }
  },
}
