import { request } from './index'
import type { DonationRecord, DonationDisclosureStats, PaginatedResponse, QueryParams } from '@/types'

export interface DonationFormData {
  project_id?: number
  donor_name: string
  donor_phone?: string
  donor_email?: string
  amount: number
  donation_type: 'wechat' | 'alipay' | 'bank'
  is_anonymous?: boolean
  message?: string
}

export interface DonationResponse {
  donation_id: number
  order_no: string
  amount: number
  payment_url?: string
  qrcode_url?: string
}

export const donationsApi = {
  /**
   * 提交捐赠
   */
  submit(data: DonationFormData): Promise<DonationResponse> {
    return request.post('/donations', data)
  },

  /**
   * 获取捐赠披露列表
   */
  getDisclosure(params?: QueryParams & {
    project_id?: number
    start_date?: string
    end_date?: string
  }): Promise<PaginatedResponse<DonationRecord> & { stats: DonationDisclosureStats }> {
    return request.get('/donations/disclosure', { params })
  },
}
