<template>
  <div class="admin-donation-disclosures">
    <div class="page-header">
      <h3>捐赠披露管理</h3>
      <el-button type="primary" @click="handleBatchPublish">
        <Icon name="upload" :size="18" />
        批量发布
      </el-button>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-value">{{ stats.totalDisclosures }}</div>
            <div class="stat-label">披露总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-value">¥{{ formatAmount(stats.totalAmount) }}</div>
            <div class="stat-label">披露总额</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-value">{{ stats.totalPublic }}</div>
            <div class="stat-label">实名披露</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <div class="stat-value">{{ stats.totalAnonymous }}</div>
            <div class="stat-label">匿名披露</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 筛选条件 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filters">
        <el-form-item label="项目">
          <el-select v-model="filters.projectId" placeholder="全部项目" clearable @change="handleSearch">
            <el-option
              v-for="project in projects"
              :key="project.id"
              :label="project.title"
              :value="project.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            @change="handleDateRangeChange"
          />
        </el-form-item>
        <el-form-item label="是否匿名">
          <el-select v-model="filters.isAnonymous" placeholder="全部" clearable @change="handleSearch">
            <el-option label="实名" :value="false" />
            <el-option label="匿名" :value="true" />
          </el-select>
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

    <!-- 披露列表 -->
    <el-card class="table-card" shadow="never">
      <el-table
        v-loading="loading"
        :data="tableData"
        border
        stripe
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="donorName" label="捐赠人" width="150" />
        <el-table-column prop="amount" label="金额" width="120">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatAmount(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="donationDate" label="捐赠日期" width="120" />
        <el-table-column prop="projectTitle" label="项目" min-width="200" show-overflow-tooltip />
        <el-table-column label="是否匿名" width="100">
          <template #default="{ row }">
            <el-tag :type="row.isAnonymous ? 'info' : 'success'" size="small">
              {{ row.isAnonymous ? '匿名' : '实名' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="disclosedAt" label="披露时间" width="180" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="danger" size="small" @click="handleDelete(row)">
              <Icon name="delete" :size="14" />
              删除
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

    <!-- 批量发布对话框 -->
    <el-dialog
      v-model="publishDialogVisible"
      title="批量发布捐赠到披露表"
      width="600px"
    >
      <el-alert
        title="提示"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      >
        只能发布已完成的捐赠记录。请先在捐赠管理页面筛选并选择要发布的记录。
      </el-alert>

      <el-form :model="publishForm" label-width="100px">
        <el-form-item label="捐赠ID列表">
          <el-input
            v-model="publishForm.donationIds"
            type="textarea"
            :rows="5"
            placeholder="请输入捐赠ID，多个ID用逗号分隔，例如：1,2,3"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="publishDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="publishLoading" @click="handleConfirmPublish">
          确定发布
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
const publishDialogVisible = ref(false)
const publishLoading = ref(false)
const tableData = ref<any[]>([])
const selectedRows = ref<any[]>([])
const dateRange = ref<[string, string] | null>(null)

// 统计数据
const stats = reactive({
  totalDisclosures: 0,
  totalAmount: 0,
  totalAnonymous: 0,
  totalPublic: 0
})

// 项目列表
const projects = ref<any[]>([])

// 筛选条件
const filters = reactive({
  projectId: undefined as number | undefined,
  startDate: undefined as string | undefined,
  endDate: undefined as string | undefined,
  isAnonymous: undefined as boolean | undefined
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 发布表单
const publishForm = reactive({
  donationIds: ''
})

// 加载统计数据
const loadStats = async () => {
  try {
    const response = await adminApi.getDonationDisclosureStats()
    if (response) {
      Object.assign(stats, response)
    }
  } catch (error) {
    console.error('加载统计数据失败:', error)
  }
}

// 加载项目列表
const loadProjects = async () => {
  try {
    const response = await adminApi.getProjects({ pageSize: 100 })
    if (response?.items) {
      projects.value = response.items
    }
  } catch (error) {
    console.error('加载项目列表失败:', error)
  }
}

// 加载披露列表
const loadDisclosures = async () => {
  try {
    loading.value = true
    const params: any = {
      page: pagination.page,
      pageSize: pagination.pageSize
    }

    if (filters.projectId) params.project_id = filters.projectId
    if (filters.startDate) params.start_date = filters.startDate
    if (filters.endDate) params.end_date = filters.endDate
    if (filters.isAnonymous !== undefined) params.is_anonymous = filters.isAnonymous

    const response = await adminApi.getDonationDisclosures(params)

    if (response?.items) {
      tableData.value = response.items
      pagination.total = response.total || 0
    }
  } catch (error) {
    console.error('加载披露列表失败:', error)
    ElMessage.error('加载披露列表失败')
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

// 日期范围变化
const handleDateRangeChange = (value: [string, string] | null) => {
  if (value) {
    filters.startDate = value[0]
    filters.endDate = value[1]
  } else {
    filters.startDate = undefined
    filters.endDate = undefined
  }
  handleSearch()
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  loadDisclosures()
}

// 重置
const handleReset = () => {
  filters.projectId = undefined
  filters.startDate = undefined
  filters.endDate = undefined
  filters.isAnonymous = undefined
  dateRange.value = null
  handleSearch()
}

// 选择变化
const handleSelectionChange = (selection: any[]) => {
  selectedRows.value = selection
}

// 批量发布
const handleBatchPublish = () => {
  publishForm.donationIds = ''
  publishDialogVisible.value = true
}

// 确认发布
const handleConfirmPublish = async () => {
  if (!publishForm.donationIds.trim()) {
    ElMessage.warning('请输入捐赠ID')
    return
  }

  const ids = publishForm.donationIds.split(',')
    .map(id => parseInt(id.trim()))
    .filter(id => !isNaN(id))

  if (ids.length === 0) {
    ElMessage.warning('请输入有效的捐赠ID')
    return
  }

  try {
    publishLoading.value = true
    const response = await adminApi.batchPublishDonationDisclosure({ ids })

    if (response) {
      ElMessage.success(`发布完成：成功 ${response.successCount} 条，失败 ${response.failedCount} 条`)

      if (response.errors && response.errors.length > 0) {
        console.warn('发布错误:', response.errors)
      }

      publishDialogVisible.value = false
      loadDisclosures()
      loadStats()
    }
  } catch (error: any) {
    console.error('批量发布失败:', error)
    ElMessage.error('批量发布失败')
  } finally {
    publishLoading.value = false
  }
}

// 删除
const handleDelete = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除这条披露记录吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    loading.value = true
    await adminApi.deleteDonationDisclosure(row.id)

    ElMessage.success('删除成功')
    await loadDisclosures()
    await loadStats()
  } catch (error: any) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  } finally {
    loading.value = false
  }
}

// 分页大小变化
const handleSizeChange = (size: number) => {
  pagination.pageSize = size
  pagination.page = 1
  loadDisclosures()
}

// 当前页变化
const handleCurrentChange = (page: number) => {
  pagination.page = page
  loadDisclosures()
}

onMounted(() => {
  loadStats()
  loadProjects()
  loadDisclosures()
})
</script>

<style lang="scss" scoped>
.admin-donation-disclosures {
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
}
</style>
