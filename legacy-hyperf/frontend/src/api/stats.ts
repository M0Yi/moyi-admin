import { request } from './index'
import type { StatsOverview, RealtimeDonation } from '@/types'
import { mockStats } from './mock'

export const statsApi = {
  /**
   * 获取首页统计数据
   */
  async getOverview(): Promise<StatsOverview> {
    try {
      return await request.get('/stats/overview')
    } catch (error) {
      console.warn('Stats API failed, using mock data:', error)
      return mockStats as StatsOverview
    }
  },

  /**
   * 获取实时捐赠动态
   */
  async getRealtime(): Promise<{ recent_donations: RealtimeDonation[] }> {
    try {
      return await request.get('/stats/realtime')
    } catch (error) {
      console.warn('Realtime API failed, using mock data')
      return { recent_donations: [] }
    }
  },
}
