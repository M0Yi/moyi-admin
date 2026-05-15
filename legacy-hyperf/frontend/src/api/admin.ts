import request from '@/utils/request'

/**
 * 后台管理 API
 */

// 获取统计数据
export function getStats() {
  return request({
    url: '/admin/stats',
    method: 'get'
  })
}

// 获取文章列表
export function getArticles(params?: any) {
  return request({
    url: '/admin/articles',
    method: 'get',
    params
  })
}

// 获取文章详情（用于编辑）
export function getArticle(id: number) {
  return request({
    url: `/admin/articles/${id}`,
    method: 'get'
  })
}

// 获取轮播图列表
export function getSlides() {
  return request({
    url: '/admin/slides',  // 使用后台管理API，返回驼峰命名
    method: 'get'
  })
}

// 删除轮播图
export function deleteSlide(id: number) {
  return request({
    url: `/admin/slides/${id}`,
    method: 'delete'
  })
}

// ==================== 导航菜单相关 API ====================

// 获取导航菜单树（后台管理）
export function getNavigation(params?: any) {
  return request({
    url: '/admin/navigation',
    method: 'get',
    params
  })
}

// 获取导航菜单平铺列表
export function getNavigationList(params?: any) {
  return request({
    url: '/admin/navigation-list',
    method: 'get',
    params
  })
}

// 获取单个导航菜单
export function getNavigationItem(id: number) {
  return request({
    url: `/admin/navigation/${id}`,
    method: 'get'
  })
}

// 创建导航菜单
export function createNavigation(data: any) {
  return request({
    url: '/admin/navigation',
    method: 'post',
    data
  })
}

// 更新导航菜单
export function updateNavigation(id: number, data: any) {
  return request({
    url: `/admin/navigation/${id}`,
    method: 'put',
    data
  })
}

// 删除导航菜单
export function deleteNavigation(id: number) {
  return request({
    url: `/admin/navigation/${id}`,
    method: 'delete'
  })
}

// 批量更新导航菜单排序
export function batchUpdateNavigationOrder(data: { items: Array<{ id: number; sort_order: number }> }) {
  return request({
    url: '/admin/navigation/order',
    method: 'post',
    data
  })
}

// 获取分类统计信息
export function getCategoryStats() {
  return request({
    url: '/admin/category-stats',
    method: 'get'
  })
}

// ==================== 文章分类相关 API ====================

// 获取文章分类树（后台管理）
export function getCategories(params?: any) {
  return request({
    url: '/admin/categories',
    method: 'get',
    params
  })
}

// 获取文章分类平铺列表
export function getCategoryList(params?: any) {
  return request({
    url: '/admin/category-list',
    method: 'get',
    params
  })
}

// 获取单个文章分类
export function getCategory(id: number) {
  return request({
    url: `/admin/categories/${id}`,
    method: 'get'
  })
}

// 创建文章分类
export function createCategory(data: any) {
  return request({
    url: '/admin/categories',
    method: 'post',
    data
  })
}

// 更新文章分类
export function updateCategory(id: number, data: any) {
  return request({
    url: `/admin/categories/${id}`,
    method: 'put',
    data
  })
}

// 删除文章分类
export function deleteCategory(id: number) {
  return request({
    url: `/admin/categories/${id}`,
    method: 'delete'
  })
}

// 批量更新文章分类排序
export function batchUpdateCategoryOrder(data: { items: Array<{ id: number; sort_order: number }> }) {
  return request({
    url: '/admin/categories/order',
    method: 'post',
    data
  })
}

// 获取项目列表
export function getProjects(params?: any) {
  return request({
    url: '/admin/projects',  // 使用后台管理API
    method: 'get',
    params
  })
}

// 获取用户列表
export function getUsers(params?: any) {
  return request({
    url: '/admin/users',
    method: 'get',
    params
  })
}

// 获取操作日志
export function getLogs(params?: any) {
  return request({
    url: '/admin/logs',
    method: 'get',
    params
  })
}

// 更新轮播图
export function updateSlide(id: number, data: any) {
  return request({
    url: `/admin/slides/${id}`,
    method: 'put',
    data
  })
}

// 创建轮播图
export function createSlide(data: any) {
  return request({
    url: '/admin/slides',
    method: 'post',
    data
  })
}

// 更新文章
export function updateArticle(id: number, data: any) {
  return request({
    url: `/admin/articles/${id}`,
    method: 'put',
    data
  })
}

// 删除文章
export function deleteArticle(id: number) {
  return request({
    url: `/admin/articles/${id}`,
    method: 'delete'
  })
}

