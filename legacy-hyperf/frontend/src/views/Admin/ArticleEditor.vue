<template>
  <div class="article-editor">
    <el-card shadow="never">
      <template #header>
        <div class="editor-header">
          <h3>{{ isEdit ? '编辑文章' : '新建文章' }}</h3>
          <div class="header-actions">
            <el-button @click="handleCancel">取消</el-button>
            <el-button type="primary" :loading="saving" @click="handleSave">
              <Icon name="check" :size="16" />
              保存
            </el-button>
          </div>
        </div>
      </template>

      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="120px"
        class="article-form"
      >
        <el-form-item label="文章标题" prop="title">
          <el-input
            v-model="formData.title"
            placeholder="请输入文章标题"
            maxlength="200"
            show-word-limit
          />
        </el-form-item>

        <el-form-item label="文章摘要" prop="summary">
          <el-input
            v-model="formData.summary"
            type="textarea"
            :rows="3"
            placeholder="请输入文章摘要"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>

        <el-form-item label="分类" prop="categoryId">
          <el-cascader
            v-model="formData.categoryId"
            :options="categoryOptions"
            :props="cascaderProps"
            placeholder="请选择分类"
            clearable
            filterable
            style="width: 100%;"
            @change="handleCategoryChange"
          />
          <div class="form-tip">支持多级分类选择</div>
        </el-form-item>

        <el-form-item label="封面图片">
          <div class="cover-upload">
            <el-upload
              class="cover-uploader"
              :show-file-list="false"
              :before-upload="handleCoverUpload"
              :http-request="() => {}"
              :disabled="uploading"
              accept="image/*"
            >
              <div v-if="uploading" class="upload-loading">
                <el-icon class="is-loading"><Loading /></el-icon>
                <div>上传中...</div>
              </div>
              <img v-else-if="formData.coverImage" :src="formData.coverImage" class="cover-image" />
              <div v-else class="upload-placeholder">
                <Icon name="upload" :size="40" color="#ccc" />
                <div>点击上传封面图</div>
                <div class="upload-tip">建议尺寸：800x450px</div>
              </div>
            </el-upload>
            <el-button
              v-if="formData.coverImage"
              type="danger"
              size="small"
              @click="formData.coverImage = ''"
            >
              删除封面
            </el-button>
          </div>
        </el-form-item>

        <el-form-item label="文章内容" prop="content">
          <div class="editor-container">
            <el-input
              v-model="formData.content"
              type="textarea"
              :rows="20"
              placeholder="请输入文章内容，支持 HTML 格式"
            />
          </div>
        </el-form-item>

        <el-form-item label="发布日期" prop="publishedDate">
          <el-date-picker
            v-model="formData.publishedDate"
            type="date"
            placeholder="选择发布日期"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
          />
        </el-form-item>

        <el-form-item label="排序">
          <el-input-number v-model="formData.sortOrder" :min="0" />
        </el-form-item>

        <el-form-item label="选项">
          <el-checkbox v-model="formData.isFeatured">设为精选</el-checkbox>
          <el-checkbox v-model="formData.isPinned">设为置顶</el-checkbox>
        </el-form-item>

        <el-form-item label="状态">
          <el-radio-group v-model="formData.status">
            <el-radio label="published">已发布</el-radio>
            <el-radio label="draft">草稿</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import Icon from '@/components/Icon.vue'
import * as adminApi from '@/api/admin'

const router = useRouter()
const route = useRoute()
const formRef = ref<FormInstance>()
const saving = ref(false)
const isEdit = ref(false)
const categories = ref([])
const categoryOptions = ref([])
const uploading = ref(false)

// Cascader 配置
const cascaderProps = {
  value: 'id',
  label: 'name',
  children: 'children',
  emitPath: false, // 只返回最后一级的值
  checkStrictly: true,
  expandTrigger: 'hover' as const
}

const formData = reactive({
  title: '',
  summary: '',
  content: '',
  coverImage: '',
  categoryId: null as number | null,
  publishedDate: '',
  sortOrder: 0,
  isFeatured: false,
  isPinned: false,
  status: 'published'
})

const formRules: FormRules = {
  title: [
    { required: true, message: '请输入文章标题', trigger: 'blur' }
  ],
  content: [
    { required: true, message: '请输入文章内容', trigger: 'blur' }
  ],
  publishedDate: [
    { required: true, message: '请选择发布日期', trigger: 'change' }
  ]
}

