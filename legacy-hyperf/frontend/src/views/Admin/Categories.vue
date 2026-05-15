<template>
  <div class="admin-categories">
    <div class="page-header">
      <h3>文章分类管理</h3>
      <el-button type="primary" @click="handleAddRoot">
        <Icon name="add" :size="18" />
        添加顶级分类
      </el-button>
    </div>

    <!-- 分类树形列表 -->
    <el-card class="tree-card" shadow="never">
    <el-table
      v-loading="loading"
      :data="treeData"
      row-key="id"
      :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
      border
      stripe
      style="width: 100%"
      :expand-row-keys="expandedKeys"
      @expand-change="handleExpandChange"
    >
        <el-table-column prop="name" label="分类名称" width="250" />
        <el-table-column prop="slug" label="URL别名" width="200" />
        <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
        <el-table-column label="文章数" width="100">
          <template #default="{ row }">
            <span>{{ row.article_count || 0 }}篇</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-switch
              v-model="row.is_active"
              @change="handleToggleActive(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleEdit(row)">
              <Icon name="edit" :size="14" />
              编辑
            </el-button>
            <el-button
              v-if="row.parent_id === 0"
              link
              type="success"
              size="small"
              @click="handleAddChild(row)"
            >
              <Icon name="add" :size="14" />
              添加子分类
            </el-button>
            <el-button link type="primary" size="small" @click="handleMoveUp(row)">
              <Icon name="up" :size="14" />
              上移
            </el-button>
            <el-button link type="primary" size="small" @click="handleMoveDown(row)">
              <Icon name="down" :size="14" />
              下移
            </el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)">
              <Icon name="delete" :size="14" />
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑分类' : (isAddChild ? '添加子分类' : '添加顶级分类')"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="120px"
      >
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入分类名称" />
        </el-form-item>

        <el-form-item label="URL别名" prop="slug">
          <el-input v-model="formData.slug" placeholder="请输入URL别名，如：news-jigou" />
        </el-form-item>

        <el-form-item label="分类类型" prop="type">
          <el-select v-model="formData.type" placeholder="请选择类型" :disabled="isEdit">
            <el-option label="文章" value="article" />
            <el-option label="生命故事" value="story" />
            <el-option label="项目" value="project" />
          </el-select>
        </el-form-item>

        <el-form-item label="描述">
          <el-input
            v-model="formData.description"
            type="textarea"
            :rows="3"
            placeholder="请输入分类描述"
          />
        </el-form-item>

        <el-form-item label="图标">
          <el-input v-model="formData.icon" placeholder="请输入图标名称，如：news, article">
            <template #append>
              <el-button @click="showIconSelector">选择</el-button>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item label="封面图片">
          <el-input v-model="formData.cover_image" placeholder="请输入封面图片URL" />
        </el-form-item>

        <el-form-item label="排序">
          <el-input-number v-model="formData.sort_order" :min="0" />
        </el-form-item>

        <el-form-item label="状态">
          <el-switch v-model="formData.is_active" />
        </el-form-item>

        <el-form-item label="单页文章分类">
          <el-switch v-model="formData.is_single_article" />
          <div class="form-tip">开启后，该分类页面将显示关联的单篇文章</div>
        </el-form-item>

        <el-form-item v-if="formData.is_single_article" label="关联文章">
          <el-select v-model="formData.linked_article_id" placeholder="请选择文章" filterable>
            <el-option
              v-for="article in articleOptions"
              :key="article.id"
              :label="article.title"
              :value="article.id"
            />
          </el-select>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
          确定
        </el-button>
      </template>
    </el-dialog>

    <!-- 图标选择器 -->
    <el-dialog v-model="iconSelectorVisible" title="选择图标" width="600px">
      <div class="icon-grid">
        <div
          v-for="icon in iconList"
          :key="icon"
          class="icon-item"
          :class="{ selected: formData.icon === icon }"
          @click="selectIcon(icon)"
        >
          <Icon :name="icon" :size="24" />
          <span>{{ icon }}</span>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import Icon from '@/components/Icon.vue'
import * as adminApi from '@/api/admin'

const loading = ref(false)
const dialogVisible = ref(false)
const iconSelectorVisible = ref(false)
const isEdit = ref(false)
const isAddChild = ref(false)
const submitLoading = ref(false)
const formRef = ref<FormInstance>()
const currentEditId = ref<number | null>(null)
const currentParentId = ref<number | null>(null)

const treeData = ref<any[]>([])
const expandedKeys = ref<number[]>([])
const articleOptions = ref<any[]>([])

// 常用图标列表
const iconList = [
  'home', 'about', 'news', 'article', 'project', 'heart', 'file', 'document',
  'phone', 'mail', 'user', 'users', 'organization', 'trophy', 'star',
  'calendar', 'clock', 'location', 'link', 'setting', 'search'
]

// 表单数据
const formData = reactive({
  parent_id: 0,
  name: '',
  slug: '',
  type: 'article',
  description: '',
  icon: '',
  cover_image: '',
  is_active: true,
  sort_order: 0,
  is_single_article: false,
  linked_article_id: null as number | null
})

// 表单验证规则
const formRules: FormRules = {
  name: [
    { required: true, message: '请输入分类名称', trigger: 'blur' }
  ],
  slug: [
    { required: true, message: '请输入URL别名', trigger: 'blur' }
  ],
  type: [
    { required: true, message: '请选择分类类型', trigger: 'change' }
  ]
}

// 加载分类树
const loadCategories = async () => {
  try {
    loading.value = true
    const response = await adminApi.getCategories({
      type: 'article',
      include_inactive: true
    })

    if (response && Array.isArray(response)) {
      treeData.value = response
    }
  } catch (error) {
    console.error('加载分类失败:', error)
    ElMessage.error('加载分类失败')
  } finally {
    loading.value = false
  }
}

