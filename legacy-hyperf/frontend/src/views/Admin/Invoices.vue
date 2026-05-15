<template>
  <div class="admin-invoices">
    <div class="page-header">
      <h3>发票管理</h3>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-value">{{ stats.invoices?.total || 0 }}</div>
            <div class="stat-label">发票总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-value">¥{{ formatAmount(stats.invoices?.totalAmount || 0) }}</div>
            <div class="stat-label">开票总额</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card stat-success">
          <div class="stat-content">
            <div class="stat-value">{{ stats.invoices?.electronic || 0 }}</div>
            <div class="stat-label">电子发票</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card stat-warning">
          <div class="stat-content">
            <div class="stat-value">{{ stats.invoices?.paper || 0 }}</div>
            <div class="stat-label">纸质发票</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 筛选条件 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filters">
        <el-form-item label="发票类型">
          <el-select v-model="filters.invoiceType" placeholder="全部" clearable @change="handleSearch">
            <el-option label="电子发票" value="electronic" />
            <el-option label="纸质发票" value="paper" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input
            v-model="filters.keyword"
            placeholder="发票号码/发票抬头"
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

    <!-- 发票列表 -->
    <el-card class="table-card" shadow="never">
      <el-table
        v-loading="loading"
        :data="tableData"
        border
        stripe
        style="width: 100%"
      >
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="invoiceNo" label="发票号码" width="160" />
        <el-table-column prop="invoiceCode" label="发票代码" width="140" />
        <el-table-column label="发票类型" width="120">
          <template #default="{ row }">
            <el-tag :type="row.invoiceType === 'electronic' ? 'success' : 'warning'" size="small">
              {{ row.invoiceTypeLabel }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="invoiceTitle" label="发票抬头" width="200" show-overflow-tooltip />
        <el-table-column prop="taxNo" label="税号" width="150" show-overflow-tooltip />
        <el-table-column prop="invoiceAmount" label="发票金额" width="110">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatAmount(row.invoiceAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="invoiceDate" label="开票日期" width="120" />
        <el-table-column label="发票文件" width="120">
          <template #default="{ row }">
            <el-button
              v-if="row.invoiceFileUrl"
              link
              type="primary"
              size="small"
              @click="openInvoiceFile(row.invoiceFileUrl)"
            >
              查看
            </el-button>
            <span v-else class="text-muted">未上传</span>
          </template>
        </el-table-column>
        <el-table-column label="快递信息" width="180">
          <template #default="{ row }">
            <div v-if="row.invoiceType === 'paper'">
              <div v-if="row.expressCompany">
                <div>{{ row.expressCompany }}</div>
                <div class="text-muted">{{ row.expressNo }}</div>
              </div>
              <el-button
                v-else
                link
                type="primary"
                size="small"
                @click="handleUpdateExpress(row)"
              >
                填写快递
              </el-button>
            </div>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="收票人" width="120">
          <template #default="{ row }">
            <el-popover placement="top" :width="200" trigger="hover">
              <div>
                <p>姓名：{{ row.application?.recipientName }}</p>
                <p>电话：{{ row.application?.recipientPhone }}</p>
                <p>邮箱：{{ row.application?.recipientEmail }}</p>
              </div>
              <template #reference>
                <span class="link-text">{{ row.application?.recipientName }}</span>
              </template>
            </el-popover>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleUploadFile(row)">
              <Icon name="upload" :size="14" />
              上传文件
            </el-button>
            <el-button
              v-if="row.invoiceType === 'paper' && !row.expressCompany"
              link
              type="success"
              size="small"
              @click="handleUpdateExpress(row)"
            >
              <Icon name="edit" :size="14" />
              填写快递
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

    <!-- 上传文件对话框 -->
    <el-dialog
      v-model="uploadDialogVisible"
      title="上传发票文件"
      width="500px"
    >
      <el-upload
        ref="uploadRef"
        :auto-upload="false"
        :limit="1"
        :on-change="handleFileChange"
        :on-exceed="handleExceed"
        drag
        class="upload-area"
      >
        <Icon name="upload" :size="60" color="#409eff" />
        <div class="el-upload__text">
          将文件拖到此处，或<em>点击上传</em>
        </div>
        <template #tip>
          <div class="el-upload__tip">
            支持 PDF、JPG、PNG 格式，文件大小不超过 10MB
          </div>
        </template>
      </el-upload>

      <template #footer>
        <el-button @click="uploadDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="uploadLoading" @click="handleConfirmUpload">
          确定上传
        </el-button>
      </template>
    </el-dialog>

    <!-- 快递信息对话框 -->
    <el-dialog
      v-model="expressDialogVisible"
      title="填写快递信息"
      width="500px"
    >
      <el-form :model="expressForm" label-width="100px">
        <el-form-item label="快递公司" required>
          <el-input v-model="expressForm.express_company" placeholder="请输入快递公司" />
        </el-form-item>
        <el-form-item label="快递单号" required>
          <el-input v-model="expressForm.express_no" placeholder="请输入快递单号" />
        </el-form-item>
        <el-form-item label="快递费用">
          <el-input-number v-model="expressForm.express_fee" :min="0" :precision="2" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="expressDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="expressLoading" @click="handleConfirmExpress">
          确定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import type { UploadInstance, UploadProps, UploadRawFile } from 'element-plus'
import Icon from '@/components/Icon.vue'
import * as adminApi from '@/api/admin'

const loading = ref(false)
const uploadDialogVisible = ref(false)
const expressDialogVisible = ref(false)
const uploadLoading = ref(false)
const expressLoading = ref(false)
const tableData = ref<any[]>([])
const uploadRef = ref<UploadInstance>()
const currentInvoice = ref<any>(null)
const selectedFile = ref<File | null>(null)

// 统计数据
const stats = reactive({
  invoices: {
    total: 0,
    totalAmount: 0,
    electronic: 0,
    paper: 0
  }
})

// 筛选条件
const filters = reactive({
  invoiceType: undefined as string | undefined,
  keyword: ''
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 快递表单
const expressForm = reactive({
  express_company: '',
  express_no: '',
  express_fee: 0
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

// 加载发票列表
const loadInvoices = async () => {
  try {
    loading.value = true
    const params: any = {
      page: pagination.page,
      pageSize: pagination.pageSize
    }

    if (filters.invoiceType) params.invoice_type = filters.invoiceType
    if (filters.keyword) params.keyword = filters.keyword

    const response = await adminApi.getInvoices(params)

    if (response?.items) {
      tableData.value = response.items
      pagination.total = response.total || 0
    }
  } catch (error) {
    console.error('加载发票列表失败:', error)
    ElMessage.error('加载发票列表失败')
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

// 搜索
const handleSearch = () => {
  pagination.page = 1
  loadInvoices()
}

// 重置
const handleReset = () => {
  filters.invoiceType = undefined
  filters.keyword = ''
  handleSearch()
}

// 上传文件
const handleUploadFile = (row: any) => {
  currentInvoice.value = row
  selectedFile.value = null
  if (uploadRef.value) {
    uploadRef.value.clearFiles()
  }
  uploadDialogVisible.value = true
}

// 文件变化
const handleFileChange: UploadProps['onChange'] = (uploadFile) => {
  if (uploadFile.raw) {
    selectedFile.value = uploadFile.raw
  }
}

// 超出限制
const handleExceed: UploadProps['onExceed'] = (files) => {
  uploadRef.value?.clearFiles()
  const file = files[0] as UploadRawFile
  uploadRef.value?.handleStart(file)
  selectedFile.value = file
}

// 确认上传
const handleConfirmUpload = async () => {
  if (!currentInvoice.value) return

  if (!selectedFile.value) {
    ElMessage.warning('请选择要上传的文件')
    return
  }

  // 验证文件
  const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg']
  if (!validTypes.includes(selectedFile.value.type)) {
    ElMessage.warning('只支持上传 PDF、JPG、PNG 格式的文件')
    return
  }

  if (selectedFile.value.size > 10 * 1024 * 1024) {
    ElMessage.warning('文件大小不能超过 10MB')
    return
  }

  try {
    uploadLoading.value = true
    await adminApi.uploadInvoiceFile(currentInvoice.value.id, selectedFile.value)

    ElMessage.success('文件上传成功')
    uploadDialogVisible.value = false
    await loadInvoices()
  } catch (error: any) {
    console.error('上传失败:', error)
    ElMessage.error(error.response?.data?.message || '上传失败')
  } finally {
    uploadLoading.value = false
  }
}

// 更新快递信息
const handleUpdateExpress = (row: any) => {
  currentInvoice.value = row
  expressForm.express_company = row.expressCompany || ''
  expressForm.express_no = row.expressNo || ''
  expressForm.express_fee = row.expressFee || 0
  expressDialogVisible.value = true
}

// 确认快递信息
const handleConfirmExpress = async () => {
  if (!currentInvoice.value) return

  if (!expressForm.express_company || !expressForm.express_no) {
    ElMessage.warning('请填写完整的快递信息')
    return
  }

  try {
    expressLoading.value = true
    await adminApi.updateInvoiceExpress(currentInvoice.value.id, expressForm)

    ElMessage.success('快递信息已更新')
    expressDialogVisible.value = false
    await loadInvoices()
  } catch (error: any) {
    console.error('更新失败:', error)
    ElMessage.error(error.response?.data?.message || '更新失败')
  } finally {
    expressLoading.value = false
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
  loadInvoices()
}

// 当前页变化
const handleCurrentChange = (page: number) => {
  pagination.page = page
  loadInvoices()
}

onMounted(() => {
  loadStats()
  loadInvoices()
})
</script>

<style lang="scss" scoped>
.admin-invoices {
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

      &.stat-success .stat-value {
        color: #67c23a;
      }

      &.stat-warning .stat-value {
        color: #e6a23c;
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

    .text-muted {
      color: #909399;
      font-size: 12px;
    }

    .link-text {
      color: #409eff;
      cursor: pointer;

      &:hover {
        text-decoration: underline;
      }
    }
  }

  .upload-area {
    :deep(.el-upload-dragger) {
      padding: 40px;
    }
  }
}
</style>
