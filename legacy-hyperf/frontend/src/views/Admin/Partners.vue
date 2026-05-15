<template>
  <div class="partners-admin">
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">合作伙伴管理</h1>
        <el-button type="primary" @click="showCreateDialog">
          <el-icon><Plus /></el-icon>
          添加合作伙伴
        </el-button>
      </div>
    </div>

    <!-- 分类标签页 -->
    <el-tabs v-model="activeCategory" @tab-change="handleCategoryChange" class="category-tabs">
      <el-tab-pane label="全部" name="all"></el-tab-pane>
      <el-tab-pane
        v-for="category in categories"
        :key="category.id"
        :label="category.name"
        :name="String(category.id)"
      ></el-tab-pane>
    </el-tabs>

    <!-- 合作伙伴列表 -->
    <div class="partners-container">
      <el-table
        :data="partners"
        v-loading="loading"
        border
        stripe
        style="width: 100%"
      >
        <el-table-column type="index" label="#" width="60"></el-table-column>

        <el-table-column label="Logo" width="120">
          <template #default="{ row }">
            <el-image
              :src="row.logoUrl"
              fit="contain"
              style="width: 80px; height: 80px"
              :preview-src-list="[row.logoUrl]"
            >
              <template #error>
                <div class="image-slot">
                  <el-icon><Picture /></el-icon>
                </div>
              </template>
            </el-image>
          </template>
        </el-table-column>

        <el-table-column prop="name" label="名称" min-width="200"></el-table-column>

        <el-table-column label="分类" width="150">
          <template #default="{ row }">
            {{ row.category?.name || '-' }}
          </template>
        </el-table-column>

        <el-table-column label="网站" min-width="200">
          <template #default="{ row }">
            <a v-if="row.websiteUrl" :href="row.websiteUrl" target="_blank" class="website-link">
              {{ row.websiteUrl }}
            </a>
            <span v-else class="text-muted">-</span>
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
      :title="isEdit ? '编辑合作伙伴' : '添加合作伙伴'"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="100px"
      >
        <el-form-item label="名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入合作伙伴名称"></el-input>
        </el-form-item>

        <el-form-item label="分类" prop="category_id">
          <el-select v-model="formData.category_id" placeholder="请选择分类" style="width: 100%">
            <el-option
              v-for="category in categories"
              :key="category.id"
              :label="category.name"
              :value="category.id"
            ></el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="Logo图片" prop="logo_url">
          <el-input v-model="formData.logo_url" placeholder="请输入Logo图片URL"></el-input>
          <div v-if="formData.logo_url" class="image-preview">
            <el-image
              :src="formData.logo_url"
              fit="contain"
              style="width: 120px; height: 120px"
              :preview-src-list="[formData.logo_url]"
            ></el-image>
          </div>
        </el-form-item>

        <el-form-item label="网站地址" prop="website_url">
          <el-input v-model="formData.website_url" placeholder="请输入网站地址（选填）"></el-input>
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
import { Plus, Picture } from '@element-plus/icons-vue'
import {
  getPartners,
  getPartnerCategories,
  createPartner,
  updatePartner,
  deletePartner
} from '@/api/admin'

const loading = ref(false)
const submitting = ref(false)
const activeCategory = ref('all')
const partners = ref<any[]>([])
const categories = ref<any[]>([])

// 对话框相关
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref<FormInstance>()

// 表单数据
const formData = reactive({
  id: 0,
  name: '',
  category_id: 0,
  logo_url: '',
  website_url: '',
  description: '',
  sort_order: 0,
  is_active: true
})

// 表单验证规则
const formRules: FormRules = {
  name: [
    { required: true, message: '请输入合作伙伴名称', trigger: 'blur' }
  ],
  category_id: [
    { required: true, message: '请选择分类', trigger: 'change' }
  ],
  logo_url: [
    { required: true, message: '请输入Logo图片URL', trigger: 'blur' }
  ],
  sort_order: [
    { required: true, message: '请输入排序', trigger: 'blur' }
  ]
}

// 加载分类列表
const loadCategories = async () => {
  try {
    const data = await getPartnerCategories()
    categories.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error('加载分类失败:', error)
    ElMessage.error('加载分类失败')
  }
}

// 加载合作伙伴列表
const loadPartners = async () => {
  try {
    loading.value = true
    const params: any = { limit: 100 }
    if (activeCategory.value !== 'all') {
      params.categoryId = activeCategory.value
    }
    const data = await getPartners(params)
    partners.value = data?.items || []
  } catch (error) {
    console.error('加载合作伙伴失败:', error)
    ElMessage.error('加载合作伙伴失败')
  } finally {
    loading.value = false
  }
}

// 分类切换
const handleCategoryChange = () => {
  loadPartners()
}

// 显示创建对话框
const showCreateDialog = () => {
  isEdit.value = false
  Object.assign(formData, {
    id: 0,
    name: '',
    category_id: categories.value[0]?.id || 0,
    logo_url: '',
    website_url: '',
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
    category_id: row.categoryId,
    logo_url: row.logoUrl,
    website_url: row.websiteUrl || '',
    description: row.description || '',
    sort_order: row.sortOrder,
    is_active: row.isActive
  })
  dialogVisible.value = true
}

// 删除
const handleDelete = (row: any) => {
  ElMessageBox.confirm(
    `确定要删除合作伙伴"${row.name}"吗？`,
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await deletePartner(row.id)
      ElMessage.success('删除成功')
      loadPartners()
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
      category_id: formData.category_id,
      logo_url: formData.logo_url,
      website_url: formData.website_url,
      description: formData.description,
      sort_order: formData.sort_order,
      is_active: formData.is_active
    }

    if (isEdit.value) {
      await updatePartner(formData.id, data)
      ElMessage.success('更新成功')
    } else {
      await createPartner(data)
      ElMessage.success('创建成功')
    }

    dialogVisible.value = false
    loadPartners()
  } catch (error: any) {
    console.error('提交失败:', error)
    if (error !== false) { // 不是验证错误
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
  loadPartners()
})
</script>

<style scoped lang="scss">
.partners-admin {
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

.category-tabs {
  margin-bottom: 20px;
  background: #fff;
  padding: 10px 20px 0;
  border-radius: 4px;
}

.partners-container {
  background: #fff;
  padding: 20px;
  border-radius: 4px;
}

.website-link {
  color: #409eff;
  text-decoration: none;

  &:hover {
    text-decoration: underline;
  }
}

.text-muted {
  color: #999;
}

.image-slot {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 80px;
  height: 80px;
  background: #f5f7fa;
  color: #909399;
  font-size: 30px;
}

.image-preview {
  margin-top: 10px;
  display: flex;
  justify-content: center;
  padding: 10px;
  background: #f5f7fa;
  border-radius: 4px;
}
</style>
