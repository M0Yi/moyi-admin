<template>
  <div class="admin-projects">
    <div class="page-header">
      <h3>项目管理</h3>
      <el-button type="primary" @click="handleAdd">
        <Icon name="add" :size="18" />
        新建项目
      </el-button>
    </div>

    <!-- 筛选栏 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="项目名称">
          <el-input
            v-model="filterForm.title"
            placeholder="请输入项目名称"
            clearable
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item label="项目类型">
          <el-select v-model="filterForm.projectType" placeholder="请选择类型" clearable>
            <el-option label="全部" value="" />
            <el-option label="助残" value="assistance" />
            <el-option label="济困" value="poverty" />
            <el-option label="助学" value="education" />
            <el-option label="医疗" value="medical" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="请选择状态" clearable>
            <el-option label="全部" value="" />
            <el-option label="进行中" value="ongoing" />
            <el-option label="已完成" value="completed" />
            <el-option label="筹备中" value="planning" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">
            <Icon name="search" :size="16" />
            搜索
          </el-button>
          <el-button @click="handleReset">
            <Icon name="refresh" :size="16" />
            重置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 项目列表 -->
    <el-card class="table-card" shadow="never">
      <el-table
        v-loading="loading"
        :data="tableData"
        border
        stripe
        style="width: 100%"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="title" label="项目名称" min-width="200" show-overflow-tooltip />
        <el-table-column label="项目类型" width="120">
          <template #default="{ row }">
            <el-tag :type="getProjectTypeColor(row.projectType) || undefined" size="small">
              {{ getProjectTypeLabel(row.projectType) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="筹款进度" width="150">
          <template #default="{ row }">
            <el-progress
              :percentage="getProgressPercentage(row)"
              :color="getProgressColor(row)"
              :stroke-width="8"
            />
          </template>
        </el-table-column>
        <el-table-column prop="beneficiaryCount" label="受益人数" width="120">
          <template #default="{ row }">
            {{ row.beneficiaryCount || 0 }} 人
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusColor(row.status) || undefined" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="创建时间" width="180" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleEdit(row)">
              <Icon name="edit" :size="14" />
              编辑
            </el-button>
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
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import Icon from '@/components/Icon.vue'
import * as adminApi from '@/api/admin'

const loading = ref(false)

// 筛选表单
const filterForm = reactive({
  title: '',
  projectType: '',
  status: ''
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格数据
const tableData = ref([])

// 项目类型映射
const getProjectTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    'assistance': '助残',
    'poverty': '济困',
    'education': '助学',
    'medical': '医疗'
  }
  return labels[type] || type
}

const getProjectTypeColor = (type: string) => {
  const colors: Record<string, '' | 'primary' | 'success' | 'warning' | 'danger' | 'info'> = {
    'assistance': 'success',
    'poverty': 'warning',
    'education': 'primary',
    'medical': 'danger'
  }
  return colors[type] || ''
}

// 状态映射
const getStatusLabel = (status: string) => {
  const labels: Record<string, string> = {
    'ongoing': '进行中',
    'completed': '已完成',
    'planning': '筹备中'
  }
  return labels[status] || status
}

const getStatusColor = (status: string) => {
  const colors: Record<string, '' | 'primary' | 'success' | 'warning' | 'danger' | 'info'> = {
    'ongoing': 'success',
    'completed': 'info',
    'planning': 'warning'
  }
  return colors[status] || ''
}

// 计算进度百分比
const getProgressPercentage = (row: any) => {
  if (!row.targetAmount || row.targetAmount === 0) return 0
  const percentage = (row.raisedAmount / row.targetAmount) * 100
  return Math.min(Math.round(percentage), 100)
}

const getProgressColor = (row: any) => {
  const percentage = getProgressPercentage(row)
  if (percentage >= 100) return '#67c23a'
  if (percentage >= 50) return '#409eff'
  return '#e6a23c'
}

// 加载项目列表
const loadProjects = async () => {
  try {
    loading.value = true
    const response = await adminApi.getProjects({
      page: pagination.page,
      limit: pagination.pageSize
    })

    // 响应拦截器已经提取了 data 字段
    if (response?.items) {
      tableData.value = response.items as any
      pagination.total = response.total || response.items.length

      // 处理数据格式
      tableData.value = tableData.value.map((item: any) => ({
        id: item.id,
        title: item.title,
        description: item.description,
        projectType: item.projectType,
        status: item.status,
        targetAmount: item.targetAmount || 0,
        raisedAmount: item.raisedAmount || 0,
        beneficiaryCount: item.beneficiaryCount || 0,
        isActive: item.isActive,
        sortOrder: item.sortOrder,
        createdAt: item.createdAt || new Date().toISOString()
      }))
    }
  } catch (error) {
    console.error('加载项目列表失败:', error)
    ElMessage.error('加载项目列表失败')
  } finally {
    loading.value = false
  }
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  loadProjects()
}

// 重置
const handleReset = () => {
  filterForm.title = ''
  filterForm.projectType = ''
  filterForm.status = ''
  pagination.page = 1
  loadProjects()
}

// 新建
const handleAdd = () => {
  ElMessage.info('新建项目功能开发中...')
}

// 编辑
const handleEdit = (row: any) => {
  ElMessage.info(`编辑项目：${row.title}`)
}

// 删除
const handleDelete = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除项目"${row.title}"吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    loading.value = true
    // 调用删除 API
    await adminApi.deleteProject(row.id)

    ElMessage.success('删除成功')
    await loadProjects()
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
  loadProjects()
}

// 当前页变化
const handleCurrentChange = (page: number) => {
  pagination.page = page
  loadProjects()
}

onMounted(() => {
  loadProjects()
})
</script>

<style lang="scss" scoped>
.admin-projects {
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

  .filter-card {
    margin-bottom: 20px;
  }

  .table-card {
    .pagination {
      margin-top: 20px;
      display: flex;
      justify-content: flex-end;
    }
  }
}
</style>
