/**
 * 格式化金额（添加千分位）
 */
export function formatAmount(amount: number): string {
  return new Intl.NumberFormat('zh-CN').format(amount)
}

/**
 * 格式化日期
 */
export function formatDate(date: string | Date, format = 'YYYY-MM-DD'): string {
  const d = typeof date === 'string' ? new Date(date) : date

  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')

  return format
    .replace('YYYY', String(year))
    .replace('MM', month)
    .replace('DD', day)
}

/**
 * 格式化相对时间
 */
export function formatRelativeTime(date: string | Date): string {
  const d = typeof date === 'string' ? new Date(date) : date
  const now = new Date()
  const diff = now.getTime() - d.getTime()

  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (seconds < 60) {
    return '刚刚'
  } else if (minutes < 60) {
    return `${minutes}分钟前`
  } else if (hours < 24) {
    return `${hours}小时前`
  } else if (days < 7) {
    return `${days}天前`
  } else {
    return formatDate(d)
  }
}

/**
 * 截断文本
 */
export function truncateText(text: string, maxLength: number): string {
  if (text.length <= maxLength) {
    return text
  }
  return text.substring(0, maxLength) + '...'
}

/**
 * 获取项目类型标签
 */
export function getProjectTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    medical: '医疗援助',
    health: '健康关怀',
    emergency: '应急救援',
    undirected: '非定向'
  }
  return labels[type] || type
}

/**
 * 获取项目状态标签
 */
export function getProjectStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    active: '进行中',
    completed: '已完成',
    paused: '已暂停'
  }
  return labels[status] || status
}

/**
 * 获取项目状态颜色
 */
export function getProjectStatusColor(status: string): string {
  const colors: Record<string, string> = {
    active: 'success',
    completed: 'info',
    paused: 'warning'
  }
  return colors[status] || 'default'
}