// 加载文章选项（用于单页分类）
const loadArticleOptions = async () => {
  try {
    const response = await adminApi.getArticles({
      page: 1,
      pageSize: 1000
    })

    if (response && response.items) {
      articleOptions.value = response.items
    }
  } catch (error) {
    console.error('加载文章列表失败:', error)
  }
}

// 类型标签颜色
const getTypeTagColor = (type: string): '' | 'primary' | 'success' | 'warning' | 'danger' | 'info' => {
  const colors: Record<string, '' | 'primary' | 'success' | 'warning' | 'danger' | 'info'> = {
    article: '',
    story: 'success',
    project: 'warning'
  }
  return colors[type] || ''
}

// 添加顶级分类
const handleAddRoot = () => {
  isEdit.value = false
  isAddChild.value = false
  currentEditId.value = null
  currentParentId.value = null
  resetForm()
  dialogVisible.value = true
}

// 添加子分类
const handleAddChild = (row: any) => {
  isEdit.value = false
  isAddChild.value = true
  currentEditId.value = null
  currentParentId.value = row.id
  resetForm()
  formData.parent_id = row.id
  formData.type = row.type // 继承父分类的类型
  dialogVisible.value = true
}

// 编辑
const handleEdit = (row: any) => {
  isEdit.value = true
  isAddChild.value = false
  currentEditId.value = row.id
  currentParentId.value = row.parent_id

  Object.assign(formData, {
    parent_id: row.parent_id,
    name: row.name,
    slug: row.slug,
    type: row.type,
    description: row.description || '',
    icon: row.icon || '',
    cover_image: row.cover_image || '',
    is_active: row.is_active,
    sort_order: row.sort_order || 0,
    is_single_article: row.is_single_article || false,
    linked_article_id: row.linked_article_id || null
  })

  dialogVisible.value = true
}

// 删除
const handleDelete = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除分类"${row.name}"吗？${
        row.children && row.children.length > 0
          ? '该分类下有子分类，请先删除或移动子分类。'
          : ''
      }`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    if (row.children && row.children.length > 0) {
      return
    }

    loading.value = true
    await adminApi.deleteCategory(row.id)

    ElMessage.success('删除成功')
    await loadCategories()
  } catch (error: any) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(typeof error === 'string' ? error : '删除失败')
    }
  } finally {
    loading.value = false
  }
}

// 切换状态
const handleToggleActive = async (row: any) => {
  try {
    await adminApi.updateCategory(row.id, {
      ...row,
      is_active: row.is_active
    })
    ElMessage.success('状态更新成功')
    await loadCategories()
  } catch (error) {
    console.error('更新状态失败:', error)
    ElMessage.error('更新状态失败')
    // 恢复状态
    row.is_active = !row.is_active
  }
}

// 上移
const handleMoveUp = async (row: any) => {
  if (row.sort_order > 0) {
    await updateSort(row, row.sort_order - 1)
  }
}

// 下移
const handleMoveDown = async (row: any) => {
  await updateSort(row, row.sort_order + 1)
}

// 更新排序
const updateSort = async (row: any, newSort: number) => {
  try {
    loading.value = true
    await adminApi.updateCategory(row.id, {
      ...row,
      sort_order: newSort
    })
    await loadCategories()
    ElMessage.success('排序更新成功')
  } catch (error) {
    console.error('更新排序失败:', error)
    ElMessage.error('更新排序失败')
  } finally {
    loading.value = false
  }
}

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    submitLoading.value = true

    if (isEdit.value && currentEditId.value) {
      await adminApi.updateCategory(currentEditId.value, formData)
      ElMessage.success('更新成功')
    } else {
      await adminApi.createCategory(formData)
      ElMessage.success('创建成功')
    }

    dialogVisible.value = false
    await loadCategories()
  } catch (error: any) {
    console.error('提交失败:', error)
    ElMessage.error(typeof error === 'string' ? error : '提交失败')
  } finally {
    submitLoading.value = false
  }
}

// 关闭对话框
const handleDialogClose = () => {
  resetForm()
}

// 重置表单
const resetForm = () => {
  Object.assign(formData, {
    parent_id: 0,
    name: '',
    slug: '',
    type: 'article',
    description: '',
    icon: '',
    cover_image: '',
    is_active: true,
    sort_order: 0,
    is_single_article: false,
    linked_article_id: null
  })
  formRef.value?.resetFields()
}

// 展开变化
const handleExpandChange = (row: any, expandedRows: any[]) => {
  expandedKeys.value = expandedRows.map((r: any) => r.id)
}

// 显示图标选择器
const showIconSelector = () => {
  iconSelectorVisible.value = true
}

// 选择图标
const selectIcon = (icon: string) => {
  formData.icon = icon
  iconSelectorVisible.value = false
}

onMounted(() => {
  loadCategories()
  loadArticleOptions()
})
</script>

<style lang="scss" scoped>
.admin-categories {
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

  .tree-card {
    .text-muted {
      color: #909399;
    }
  }

  .form-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
  }

  .icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 10px;
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;

    .icon-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 10px;
      border: 1px solid #dcdfe6;
      border-radius: 4px;
      cursor: pointer;
      transition: all 0.3s;

      &:hover {
        border-color: #409eff;
        background-color: #f0f7ff;
      }

      &.selected {
        border-color: #409eff;
        background-color: #e6f7ff;
      }

      span {
        margin-top: 5px;
        font-size: 12px;
        color: #606266;
      }
    }
  }
}
</style>
