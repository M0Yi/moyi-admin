<template>
  <div class="admin-invoice-applications">
    <div class="page-header">
      <h3>开票申请管理</h3>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-value">{{ stats.applications?.total || 0 }}</div>
            <div class="stat-label">申请总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card stat-warning">
          <div class="stat-content">
            <div class="stat-value">{{ stats.applications?.pending || 0 }}</div>
            <div class="stat-label">待审核</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card stat-success">
          <div class="stat-content">
            <div class="stat-value">{{ stats.applications?.approved || 0 }}</div>
            <div class="stat-label">已开具</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card stat-danger">
          <div class="stat-content">
            <div class="stat-value">{{ stats.applications?.rejected || 0 }}</div>
            <div class="stat-label">已拒绝</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 筛选条件 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filters">
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable @change="handleSearch">
            <el-option label="待审核" value="pending" />
            <el-option label="已开具" value="approved" />
            <el-option label="已拒绝" value="rejected" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="发票类型">
          <el-select v-model="filters.invoiceType" placeholder="全部" clearable @change="handleSearch">
            <el-option label="个人" value="individual" />
            <el-option label="企业" value="enterprise" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input
            v-model="filters.keyword"
            placeholder="申请号/发票抬头/收票人"
            clearable
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">
            <Icon name="search" :size="14" />
            查询
          </el-button>
          <el-button @click="handleReset">
            <Icon name="refresh" :size="14" />
            重置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 申请列表 -->
    <el-card class="table-card" shadow="never">
      <el-table
        v-loading="loading"
        :data="tableData"
        border
        stripe
        style="width: 100%"
      >
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="applicationNo" label="申请编号" width="160" />
        <el-table-column prop="donationOrderNo" label="捐赠订单号" width="140" />
        <el-table-column prop="donationAmount" label="捐赠金额" width="100">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatAmount(row.donationAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="发票类型" width="100">
          <template #default="{ row }">
            <el-tag :type="row.invoiceType === 'enterprise' ? 'warning' : 'success'" size="small">
              {{ row.invoiceTypeLabel }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="invoiceTitle" label="发票抬头" width="200" show-overflow-tooltip />
        <el-table-column prop="recipientName" label="收票人" width="120" />
        <el-table-column prop="recipientPhone" label="联系电话" width="130" />
        <el-table-column prop="invoiceAmount" label="开票金额" width="100">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatAmount(row.invoiceAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status) || undefined" size="small">
              {{ row.statusLabel }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="申请时间" width="170" />
        <el-table-column label="操作" width="250" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleViewDetail(row)">
              <Icon name="eye" :size="14" />
              查看
            </el-button>
            <el-button
              v-if="row.status === 'pending'"
              link
              type="success"
              size="small"
              @click="handleReview(row)"
            >
              <Icon name="check" :size="14" />
              审核
            </el-button>
            <el-button
              v-if="row.status === 'approved' && !row.hasInvoice"
              link
              type="primary"
              size="small"
              @click="handleCreateInvoice(row)"
            >
              <Icon name="ticket" :size="14" />
              开票
            </el-button>
            <el-button
              v-if="row.hasInvoice"
              link
              type="info"
              size="small"
              @click="handleViewInvoice(row)"
            >
              <Icon name="document" :size="14" />
              发票
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>

    <!-- 详情对话框 -->
    <el-dialog
      v-model="detailDialogVisible"
      title="开票申请详情"
      width="700px"
    >
      <div v-if="currentApplication" class="detail-content">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="申请编号">
            {{ currentApplication.applicationNo }}
          </el-descriptions-item>
          <el-descriptions-item label="申请状态">
            <el-tag :type="getStatusType(currentApplication.status)">
              {{ currentApplication.statusLabel }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="捐赠订单号">
            {{ currentApplication.donation?.orderNo }}
          </el-descriptions-item>
          <el-descriptions-item label="捐赠金额">
            ¥{{ formatAmount(currentApplication.donation?.amount) }}
          </el-descriptions-item>
          <el-descriptions-item label="发票类型">
            {{ currentApplication.invoiceTypeLabel }}
          </el-descriptions-item>
          <el-descriptions-item label="发票抬头">
            {{ currentApplication.invoiceTitle || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="税号">
            {{ currentApplication.taxNo || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="开票金额">
            <span class="amount-text">¥{{ formatAmount(currentApplication.invoiceAmount) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="收票人姓名">
            {{ currentApplication.recipientName }}
          </el-descriptions-item>
          <el-descriptions-item label="收票人电话">
            {{ currentApplication.recipientPhone }}
          </el-descriptions-item>
          <el-descriptions-item label="收票人邮箱" :span="2">
            {{ currentApplication.recipientEmail }}
          </el-descriptions-item>
          <el-descriptions-item label="收票地址" :span="2">
            {{ currentApplication.recipientAddress || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="申请人备注" :span="2">
            {{ currentApplication.applicantMemo || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="申请时间">
            {{ currentApplication.createdAt }}
          </el-descriptions-item>
          <el-descriptions-item label="审核时间">
            {{ currentApplication.reviewedAt || '-' }}
          </el-descriptions-item>
          <el-descriptions-item v-if="currentApplication.rejectReason" label="拒绝原因" :span="2">
            {{ currentApplication.rejectReason }}
          </el-descriptions-item>
        </el-descriptions>

        <!-- 发票信息 -->
        <div v-if="currentApplication.invoice" class="invoice-info">
          <h4>发票信息</h4>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="发票号码">
              {{ currentApplication.invoice.invoiceNo }}
            </el-descriptions-item>
            <el-descriptions-item label="发票类型">
              {{ currentApplication.invoice.invoiceTypeLabel }}
            </el-descriptions-item>
            <el-descriptions-item label="发票金额">
              ¥{{ formatAmount(currentApplication.invoice.invoiceAmount) }}
            </el-descriptions-item>
            <el-descriptions-item label="开票日期">
              {{ currentApplication.invoice.invoiceDate }}
            </el-descriptions-item>
            <el-descriptions-item v-if="currentApplication.invoice.invoiceFileUrl" label="发票文件" :span="2">
              <el-button link type="primary" @click="openInvoiceFile(currentApplication.invoice.invoiceFileUrl)">
                查看发票文件
              </el-button>
            </el-descriptions-item>
          </el-descriptions>
        </div>
      </div>
    </el-dialog>

    <!-- 审核对话框 -->
    <el-dialog
      v-model="reviewDialogVisible"
      title="审核开票申请"
      width="600px"
    >
      <div v-if="currentApplication">
        <el-alert
          title="申请信息"
          type="info"
          :closable="false"
          style="margin-bottom: 20px"
        >
          <p>申请编号：{{ currentApplication.applicationNo }}</p>
          <p>发票类型：{{ currentApplication.invoiceTypeLabel }}</p>
          <p>发票抬头：{{ currentApplication.invoiceTitle || currentApplication.recipientName }}</p>
          <p>开票金额：¥{{ formatAmount(currentApplication.invoiceAmount) }}</p>
        </el-alert>

        <el-form :model="reviewForm" label-width="100px">
          <el-form-item label="审核结果">
            <el-radio-group v-model="reviewForm.action">
              <el-radio label="approve">批准申请</el-radio>
              <el-radio label="reject">拒绝申请</el-radio>
            </el-radio-group>
          </el-form-item>

          <template v-if="reviewForm.action === 'approve'">
            <el-alert
              title="批准后将允许该申请进行开票，请确保该捐赠订单未开具过发票"
              type="warning"
              :closable="false"
              style="margin-bottom: 15px"
            />
          </template>

          <template v-if="reviewForm.action === 'reject'">
            <el-form-item label="拒绝原因" required>
              <el-input
                v-model="reviewForm.reject_reason"
                type="textarea"
                :rows="3"
                placeholder="请输入拒绝原因"
              />
            </el-form-item>
          </template>

          <el-form-item label="审核备注">
            <el-input
              v-model="reviewForm.review_memo"
              type="textarea"
              :rows="2"
              placeholder="请输入审核备注（可选）"
            />
          </el-form-item>
        </el-form>
      </div>

      <template #footer>
        <el-button @click="reviewDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="reviewLoading" @click="handleConfirmReview">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 开具发票对话框 -->
    <el-dialog
      v-model="invoiceDialogVisible"
      title="开具发票"
      width="600px"
    >
      <div v-if="currentApplication">
        <el-alert
          title="申请信息"
          type="info"
          :closable="false"
          style="margin-bottom: 20px"
        >
          <p>申请编号：{{ currentApplication.applicationNo }}</p>
          <p>发票类型：{{ currentApplication.invoiceTypeLabel }}</p>
          <p>发票抬头：{{ currentApplication.invoiceTitle || currentApplication.recipientName }}</p>
          <p>开票金额：¥{{ formatAmount(currentApplication.invoiceAmount) }}</p>
        </el-alert>

        <el-form :model="invoiceForm" label-width="100px">
          <el-form-item label="发票号码" required>
            <el-input v-model="invoiceForm.invoice_no" placeholder="请输入发票号码" />
          </el-form-item>
          <el-form-item label="发票代码">
            <el-input v-model="invoiceForm.invoice_code" placeholder="请输入发票代码（可选）" />
          </el-form-item>
          <el-form-item label="发票类型">
            <el-select v-model="invoiceForm.invoice_type" style="width: 100%">
              <el-option label="电子发票" value="electronic" />
              <el-option label="纸质发票" value="paper" />
            </el-select>
          </el-form-item>
          <el-form-item label="发票种类">
            <el-select v-model="invoiceForm.invoice_form" style="width: 100%">
              <el-option label="普通发票" value="ordinary" />
              <el-option label="专用发票" value="vat" />
            </el-select>
          </el-form-item>
        </el-form>
      </div>

      <template #footer>
        <el-button @click="invoiceDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="invoiceLoading" @click="handleConfirmCreateInvoice">
          确定开票
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import Icon from '@/components/Icon.vue'
import * as adminApi from '@/api/admin'

const loading = ref(false)
const detailDialogVisible = ref(false)
const reviewDialogVisible = ref(false)
const reviewLoading = ref(false)
const tableData = ref<any[]>([])
const currentApplication = ref<any>(null)

// 统计数据
const stats = reactive({
  applications: {
    total: 0,
    pending: 0,
    approved: 0,
    rejected: 0
  }
})

// 筛选条件
const filters = reactive({
  status: undefined as string | undefined,
  invoiceType: undefined as string | undefined,
  keyword: ''
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 审核表单
const reviewForm = reactive({
  action: 'approve',
  invoice_no: '',
  invoice_code: '',
  invoice_type: 'electronic',
  invoice_form: 'ordinary',
  reject_reason: '',
  review_memo: ''
})

// 加载统计数据
const loadStats = async () => {
  try {
    const response = await adminApi.getInvoiceStats()
    if (response) {
      Object.assign(stats, response)
    }
  } catch (error) {
    console.error('加载统计数据失败:', error)
  }
}

// 加载申请列表
const loadApplications = async () => {
  try {
    loading.value = true
    const params: any = {
      page: pagination.page,
      pageSize: pagination.pageSize
    }

    if (filters.status) params.status = filters.status
    if (filters.invoiceType) params.invoice_type = filters.invoiceType
    if (filters.keyword) params.keyword = filters.keyword

    const response = await adminApi.getInvoiceApplications(params)

    if (response?.items) {
      tableData.value = response.items
      pagination.total = response.total || 0
    }
  } catch (error) {
    console.error('加载申请列表失败:', error)
    ElMessage.error('加载申请列表失败')
  } finally {
    loading.value = false
  }
}

// 格式化金额
const formatAmount = (amount: number) => {
  return amount.toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

// 获取状态类型
const getStatusType = (status: string) => {
  const map: Record<string, '' | 'primary' | 'success' | 'warning' | 'danger' | 'info'> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'info'
  }
  return map[status] || ''
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  loadApplications()
}

// 重置
const handleReset = () => {
  filters.status = undefined
  filters.invoiceType = undefined
  filters.keyword = ''
  handleSearch()
}

// 查看详情
const handleViewDetail = async (row: any) => {
  try {
    const response = await adminApi.getInvoiceApplication(row.id)
    if (response) {
      currentApplication.value = response
      detailDialogVisible.value = true
    }
  } catch (error) {
    console.error('加载详情失败:', error)
    ElMessage.error('加载详情失败')
  }
}

// 审核
const handleReview = (row: any) => {
  currentApplication.value = row
  reviewForm.action = 'approve'
  reviewForm.reject_reason = ''
  reviewForm.review_memo = ''
  reviewDialogVisible.value = true
}

// 确认审核
const handleConfirmReview = async () => {
  if (!currentApplication.value) return

  if (reviewForm.action === 'reject' && !reviewForm.reject_reason) {
    ElMessage.warning('请输入拒绝原因')
    return
  }

  try {
    reviewLoading.value = true
    await adminApi.reviewInvoiceApplication(currentApplication.value.id, reviewForm)

    ElMessage.success(reviewForm.action === 'approve' ? '已批准该开票申请' : '已拒绝该申请')
    reviewDialogVisible.value = false
    await loadApplications()
    await loadStats()
  } catch (error: any) {
    console.error('审核失败:', error)
    ElMessage.error(error.response?.data?.message || '审核失败')
  } finally {
    reviewLoading.value = false
  }
}

// 查看发票
const handleViewInvoice = (row: any) => {
  if (row.invoice?.invoiceFileUrl) {
    openInvoiceFile(row.invoice.invoiceFileUrl)
  } else {
    ElMessage.warning('发票文件尚未上传')
  }
}

// 开具发票对话框
const invoiceDialogVisible = ref(false)
const invoiceLoading = ref(false)
const invoiceForm = reactive({
  invoice_no: '',
  invoice_code: '',
  invoice_type: 'electronic',
  invoice_form: 'ordinary'
})

// 开具发票
const handleCreateInvoice = (row: any) => {
  currentApplication.value = row
  invoiceForm.invoice_no = ''
  invoiceForm.invoice_code = ''
  invoiceForm.invoice_type = 'electronic'
  invoiceForm.invoice_form = 'ordinary'
  invoiceDialogVisible.value = true
}

// 确认开具发票
const handleConfirmCreateInvoice = async () => {
  if (!currentApplication.value) return

  if (!invoiceForm.invoice_no) {
    ElMessage.warning('请输入发票号码')
    return
  }

  try {
    invoiceLoading.value = true
    await adminApi.createInvoice(currentApplication.value.id, invoiceForm)

    ElMessage.success('发票开具成功')
    invoiceDialogVisible.value = false
    await loadApplications()
    await loadStats()
  } catch (error: any) {
    console.error('开票失败:', error)
    ElMessage.error(error.response?.data?.message || '开票失败')
  } finally {
    invoiceLoading.value = false
  }
}

// 打开发票文件
const openInvoiceFile = (url: string) => {
  window.open(url, '_blank')
}

// 分页大小变化
const handleSizeChange = (size: number) => {
  pagination.pageSize = size
  pagination.page = 1
  loadApplications()
}

// 当前页变化
const handleCurrentChange = (page: number) => {
  pagination.page = page
  loadApplications()
}

onMounted(() => {
  loadStats()
  loadApplications()
})
</script>

<style lang="scss" scoped>
.admin-invoice-applications {
  .page-header {
    margin-bottom: 20px;

    h3 {
      font-size: 20px;
      color: #303133;
      margin: 0;
    }
  }

  .stats-row {
    margin-bottom: 20px;

    .stat-card {
      .stat-content {
        text-align: center;

        .stat-value {
          font-size: 28px;
          font-weight: bold;
          color: #409eff;
          margin-bottom: 8px;
        }

        .stat-label {
          font-size: 14px;
          color: #909399;
        }
      }

      &.stat-warning .stat-value {
        color: #e6a23c;
      }

      &.stat-success .stat-value {
        color: #67c23a;
      }

      &.stat-danger .stat-value {
        color: #f56c6c;
      }
    }
  }

  .filter-card {
    margin-bottom: 20px;
  }

  .table-card {
    .pagination {
      margin-top: 20px;
      display: flex;
      justify-content: flex-end;
    }

    .amount-text {
      color: #f56c6c;
      font-weight: bold;
    }
  }

  .detail-content {
    .invoice-info {
      margin-top: 20px;

      h4 {
        margin-bottom: 10px;
        color: #303133;
      }
    }
  }
}
</style>
