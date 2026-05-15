<template>
  <div class="admin-articles">
    <div class="page-header">
      <h3>文章管理</h3>
      <el-button type="primary" @click="handleAdd">
        <Icon name="add" :size="18" />
        新建文章
      </el-button>
    </div>

    <!-- 筛选栏 -->
    <el-card class="filter-card" shadow="never">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="标题">
          <el-input
            v-model="filterForm.title"
            placeholder="请输入文章标题"
            clearable
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="请选择状态" clearable>
            <el-option label="全部" value="" />
            <el-option label="已发布" value="published" />
            <el-option label="草稿" value="draft" />
          </el-select>
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="filterForm.categoryId" placeholder="请选择分类" clearable>
            <el-option label="全部" value="" />
            <el-option
              v-for="category in flatCategories"
              :key="category.id"
              :label="category.name"
              :value="category.id"
            />
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

    <!-- 文章列表 -->
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
        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
        <el-table-column label="分类" width="150">
          <template #default="{ row }">
            <span v-if="row.category">{{ row.category.name }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="author" label="作者" width="120" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">
              {{ row.status === 'published' ? '已发布' : '草稿' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="viewCount" label="浏览量" width="100">
          <template #default="{ row }">
            {{ row.viewCount || 0 }}
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
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import Icon from '@/components/Icon.vue'
import * as adminApi from '@/api/admin'

const router = useRouter()

const loading = ref(false)

// 筛选表单
const filterForm = reactive({
  title: '',
  status: '',
  categoryId: ''
})

// 分类数据
const flatCategories = ref<any[]>([])

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格数据
const tableData = ref([])

// 加载文章列表
const loadArticles = async () => {
  try {
    loading.value = true
    const response = await adminApi.getArticles({
      page: pagination.page,
      limit: pagination.pageSize,
      ...filterForm
    })

    // 响应拦截器已经提取了 data 字段
    if (response?.items) {
      tableData.value = response.items
      pagination.total = response.total || response.items.length

      // 处理数据格式，确保与表格列匹配
      tableData.value = tableData.value.map((item: any) => ({
        id: item.id,
        title: item.title,
        category: item.category || null,
        author: item.author || '管理员',
        status: item.status || 'published',
        viewCount: item.view_count || item.viewCount || 0,
        createdAt: item.created_at || item.createdAt || new Date().toISOString()
      }))
    }
  } catch (error) {
    console.error('加载文章列表失败:', error)
    ElMessage.error('加载文章列表失败')
  } finally {
    loading.value = false
  }
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  loadArticles()
}

// 重置
const handleReset = () => {
  filterForm.title = ''
  filterForm.status = ''
  filterForm.categoryId = ''
  pagination.page = 1
  loadArticles()
}

// 新建
const handleAdd = () => {
  router.push('/admin/articles/new')
}

// 编辑
const handleEdit = (row: any) => {
  router.push(`/admin/articles/${row.id}`)
}

// 删除
const handleDelete = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除文章"${row.title}"吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    loading.value = true
    // 调用删除 API
    await adminApi.deleteArticle(row.id)

    ElMessage.success('删除成功')
    await loadArticles()
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
  loadArticles()
}

// 当前页变化
const handleCurrentChange = (page: number) => {
  pagination.page = page
  loadArticles()
}

// 加载分类列表（平铺）
const loadCategories = async () => {
  try {
    const response = await adminApi.getCategoryList({
      type: 'article',
      include_inactive: false
    })

    if (response && Array.isArray(response)) {
      flatCategories.value = response
    }
  } catch (error) {
    console.error('加载分类失败:', error)
  }
}

onMounted(() => {
  loadArticles()
  loadCategories()
})
</script>

<style lang="scss" scoped>
.admin-articles {
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

    .text-muted {
      color: #909399;
    }
  }
}
</style>
