<template>
  <div class="donations-page">
    <div class="page-header">
      <div class="header-content">
        <h2>捐赠管理</h2>
        <p class="subtitle">管理所有捐赠记录和统计数据</p>
      </div>
      <div class="header-actions">
        <el-button @click="handleExport" :loading="exporting">
          <Icon name="download" :size="16" />
          导出CSV
        </el-button>
        <el-button type="primary" @click="showCreateDialog = true">
          <Icon name="plus" :size="16" />
          新建捐赠
        </el-button>
        <el-button @click="loadData" :loading="loading">
          <Icon name="refresh" :size="16" />
          刷新
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="12" :lg="6">
        <div class="stat-card stat-primary">
          <div class="stat-icon">
            <Icon name="money" :size="24" color="white" />
          </div>
          <div class="stat-content">
            <div class="stat-label">总捐赠金额</div>
            <div class="stat-value">¥{{ formatAmount(stats.total?.amount || 0) }}</div>
            <div class="stat-sub">{{ stats.total?.count || 0 }} 笔捐赠</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="24" :sm="12" :lg="6">
        <div class="stat-card stat-success">
          <div class="stat-icon">
            <Icon name="check-circle" :size="24" color="white" />
          </div>
          <div class="stat-content">
            <div class="stat-label">已完成捐赠</div>
            <div class="stat-value">¥{{ formatAmount(stats.byStatus?.completed?.amount || 0) }}</div>
            <div class="stat-sub">{{ stats.byStatus?.completed?.count || 0 }} 笔</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="24" :sm="12" :lg="6">
        <div class="stat-card stat-warning">
          <div class="stat-icon">
            <Icon name="clock" :size="24" color="white" />
          </div>
          <div class="stat-content">
            <div class="stat-label">待处理捐赠</div>
            <div class="stat-value">¥{{ formatAmount(stats.byStatus?.pending?.amount || 0) }}</div>
            <div class="stat-sub">{{ stats.byStatus?.pending?.count || 0 }} 笔</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="24" :sm="12" :lg="6">
        <div class="stat-card stat-info">
          <div class="stat-icon">
            <Icon name="calendar" :size="24" color="white" />
          </div>
          <div class="stat-content">
            <div class="stat-label">今日捐赠</div>
            <div class="stat-value">¥{{ formatAmount(stats.today?.amount || 0) }}</div>
            <div class="stat-sub">{{ stats.today?.count || 0 }} 笔</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- 捐赠趋势 -->
    <el-card class="trend-card" shadow="never">
      <template #header>
        <div class="card-header">
          <span>最近7天捐赠趋势</span>
        </div>
      </template>
      <div class="trend-chart">
        <div v-for="(day, index) in stats.trend" :key="index" class="trend-item">
          <div class="trend-bar-wrapper">
            <div class="trend-bar">
              <div
                class="trend-bar-fill"
                :style="{
                  height: getTrendHeight(day.amount) + '%',
                  background: getTrendColor(day.amount)
                }"
              ></div>
            </div>
            <div class="trend-info">
              <div class="trend-amount">¥{{ formatAmount(day.amount) }}</div>
              <div class="trend-count">{{ day.count }}笔</div>
            </div>
          </div>
          <div class="trend-label">{{ formatDate(day.date) }}</div>
        </div>
      </div>
    </el-card>

    <!-- 捐赠列表 -->
    <el-card class="table-card" shadow="never">
      <template #header>
        <div class="table-header">
          <span>捐赠记录</span>
          <div class="header-filters">
            <el-select
              v-model="filters.status"
              placeholder="状态筛选"
              clearable
              @change="loadData"
              style="width: 150px; margin-right: 10px;"
            >
              <el-option label="全部状态" value="" />
              <el-option label="已完成" value="completed" />
              <el-option label="待处理" value="pending" />
              <el-option label="失败" value="failed" />
            </el-select>
            <el-select
              v-model="filters.donation_type"
              placeholder="捐赠方式"
              clearable
              @change="loadData"
              style="width: 150px;"
            >
              <el-option label="全部方式" value="" />
              <el-option label="在线捐赠" value="online" />
              <el-option label="微信支付" value="wechat" />
              <el-option label="支付宝" value="alipay" />
              <el-option label="银行转账" value="bank" />
            </el-select>
          </div>
        </div>
      </template>

      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="orderNo" label="订单号" width="150" />
        <el-table-column prop="donorName" label="捐赠人" width="120">
          <template #default="{ row }">
            <span v-if="row.isAnonymous" class="anonymous-badge">
              <el-icon><User /></el-icon>
              爱心人士
            </span>
            <span v-else>{{ row.donorName }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="amount" label="捐赠金额" width="120">
          <template #default="{ row }">
            <span class="amount">¥{{ row.amount.toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="donationTypeLabel" label="捐赠方式" width="100" />
        <el-table-column prop="projectTitle" label="捐赠项目" min-width="150">
          <template #default="{ row }">
            <span v-if="row.projectTitle">{{ row.projectTitle }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ row.statusLabel }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="捐赠时间" width="160" />
        <el-table-column label="操作" width="350" fixed="right">
          <template #default="{ row }">
            <el-button
              type="primary"
              size="small"
              link
              @click="viewDetail(row)"
            >
              详情
            </el-button>
            <el-button
              type="primary"
              size="small"
              link
              @click="handleEdit(row)"
            >
              编辑
            </el-button>
            <el-button
              v-if="row.status === 'pending'"
              type="success"
              size="small"
              link
              @click="handleUpdateStatus(row, 'completed')"
            >
              确认
            </el-button>
            <el-button
              v-if="row.status === 'pending'"
              type="danger"
              size="small"
              link
              @click="handleUpdateStatus(row, 'failed')"
            >
              拒绝
            </el-button>
            <el-button
              v-if="row.status === 'completed'"
              type="success"
              size="small"
              link
              @click="handlePublishDisclosure(row)"
            >
              发布披露
            </el-button>
            <el-button
              type="danger"
              size="small"
              link
              @click="handleDelete(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="table-footer">
        <div class="batch-actions">
          <el-button
            type="danger"
            size="small"
            :disabled="selectedRows.length === 0"
            @click="handleBatchDelete"
          >
            批量删除 ({{ selectedRows.length }})
          </el-button>
        </div>
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.limit"
          :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </el-card>

    <!-- 新建/编辑对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingDonation ? '编辑捐赠' : '新建捐赠'"
      width="600px"
      @close="resetForm"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="120px"
      >
        <el-form-item label="捐赠人姓名" prop="donorName">
          <el-input
            v-model="formData.donorName"
            placeholder="请输入捐赠人姓名"
            :disabled="formData.isAnonymous"
          />
        </el-form-item>

        <el-form-item label="匿名捐赠">
          <el-switch v-model="formData.isAnonymous" />
          <span class="form-tip">匿名后捐赠人姓名将显示为"爱心人士"</span>
        </el-form-item>

        <el-form-item label="捐赠金额" prop="amount">
          <el-input-number
            v-model="formData.amount"
            :min="0.01"
            :step="0.01"
            :precision="2"
            placeholder="请输入捐赠金额"
            style="width: 100%;"
          />
        </el-form-item>

        <el-form-item label="捐赠方式" prop="donationType">
          <el-select v-model="formData.donationType" placeholder="请选择捐赠方式" style="width: 100%;">
            <el-option label="在线捐赠" value="online" />
            <el-option label="微信支付" value="wechat" />
            <el-option label="支付宝" value="alipay" />
            <el-option label="银行转账" value="bank" />
          </el-select>
        </el-form-item>

        <el-form-item label="捐赠项目">
          <el-select v-model="formData.projectId" placeholder="请选择项目" clearable style="width: 100%;">
            <el-option
              v-for="project in projects"
              :key="project.id"
              :label="project.title"
              :value="project.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="手机号码">
          <el-input v-model="formData.donorPhone" placeholder="请输入手机号码" />
        </el-form-item>

        <el-form-item label="邮箱">
          <el-input v-model="formData.donorEmail" placeholder="请输入邮箱" />
        </el-form-item>

        <el-form-item label="捐赠状态">
          <el-radio-group v-model="formData.status">
            <el-radio label="pending">待处理</el-radio>
            <el-radio label="completed">已完成</el-radio>
            <el-radio label="failed">失败</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="留言">
          <el-input
            v-model="formData.message"
            type="textarea"
            :rows="3"
            placeholder="请输入留言"
          />
        </el-form-item>

        <el-form-item label="交易ID">
          <el-input v-model="formData.transactionId" placeholder="请输入交易ID" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">
          保存
        </el-button>
      </template>
    </el-dialog>

    <!-- 详情对话框 -->
    <el-dialog
      v-model="showDetailDialog"
      title="捐赠详情"
      width="600px"
    >
      <div v-if="currentDonation" class="detail-content">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="订单号">
            {{ currentDonation.orderNo }}
          </el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusType(currentDonation.status)">
              {{ currentDonation.statusLabel }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="捐赠人">
            <span v-if="currentDonation.isAnonymous">爱心人士</span>
            <span v-else>{{ currentDonation.donorName }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="捐赠金额">
            <span class="amount">¥{{ currentDonation.amount?.toFixed(2) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="捐赠方式">
            {{ currentDonation.donationTypeLabel }}
          </el-descriptions-item>
          <el-descriptions-item label="捐赠项目">
            {{ currentDonation.projectTitle || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="手机号码">
            {{ currentDonation.donorPhone || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="邮箱">
            {{ currentDonation.donorEmail || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="交易ID" :span="2">
            {{ currentDonation.transactionId || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="留言" :span="2">
            {{ currentDonation.message || '无' }}
          </el-descriptions-item>
          <el-descriptions-item label="支付时间">
            {{ currentDonation.paymentDate || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">
            {{ currentDonation.createdAt }}
          </el-descriptions-item>
        </el-descriptions>
      </div>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
        <el-button type="primary" @click="handleEditFromDetail()">编辑</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { User } from '@element-plus/icons-vue'
import Icon from '@/components/Icon.vue'
import * as adminApi from '@/api/admin'

const loading = ref(false)
const saving = ref(false)
const exporting = ref(false)
const tableData = ref<any[]>([])
const selectedRows = ref<any[]>([])
const showCreateDialog = ref(false)
const showDetailDialog = ref(false)
const editingDonation = ref<any>(null)
const currentDonation = ref<any>(null)
const projects = ref<any[]>([])
const formRef = ref<FormInstance>()

const stats = reactive({
  total: { amount: 0, count: 0 },
  byStatus: {
    completed: { amount: 0, count: 0 },
    pending: { amount: 0, count: 0 },
    failed: { amount: 0, count: 0 }
  },
  today: { amount: 0, count: 0 },
  trend: [] as any[]
})

const filters = reactive({
  status: '',
  donation_type: ''
})

const pagination = reactive({
  page: 1,
  limit: 20,
  total: 0
})

const formData = reactive({
  donorName: '',
  donorPhone: '',
  donorEmail: '',
  amount: 0,
  donationType: 'online',
  projectId: null as number | null,
  isAnonymous: false,
  status: 'pending',
  message: '',
  transactionId: ''
})

const formRules: FormRules = {
  amount: [
    { required: true, message: '请输入捐赠金额', trigger: 'blur' },
    { type: 'number', min: 0.01, message: '金额必须大于0', trigger: 'blur' }
  ],
  donationType: [
    { required: true, message: '请选择捐赠方式', trigger: 'change' }
  ]
}

// 格式化金额
const formatAmount = (amount: number) => {
  if (amount >= 10000) {
    return (amount / 10000).toFixed(2) + '万'
  }
  return amount.toFixed(2)
}

// 格式化日期
const formatDate = (date: string) => {
  const d = new Date(date)
  return `${d.getMonth() + 1}/${d.getDate()}`
}

// 获取趋势高度
const getTrendHeight = (amount: number) => {
  const maxAmount = Math.max(...stats.trend.map((d: any) => d.amount), 1)
  return (amount / maxAmount) * 100
}

// 获取趋势颜色
const getTrendColor = (amount: number) => {
  if (amount === 0) return '#e0e0e0'
  if (amount < 1000) return '#409eff'
  if (amount < 5000) return '#67c23a'
  return '#f56c6c'
}

// 获取状态类型
const getStatusType = (status: string) => {
  const map: Record<string, string> = {
    completed: 'success',
    pending: 'warning',
    failed: 'danger'
  }
  return map[status] || 'info'
}

// 加载数据
const loadData = async () => {
  loading.value = true
  try {
    // 加载统计
    const statsRes = await adminApi.getDonationStats()
    Object.assign(stats, statsRes)

    // 加载列表
    const response = await adminApi.getDonations({
      page: pagination.page,
      limit: pagination.limit,
      status: filters.status || undefined,
      donation_type: filters.donation_type || undefined
    })

    tableData.value = response.items || []
    pagination.total = response.total || 0
  } catch (error) {
    console.error('加载数据失败:', error)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const response = await adminApi.getProjects({ limit: 100 })
    projects.value = response.items || []
  } catch (error) {
    console.error('加载项目失败:', error)
  }
}

// 选择行
const handleSelectionChange = (rows: any[]) => {
  selectedRows.value = rows
}

// 重置表单
const resetForm = () => {
  Object.assign(formData, {
    donorName: '',
    donorPhone: '',
    donorEmail: '',
    amount: 0,
    donationType: 'online',
    projectId: null,
    isAnonymous: false,
    status: 'pending',
    message: '',
    transactionId: ''
  })
  editingDonation.value = null
  formRef.value?.resetFields()
}

// 编辑
const handleEdit = async (row: any) => {
  editingDonation.value = row
  Object.assign(formData, {
    donorName: row.donorName || '',
    donorPhone: row.donorPhone || '',
    donorEmail: row.donorEmail || '',
    amount: row.amount,
    donationType: row.donationType || 'online',
    projectId: row.projectId,
    isAnonymous: row.isAnonymous,
    status: row.status,
    message: row.message || '',
    transactionId: row.transactionId || ''
  })
  showCreateDialog.value = true
}

// 从详情编辑
const handleEditFromDetail = () => {
  showDetailDialog.value = false
  handleEdit(currentDonation.value)
}

// 保存
const handleSave = async () => {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    saving.value = true

    const payload = {
      donor_name: formData.donorName,
      donor_phone: formData.donorPhone,
      donor_email: formData.donorEmail,
      amount: formData.amount,
      donation_type: formData.donationType,
      project_id: formData.projectId,
      is_anonymous: formData.isAnonymous,
      status: formData.status,
      message: formData.message,
      transaction_id: formData.transactionId
    }

    if (editingDonation.value) {
      await adminApi.updateDonation(editingDonation.value.id, payload)
      ElMessage.success('更新成功')
    } else {
      await adminApi.createDonation(payload)
      ElMessage.success('创建成功')
    }

    showCreateDialog.value = false
    resetForm()
    await loadData()
  } catch (error: any) {
    console.error('保存失败:', error)
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

// 更新状态
const handleUpdateStatus = async (row: any, status: string) => {
  const action = status === 'completed' ? '确认' : '拒绝'

  try {
    await ElMessageBox.confirm(
      `确定要${action}这笔捐赠吗？金额为 ¥${row.amount.toFixed(2)}`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await adminApi.updateDonation(row.id, { status })
    ElMessage.success(`${action}成功`)
    await loadData()
  } catch (error: any) {
    if (error !== 'cancel') {
      console.error('更新失败:', error)
      ElMessage.error('更新失败')
    }
  }
}

// 删除
const handleDelete = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除这笔捐赠记录吗？金额为 ¥${row.amount.toFixed(2)}`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await adminApi.deleteDonation(row.id)
    ElMessage.success('删除成功')
    await loadData()
  } catch (error: any) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 发布到披露表
const handlePublishDisclosure = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `确定要将这笔捐赠发布到披露表吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'info'
      }
    )

    await adminApi.publishDonationDisclosure(row.id)
    ElMessage.success('发布成功')
  } catch (error: any) {
    if (error !== 'cancel') {
      console.error('发布失败:', error)
      ElMessage.error(error.response?.data?.message || '发布失败')
    }
  }
}

// 批量删除
const handleBatchDelete = async () => {
  try {
    await ElMessageBox.confirm(
      `确定要删除选中的 ${selectedRows.value.length} 条记录吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const ids = selectedRows.value.map((row: any) => row.id)
    await adminApi.batchDeleteDonations(ids)
    ElMessage.success('批量删除成功')
    await loadData()
  } catch (error: any) {
    if (error !== 'cancel') {
      console.error('批量删除失败:', error)
      ElMessage.error('批量删除失败')
    }
  }
}

// 查看详情
const viewDetail = async (row: any) => {
  try {
    const detail = await adminApi.getDonation(row.id)
    currentDonation.value = detail
    showDetailDialog.value = true
  } catch (error) {
    console.error('加载详情失败:', error)
    ElMessage.error('加载详情失败')
  }
}

// 导出
const handleExport = async () => {
  exporting.value = true
  try {
    const blob: any = await adminApi.exportDonations({
      status: filters.status || undefined,
      donation_type: filters.donation_type || undefined
    })

    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `donations_${new Date().getTime()}.csv`
    link.click()
    window.URL.revokeObjectURL(url)

    ElMessage.success('导出成功')
  } catch (error) {
    console.error('导出失败:', error)
    ElMessage.error('导出失败')
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  await loadProjects()
  await loadData()
})
</script>

<style lang="scss" scoped>
.donations-page {
  padding: 20px;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;

    .header-content {
      h2 {
        font-size: 24px;
        font-weight: 600;
        color: #303133;
        margin: 0 0 8px 0;
      }

      .subtitle {
        font-size: 14px;
        color: #909399;
        margin: 0;
      }
    }

    .header-actions {
      display: flex;
      gap: 12px;
    }
  }

  .stats-row {
    margin-bottom: 20px;
  }

  .stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s;

    &:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    }

    .stat-icon {
      width: 56px;
      height: 56px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .stat-content {
      flex: 1;

      .stat-label {
        font-size: 14px;
        color: #909399;
        margin-bottom: 8px;
      }

      .stat-value {
        font-size: 24px;
        font-weight: 600;
        color: #303133;
        margin-bottom: 4px;
      }

      .stat-sub {
        font-size: 12px;
        color: #c0c4cc;
      }
    }

    &.stat-primary .stat-icon {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    &.stat-success .stat-icon {
      background: linear-gradient(135deg, #67c23a 0%, #85ce61 100%);
    }

    &.stat-warning .stat-icon {
      background: linear-gradient(135deg, #e6a23c 0%, #f0c78a 100%);
    }

    &.stat-info .stat-icon {
      background: linear-gradient(135deg, #409eff 0%, #66b1ff 100%);
    }
  }

  .trend-card {
    margin-bottom: 20px;

    .trend-chart {
      display: flex;
      justify-content: space-around;
      align-items: flex-end;
      height: 200px;
      padding: 20px 0;

      .trend-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;

        .trend-bar-wrapper {
          flex: 1;
          width: 100%;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: flex-end;

          .trend-bar {
            width: 40px;
            height: 140px;
            background: #f5f7fa;
            border-radius: 4px;
            position: relative;
            overflow: hidden;

            .trend-bar-fill {
              position: absolute;
              bottom: 0;
              left: 0;
              right: 0;
              transition: height 0.3s ease;
              border-radius: 4px 4px 0 0;
            }
          }

          .trend-info {
            margin-top: 8px;
            text-align: center;

            .trend-amount {
              font-size: 14px;
              font-weight: 600;
              color: #303133;
            }

            .trend-count {
              font-size: 12px;
              color: #909399;
            }
          }
        }

        .trend-label {
          font-size: 12px;
          color: #909399;
        }
      }
    }
  }

  .table-card {
    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .anonymous-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      color: #909399;
    }

    .amount {
      font-weight: 600;
      color: #f56c6c;
    }

    .text-muted {
      color: #c0c4cc;
    }

    .table-footer {
      margin-top: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;

      .batch-actions {
        display: flex;
        gap: 12px;
      }
    }
  }

  .form-tip {
    margin-left: 12px;
    font-size: 12px;
    color: #909399;
  }

  .detail-content {
    .amount {
      font-weight: 600;
      color: #f56c6c;
      font-size: 16px;
    }
  }
}
</style>