// 加载分类
const loadCategories = async () => {
  try {
    const response = await adminApi.getCategories({
      type: 'article',
      include_inactive: true
    })

    if (response && Array.isArray(response)) {
      categories.value = response
      // 转换为cascader需要的格式，同时保留子分类
      categoryOptions.value = buildCategoryTree(response)
    }
  } catch (error) {
    console.error('加载分类失败:', error)
    ElMessage.error('加载分类失败')
  }
}

// 构建分类树（用于级联选择器）
const buildCategoryTree = (categories: any[]): any[] => {
  const result: any[] = []
  const categoryMap = new Map()

  // 先将所有分类放入map
  categories.forEach(category => {
    categoryMap.set(category.id, { ...category, children: [] })
  })

  // 构建树形结构
  categories.forEach(category => {
    if (category.parent_id === 0) {
      // 顶级分类
      result.push(categoryMap.get(category.id))
    } else {
      // 子分类
      const parent = categoryMap.get(category.parent_id)
      if (parent) {
        parent.children.push(categoryMap.get(category.id))
      } else {
        // 如果找不到父分类，作为顶级分类处理
        result.push(categoryMap.get(category.id))
      }
    }
  })

  return result
}

// 分类变化处理
const handleCategoryChange = (value: number) => {
  console.log('选择的分类ID:', value)
}

// 加载文章详情
const loadArticle = async (id: number) => {
  try {
    const response = await adminApi.getArticle(id)
    if (response) {
      Object.assign(formData, {
        title: response.title || '',
        summary: response.summary || '',
        content: response.content || '',
        coverImage: response.coverImage || '',
        categoryId: response.categoryId || response.category?.id || null,
        publishedDate: response.publishedDate || new Date().toISOString().split('T')[0],
        sortOrder: response.sortOrder || 0,
        isFeatured: response.isFeatured || false,
        isPinned: response.isPinned || false,
        status: response.status || 'published'
      })
    }
  } catch (error) {
    console.error('加载文章失败:', error)
    ElMessage.error('加载文章失败')
  }
}

// 上传前验证
const beforeCoverUpload = (file: File) => {
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

// 封面上传处理
const handleCoverUpload = async (file: File) => {
  // 验证文件
  const isValid = beforeCoverUpload(file)
  if (!isValid) return false

  uploading.value = true

  try {
    // 直接调用上传接口，后端会处理所有上传逻辑
    const uploadData = await adminApi.uploadFileForVue(file, 'images')

    // 使用返回的URL
    if (uploadData.url) {
      formData.coverImage = uploadData.url
      ElMessage.success('封面上传成功')
    } else {
      ElMessage.error('上传失败：未获取到文件URL')
    }

    // 阻止默认上传行为
    return false
  } catch (error: any) {
    console.error('上传失败:', error)
    ElMessage.error(error.message || '封面上传失败')
    return false
  } finally {
    uploading.value = false
  }
}

// 保存文章
const handleSave = async () => {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    saving.value = true

    const articleId = route.params.id
    const payload = {
      title: formData.title,
      summary: formData.summary,
      content: formData.content,
      cover_image: formData.coverImage,
      category_id: formData.categoryId,
      published_date: formData.publishedDate,
      sort_order: formData.sortOrder,
      is_featured: formData.isFeatured,
      is_pinned: formData.isPinned,
      status: formData.status
    }

    if (isEdit.value && articleId) {
      await adminApi.updateArticle(Number(articleId), payload)
      ElMessage.success('更新成功')
    } else {
      await adminApi.createArticle(payload)
      ElMessage.success('创建成功')
    }

    router.push('/admin/articles')
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 取消
const handleCancel = () => {
  router.back()
}

onMounted(async () => {
  await loadCategories()

  const articleId = route.params.id
  if (articleId && articleId !== 'new') {
    isEdit.value = true
    await loadArticle(Number(articleId))
  }
})
</script>

<style lang="scss" scoped>
.article-editor {
  .editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    h3 {
      font-size: 18px;
      color: #303133;
      margin: 0;
    }
  }

  .article-form {
    max-width: 900px;

    .editor-container {
      width: 100%;
    }

    .form-tip {
      font-size: 12px;
      color: #909399;
      margin-top: 4px;
    }
  }

  .cover-upload {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }

  .cover-uploader {
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

  .upload-loading {
    width: 400px;
    height: 225px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #409eff;
    background: #f5f7fa;
    gap: 12px;

    .el-icon {
      font-size: 40px;
    }
  }

  .cover-image {
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
    gap: 8px;

    .upload-tip {
      font-size: 12px;
      color: #999;
    }
  }
}
</style>
