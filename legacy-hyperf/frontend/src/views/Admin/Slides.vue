<template>
  <div class="admin-slides">
    <div class="page-header">
      <h3>轮播图管理</h3>
      <el-button type="primary" @click="handleAdd">
        <Icon name="add" :size="18" />
        新建轮播图
      </el-button>
    </div>

    <!-- 轮播图列表 -->
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
        <el-table-column label="图片" width="200">
          <template #default="{ row }">
            <el-image
              :src="row.image"
              fit="cover"
              style="width: 160px; height: 90px; border-radius: 8px;"
              :preview-src-list="[row.image]"
            >
              <template #error>
                <div class="image-slot">
                  <Icon name="picture" :size="32" color="#ccc" />
                </div>
              </template>
            </el-image>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="150" show-overflow-tooltip />
        <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
        <el-table-column prop="linkText" label="链接文字" width="120" />
        <el-table-column prop="linkUrl" label="链接地址" min-width="150" show-overflow-tooltip />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.isActive ? 'success' : 'info'" size="small">
              {{ row.isActive ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sortOrder" label="排序" width="80" />
        <el-table-column label="操作" width="250" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleToggleActive(row)">
              <Icon name="check" :size="14" />
              {{ row.isActive ? '禁用' : '启用' }}
            </el-button>
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
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>

    <!-- 编辑对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑轮播图' : '新建轮播图'"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="100px"
      >
        <el-form-item label="标题" prop="title">
          <el-input v-model="formData.title" placeholder="请输入标题" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input
            v-model="formData.description"
            type="textarea"
            :rows="3"
            placeholder="请输入描述"
          />
        </el-form-item>
        <el-form-item label="背景图片" prop="image">
          <el-upload
            class="slide-uploader"
            :show-file-list="false"
            :on-success="handleUploadSuccess"
            :before-upload="beforeUpload"
            action="/api/v1/admin/upload"
          >
            <img v-if="formData.image" :src="formData.image" class="slide-image" />
            <div v-else class="upload-placeholder">
              <Icon name="upload" :size="32" color="#ccc" />
              <div>点击上传图片</div>
            </div>
          </el-upload>
        </el-form-item>
        <el-form-item label="链接文字" prop="linkText">
          <el-input v-model="formData.linkText" placeholder="例如：了解更多" />
        </el-form-item>
        <el-form-item label="链接地址" prop="linkUrl">
          <el-input v-model="formData.linkUrl" placeholder="例如：/projects/1" />
        </el-form-item>
        <el-form-item label="排序" prop="sortOrder">
          <el-input-number v-model="formData.sortOrder" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="formData.isActive" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
          确定
        </el-button>
      </template>
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
const isEdit = ref(false)
const submitLoading = ref(false)
const formRef = ref<FormInstance>()
const currentEditId = ref<number | null>(null)

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格数据
const tableData = ref([])

// 表单数据
const formData = reactive({
  title: '',
  description: '',
  image: '',
  linkText: '',
  linkUrl: '',
  sortOrder: 0,
  isActive: true
})

// 表单验证规则
const formRules: FormRules = {
  title: [
    { required: true, message: '请输入标题', trigger: 'blur' }
  ],
  image: [
    { required: true, message: '请上传图片', trigger: 'change' }
  ]
}

// 加载轮播图列表
const loadSlides = async () => {
  try {
    loading.value = true
    const response = await adminApi.getSlides()

    // 响应拦截器已经提取了 data 字段，直接访问 items
    if (response?.items) {
      tableData.value = response.items as any
      pagination.total = response.total || response.items.length

      // Admin API 已经返回驼峰命名，直接使用即可
      tableData.value = tableData.value.map((item: any) => ({
        id: item.id,
        title: item.title,
        description: item.description,
        image: item.image,
        linkText: item.linkText || '了解更多',
        linkUrl: item.linkUrl || '',
        isActive: item.isActive ?? true,
        sortOrder: item.sortOrder ?? 0,
        createdAt: item.createdAt || ''
      }))
    }
  } catch (error) {
    console.error('加载轮播图列表失败:', error)
    ElMessage.error('加载轮播图列表失败')
  } finally {
    loading.value = false
  }
}

// 新建
const handleAdd = () => {
  isEdit.value = false
  currentEditId.value = null
  resetForm()
  dialogVisible.value = true
}

// 编辑
const handleEdit = (row: any) => {
  isEdit.value = true
  currentEditId.value = row.id
  Object.assign(formData, {
    title: row.title,
    description: row.description,
    image: row.image,
    linkText: row.linkText,
    linkUrl: row.linkUrl,
    sortOrder: row.sortOrder,
    isActive: row.isActive
  })
  dialogVisible.value = true
}

// 删除
const handleDelete = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除轮播图"${row.title}"吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    loading.value = true
    await adminApi.deleteSlide(row.id)

    ElMessage.success('删除成功')
    await loadSlides()
  } catch (error: any) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  } finally {
    loading.value = false
  }
}

// 切换状态
const handleToggleActive = async (row: any) => {
  try {
    loading.value = true
    await adminApi.updateSlide(row.id, {
      ...row,
      isActive: !row.isActive
    })

    ElMessage.success(`${row.isActive ? '禁用' : '启用'}成功`)
    await loadSlides()
  } catch (error) {
    console.error('操作失败:', error)
    ElMessage.error('操作失败')
  } finally {
    loading.value = false
  }
}

// 上传前验证
const beforeUpload = (file: File) => {
  const isImage = file.type.startsWith('image/')
  const isLt5M = file.size / 1024 / 1024 < 5

  if (!isImage) {
    ElMessage.error('只能上传图片文件!')
    return false
  }
  if (!isLt5M) {
    ElMessage.error('图片大小不能超过 5MB!')
    return false
  }
  return true
}

// 上传成功
const handleUploadSuccess = (response: any) => {
  if (response?.data?.url) {
    formData.image = response.data.url
    ElMessage.success('图片上传成功')
  }
}

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    submitLoading.value = true

    if (isEdit.value && currentEditId.value) {
      await adminApi.updateSlide(currentEditId.value, formData)
      ElMessage.success('更新成功')
    } else {
      await adminApi.createSlide(formData)
      ElMessage.success('创建成功')
    }

    dialogVisible.value = false
    await loadSlides()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error('提交失败')
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
    title: '',
    description: '',
    image: '',
    linkText: '',
    linkUrl: '',
    sortOrder: 0,
    isActive: true
  })
  formRef.value?.resetFields()
}

// 分页大小变化
const handleSizeChange = (size: number) => {
  pagination.pageSize = size
  pagination.page = 1
  loadSlides()
}

// 当前页变化
const handleCurrentChange = (page: number) => {
  pagination.page = page
  loadSlides()
}

onMounted(() => {
  loadSlides()
})
</script>

<style lang="scss" scoped>
.admin-slides {
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

  .table-card {
    .pagination {
      margin-top: 20px;
      display: flex;
      justify-content: flex-end;
    }
  }

  .image-slot {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: #f5f7fa;
    border-radius: 8px;
  }

  .slide-uploader {
    :deep(.el-upload) {
      border: 1px dashed #d9d9d9;
      border-radius: 6px;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: all 0.3s;

      &:hover {
        border-color: #409eff;
      }
    }
  }

  .slide-image {
    width: 400px;
    height: 225px;
    display: block;
    object-fit: cover;
  }

  .upload-placeholder {
    width: 400px;
    height: 225px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #8c939d;
    background: #f5f7fa;
  }
}
</style>