// 上传图片
export function uploadImage(file: File) {
  const formData = new FormData()
  formData.append('file', file)

  return request({
    url: '/admin/upload',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

// 创建文章
export function createArticle(data: any) {
  return request({
    url: '/admin/articles',
    method: 'post',
    data
  })
}

// 更新项目
export function updateProject(id: number, data: any) {
  return request({
    url: `/admin/projects/${id}`,
    method: 'put',
    data
  })
}

// 删除项目
export function deleteProject(id: number) {
  return request({
    url: `/admin/projects/${id}`,
    method: 'delete'
  })
}

// 创建项目
export function createProject(data: any) {
  return request({
    url: '/admin/projects',
    method: 'post',
    data
  })
}

// Vue前端专用上传接口（默认站点ID=1）
export function uploadFileForVue(file: File, subPath = 'images') {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('sub_path', subPath)

  return request({
    url: '/admin/upload/vue',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

// 获取捐赠统计概览
export function getDonationStats() {
  return request({
    url: '/admin/donation-stats',
    method: 'get'
  })
}

// 获取捐赠列表
export function getDonations(params?: any) {
  return request({
    url: '/admin/donations',
    method: 'get',
    params
  })
}

// 获取捐赠详情
export function getDonation(id: number) {
  return request({
    url: `/admin/donations/${id}`,
    method: 'get'
  })
}

// 创建捐赠记录
export function createDonation(data: any) {
  return request({
    url: '/admin/donations',
    method: 'post',
    data
  })
}

// 更新捐赠状态
export function updateDonation(id: number, data: any) {
  return request({
    url: `/admin/donations/${id}`,
    method: 'put',
    data
  })
}

// 删除捐赠记录
export function deleteDonation(id: number) {
  return request({
    url: `/admin/donations/${id}`,
    method: 'delete'
  })
}

// 批量删除捐赠记录
export function batchDeleteDonations(ids: number[]) {
  return request({
    url: '/admin/donations/batch-delete',
    method: 'post',
    data: { ids }
  })
}

// 导出捐赠记录
export function exportDonations(params?: any) {
  return request({
    url: '/admin/donations-export',
    method: 'get',
    params,
    responseType: 'blob'
  })
}

// 获取捐赠披露列表
export function getDonationDisclosures(params?: any) {
  return request({
    url: '/admin/donation-disclosures',
    method: 'get',
    params
  })
}

// 获取捐赠披露统计
export function getDonationDisclosureStats() {
  return request({
    url: '/admin/donation-disclosure-stats',
    method: 'get'
  })
}

// 发布捐赠到披露表
export function publishDonationDisclosure(id: number) {
  return request({
    url: `/admin/donations/${id}/publish-disclosure`,
    method: 'post'
  })
}

// 批量发布捐赠到披露表
export function batchPublishDonationDisclosure(data: { ids: number[] }) {
  return request({
    url: '/admin/donations/batch-publish-disclosure',
    method: 'post',
    data
  })
}

// 删除捐赠披露记录
export function deleteDonationDisclosure(id: number) {
  return request({
    url: `/admin/donation-disclosures/${id}`,
    method: 'delete'
  })
}

// 批量删除捐赠披露记录
export function batchDeleteDonationDisclosure(data: { ids: number[] }) {
  return request({
    url: '/admin/donation-disclosures/batch-delete',
    method: 'post',
    data
  })
}

// ==================== 发票相关 API ====================

// 获取开票统计数据
export function getInvoiceStats() {
  return request({
    url: '/admin/invoice-stats',
    method: 'get'
  })
}

// 获取开票申请列表
export function getInvoiceApplications(params?: any) {
  return request({
    url: '/admin/invoice-applications',
    method: 'get',
    params
  })
}

// 获取开票申请详情
export function getInvoiceApplication(id: number) {
  return request({
    url: `/admin/invoice-applications/${id}`,
    method: 'get'
  })
}

// 审核开票申请
export function reviewInvoiceApplication(id: number, data: any) {
  return request({
    url: `/admin/invoice-applications/${id}/review`,
    method: 'post',
    data
  })
}

// 上传发票文件
export function uploadInvoiceFile(id: number, file: File) {
  const formData = new FormData()
  formData.append('file', file)

  return request({
    url: `/admin/invoices/${id}/upload-file`,
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

// 更新快递信息
export function updateInvoiceExpress(id: number, data: any) {
  return request({
    url: `/admin/invoices/${id}/express`,
    method: 'put',
    data
  })
}

// 获取发票列表
export function getInvoices(params?: any) {
  return request({
    url: '/admin/invoices',
    method: 'get',
    params
  })
}

// 创建开票申请
export function createInvoiceApplication(data: any) {
  return request({
    url: '/admin/invoice-applications',
    method: 'post',
    data
  })
}

// 开具发票
export function createInvoice(applicationId: number, data: any) {
  return request({
    url: `/admin/invoices/${applicationId}/create`,
    method: 'post',
    data
  })
}

// 获取可开票的捐赠列表
export function getDonationsForInvoice(params?: any) {
  return request({
    url: '/admin/donations-for-invoice',
    method: 'get',
    params
  })
}

// ==================== 合作伙伴相关 API ====================

// 获取合作伙伴列表（平铺列表，用于管理）
export function getPartners(params?: any) {
  return request({
    url: '/admin/partners-list',
    method: 'get',
    params
  })
}

// 获取合作伙伴分类列表
export function getPartnerCategories(params?: any) {
  return request({
    url: '/admin/partner-categories',
    method: 'get',
    params
  })
}

// 获取单个合作伙伴
export function getPartner(id: number) {
  return request({
    url: `/admin/partners/${id}`,
    method: 'get'
  })
}

// 创建合作伙伴
export function createPartner(data: any) {
  return request({
    url: '/admin/partners',
    method: 'post',
    data
  })
}

// 更新合作伙伴
export function updatePartner(id: number, data: any) {
  return request({
    url: `/admin/partners/${id}`,
    method: 'put',
    data
  })
}

// 删除合作伙伴
export function deletePartner(id: number) {
  return request({
    url: `/admin/partners/${id}`,
    method: 'delete'
  })
}

// 批量更新合作伙伴排序
export function batchUpdatePartnerOrder(data: { items: Array<{ id: number; sort_order: number }> }) {
  return request({
    url: '/admin/partners/order',
    method: 'post',
    data
  })
}
