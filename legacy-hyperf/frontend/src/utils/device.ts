/**
 * 设备检测工具
 */

export interface DeviceInfo {
  isMobile: boolean
  isTablet: boolean
  isDesktop: boolean
  userAgent: string
}

/**
 * 检测是否为移动设备
 */
export function isMobileDevice(): boolean {
  const userAgent = navigator.userAgent

  // 移动设备正则表达式
  const mobileRegex = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS/i

  return mobileRegex.test(userAgent)
}

/**
 * 检测是否为平板设备
 */
export function isTabletDevice(): boolean {
  const userAgent = navigator.userAgent

  // 平板设备正则表达式
  const tabletRegex = /iPad|Android(?!.*Mobile)|Tablet|Kindle/i

  return tabletRegex.test(userAgent)
}

/**
 * 检测是否为桌面设备
 */
export function isDesktopDevice(): boolean {
  return !isMobileDevice() && !isTabletDevice()
}

/**
 * 获取设备信息
 */
export function getDeviceInfo(): DeviceInfo {
  const userAgent = navigator.userAgent

  return {
    isMobile: isMobileDevice(),
    isTablet: isTabletDevice(),
    isDesktop: isDesktopDevice(),
    userAgent,
  }
}

/**
 * 根据设备类型返回相应的路由
 */
export function getDeviceRoute(desktopRoute: string, mobileRoute: string): string {
  return isMobileDevice() ? mobileRoute : desktopRoute
}

/**
 * 检测屏幕宽度是否为移动端尺寸
 */
export function isMobileWidth(): boolean {
  return window.innerWidth <= 768
}

/**
 * 监听屏幕尺寸变化
 */
export function onScreenSizeChange(callback: (isMobile: boolean) => void): () => void {
  const handler = () => {
    callback(isMobileWidth())
  }

  window.addEventListener('resize', handler)

  // 返回清理函数
  return () => window.removeEventListener('resize', handler)
}
