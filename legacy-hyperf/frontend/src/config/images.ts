/**
 * 图片配置文件
 * 统一管理系统中使用的默认图片
 */

export const DEFAULT_IMAGES = {
  // 默认封面图片
  cover: 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png',

  // 默认头像
  avatar: 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png',

  // 默认项目封面
  project: 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png',

  // 默认文章封面
  article: 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png',
} as const

/**
 * 获取默认封面图片URL
 */
export function getDefaultCoverImage(): string {
  return DEFAULT_IMAGES.cover
}

/**
 * 获取图片URL，如果为空或无效则返回默认图片
 */
export function getImageUrl(imageUrl: string | null | undefined, type: keyof typeof DEFAULT_IMAGES = 'cover'): string {
  if (!imageUrl || imageUrl.trim() === '') {
    return DEFAULT_IMAGES[type]
  }
  return imageUrl
}
