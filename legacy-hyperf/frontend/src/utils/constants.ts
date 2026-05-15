/**
 * 应用常量
 */

// API 基础URL
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1'

// 分页配置
export const PAGINATION = {
  DEFAULT_PAGE_SIZE: 12,
  PAGE_SIZES: [12, 24, 48, 96],
}

// 项目类型
export const PROJECT_TYPES = [
  { value: 'medical', label: '医疗援助' },
  { value: 'health', label: '健康关怀' },
  { value: 'emergency', label: '应急救援' },
  { value: 'undirected', label: '非定向' },
]

// 项目状态
export const PROJECT_STATUS = [
  { value: 'active', label: '进行中' },
  { value: 'completed', label: '已完成' },
  { value: 'paused', label: '已暂停' },
]

// 捐赠快捷金额
export const DONATION_AMOUNTS = [50, 100, 200, 500, 1000]

// 支付方式
export const DONATION_TYPES = [
  { value: 'wechat', label: '微信支付', icon: 'Wechat' },
  { value: 'alipay', label: '支付宝', icon: 'Alipay' },
  { value: 'bank', label: '银行转账', icon: 'Bank' },
]

// 社交媒体链接
export const SOCIAL_LINKS = {
  wechat: '',
  weibo: '',
  douyin: '',
}
