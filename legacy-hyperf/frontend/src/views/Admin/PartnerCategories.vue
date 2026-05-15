<template>
  <div class="partner-categories-admin">
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">合作伙伴分类管理</h1>
        <el-button type="primary" @click="showCreateDialog">
          <el-icon><Plus /></el-icon>
          添加分类
        </el-button>
      </div>
    </div>

    <!-- 分类列表 -->
    <div class="categories-container">
      <el-table
        :data="categories"
        v-loading="loading"
        border
        stripe
        style="width: 100%"
      >
        <el-table-column type="index" label="#" width="60"></el-table-column>

        <el-table-column prop="name" label="分类名称" min-width="200"></el-table-column>

        <el-table-column prop="slug" label="标识符" min-width="200"></el-table-column>

        <el-table-column prop="description" label="描述" min-width="250"></el-table-column>

        <el-table-column prop="partnerCount" label="合作伙伴数量" width="120" align="center">
          <template #default="{ row }">
            <el-tag type="info" size="small">{{ row.partnerCount || 0 }}</el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="sortOrder" label="排序" width="80"></el-table-column>

        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.isActive ? 'success' : 'info'" size="small">
              {{ row.isActive ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleEdit(row)">
              编辑
            </el-button>
            <el-button link type="danger" @click="handleDelete(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 编辑/创建对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑分类' : '添加分类'"
      width="500px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="100px"
      >
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入分类名称"></el-input>
        </el-form-item>

        <el-form-item label="标识符" prop="slug">
          <el-input
            v-model="formData.slug"
            placeholder="请输入标识符（英文，如：platform）"
            :disabled="isEdit"
          >
            <template #append>
              <el-button @click="generateSlug" :disabled="isEdit">自动生成</el-button>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item label="描述" prop="description">
          <el-input
            v-model="formData.description"
            type="textarea"
            :rows="3"
            placeholder="请输入描述（选填）"
          ></el-input>
        </el-form-item>

        <el-form-item label="排序" prop="sort_order">
          <el-input-number v-model="formData.sort_order" :min="0" :max="9999"></el-input-number>
        </el-form-item>

        <el-form-item label="状态" prop="is_active">
          <el-switch v-model="formData.is_active" active-text="启用" inactive-text="禁用"></el-switch>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">
          {{ isEdit ? '保存' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { pinyin } from 'pinyin-pro'
import { getPartnerCategories } from '@/api/admin'

const loading = ref(false)
const submitting = ref(false)
const categories = ref<any[]>([])

// 对话框相关
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref<FormInstance>()

// 表单数据
const formData = reactive({
  id: 0,
  name: '',
  slug: '',
  description: '',
  sort_order: 0,
  is_active: true
})

// 表单验证规则
const formRules: FormRules = {
  name: [
    { required: true, message: '请输入分类名称', trigger: 'blur' }
  ],
  slug: [
    { required: true, message: '请输入标识符', trigger: 'blur' },
    { pattern: /^[a-z0-9-]+$/, message: '只能包含小写字母、数字和连字符', trigger: 'blur' }
  ],
  sort_order: [
    { required: true, message: '请输入排序', trigger: 'blur' }
  ]
}

// 生成slug
const generateSlug = () => {
  if (formData.name) {
    formData.slug = pinyin(formData.name, {
      toneType: 'none',
      pattern: 'lower',
      type: 'array'
    }).join('-')
  }
}

// 加载分类列表
const loadCategories = async () => {
  try {
    loading.value = true
    console.log('开始加载分类...')
    const data = await getPartnerCategories()
    console.log('分类API返回:', data)
    console.log('数据类型:', typeof data)
    console.log('是否为数组:', Array.isArray(data))

    // 响应拦截器返回的已经是res.data，应该是一个数组
    if (Array.isArray(data)) {
      categories.value = data
      console.log('成功加载', data.length, '个分类')
    } else {
      console.error('返回的数据不是数组:', data)
      categories.value = []
    }
  } catch (error: any) {
    console.error('加载分类失败:', error)
    ElMessage.error('加载分类失败: ' + error.message)
  } finally {
    loading.value = false
  }
}

// 显示创建对话框
const showCreateDialog = () => {
  isEdit.value = false
  Object.assign(formData, {
    id: 0,
    name: '',
    slug: '',
    description: '',
    sort_order: 0,
    is_active: true
  })
  dialogVisible.value = true
}

// 编辑
const handleEdit = (row: any) => {
  isEdit.value = true
  Object.assign(formData, {
    id: row.id,
    name: row.name,
    slug: row.slug,
    description: row.description || '',
    sort_order: row.sortOrder,
    is_active: row.isActive
  })
  dialogVisible.value = true
}

// 删除
const handleDelete = (row: any) => {
  if (row.partnerCount > 0) {
    ElMessage.warning('该分类下还有合作伙伴，无法删除')
    return
  }

  ElMessageBox.confirm(
    `确定要删除分类"${row.name}"吗？`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      // TODO: 调用删除API
      ElMessage.success('删除成功')
      loadCategories()
    } catch (error) {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  }).catch(() => {
    // 用户取消
  })
}

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    submitting.value = true

    const data = {
      name: formData.name,
      slug: formData.slug,
      description: formData.description,
      sort_order: formData.sort_order,
      is_active: formData.is_active
    }

    if (isEdit.value) {
      // TODO: 调用更新API
      ElMessage.success('更新成功')
    } else {
      // TODO: 调用创建API
      ElMessage.success('创建成功')
    }

    dialogVisible.value = false
    loadCategories()
  } catch (error: any) {
    console.error('提交失败:', error)
    if (error !== false) {
      ElMessage.error(isEdit.value ? '更新失败' : '创建失败')
    }
  } finally {
    submitting.value = false
  }
}

// 对话框关闭
const handleDialogClose = () => {
  formRef.value?.resetFields()
}

onMounted(() => {
  loadCategories()
})
</script>

<style scoped lang="scss">
.partner-categories-admin {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;

  .header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .page-title {
    font-size: 24px;
    font-weight: 600;
    margin: 0;
    color: #333;
  }
}

.categories-container {
  background: #fff;
  padding: 20px;
  border-radius: 4px;
}
</style>
