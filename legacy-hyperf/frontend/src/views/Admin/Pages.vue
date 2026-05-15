<template>
  <div class="pages-list">
    <div class="page-header">
      <h2>页面管理</h2>
      <el-button type="primary" @click="createPage">
        <el-icon><Plus /></el-icon>
        新建页面
      </el-button>
    </div>

    <el-table :data="pages" stripe>
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="title" label="页面标题" />
      <el-table-column prop="slug" label="标识符" />
      <el-table-column prop="updated_at" label="更新时间" width="180">
        <template #default="{ row }">
          {{ formatDate(row.updated_at) }}
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" size="small" @click="editPage(row)">
            编辑
          </el-button>
          <el-button type="danger" size="small" @click="deletePage(row)">
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 编辑对话框 -->
    <el-dialog
      v-model="showEditor"
      :title="editingPage?.title || '新建页面'"
      width="90%"
      destroy-on-close
    >
      <el-form :model="pageForm" label-width="100px">
        <el-form-item label="页面标题">
          <el-input v-model="pageForm.title" placeholder="请输入页面标题" />
        </el-form-item>
        <el-form-item label="标识符">
          <el-input v-model="pageForm.slug" placeholder="请输入页面标识符（英文）" />
        </el-form-item>
        <el-form-item label="页面内容">
          <div class="editor-container">
            <div v-if="!showEditor" class="editor-placeholder" @click="initEditor">
              <el-icon><Edit /></el-icon>
              <span>点击编辑富文本内容</span>
            </div>
            <div v-show="showEditor" ref="editorRef" class="editor-wrapper"></div>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEditor = false">取消</el-button>
        <el-button type="primary" @click="savePage" :loading="saving">
          保存
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Edit } from '@element-plus/icons-vue'
import { request } from '@/api/index'
import E from 'wangeditor'

const pages = ref([])
const showEditor = ref(false)
const editingPage = ref<any>(null)
const saving = ref(false)
const editorRef = ref<HTMLElement>()
let editor: any = null

const pageForm = ref({
  id: null,
  title: '',
  slug: '',
  content: ''
})

// 加载页面列表
const loadPages = async () => {
  try {
    const data = await request.get('/admin/pages')
    pages.value = data.items || []
  } catch (error) {
    console.error('加载页面列表失败:', error)
    ElMessage.error('加载页面列表失败')
  }
}

// 初始化富文本编辑器
const initEditor = async () => {
  showEditor.value = true
  await nextTick()

  if (!editorRef.value) return

  // 销毁旧编辑器
  if (editor) {
    editor.destroy()
    editor = null
  }

  // 创建新编辑器
  editor = new E(editorRef.value)

  // 配置编辑器
  editor.config.zIndex = 100
  editor.config.placeholder = '请输入页面内容...'
  editor.config.showFullScreen = true

  // 设置内容
  if (pageForm.value.content) {
    editor.txt.html(pageForm.value.content)
  }

  // 配置菜单
  editor.config.menus = [
    'head',
    'bold',
    'fontSize',
    'fontName',
    'italic',
    'underline',
    'strikeThrough',
    'foreColor',
    'backColor',
    'link',
    'list',
    'justify',
    'quote',
    'image',
    'video',
    'table',
    'code',
    'splitLine',
    'undo',
    'redo'
  ]
}

const createPage = () => {
  editingPage.value = null
  pageForm.value = {
    id: null,
    title: '',
    slug: '',
    content: ''
  }
  showEditor.value = true
  nextTick(() => {
    initEditor()
  })
}

const editPage = (page: any) => {
  editingPage.value = page
  pageForm.value = {
    id: page.id,
    title: page.title,
    slug: page.slug,
    content: page.content || ''
  }
  showEditor.value = true
  nextTick(() => {
    initEditor()
  })
}

const savePage = async () => {
  if (!pageForm.value.title || !pageForm.value.slug) {
    ElMessage.warning('请填写页面标题和标识符')
    return
  }

  // 获取编辑器内容
  if (editor) {
    pageForm.value.content = editor.txt.html()
  }

  try {
    saving.value = true
    const url = pageForm.value.id
      ? `/admin/pages/${pageForm.value.id}`
      : '/admin/pages'

    await request({
      url,
      method: pageForm.value.id ? 'put' : 'post',
      data: pageForm.value
    })

    ElMessage.success('保存成功')
    showEditor.value = false
    loadPages()
  } catch (error) {
    console.error('保存页面失败:', error)
    ElMessage.error('保存页面失败')
  } finally {
    saving.value = false
  }
}

const deletePage = (page: any) => {
  ElMessageBox.confirm(
    `确定要删除页面"${page.title}"吗？此操作不可恢复。`,
    '确认删除',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await request.delete(`/admin/pages/${page.id}`)
      ElMessage.success('删除成功')
      loadPages()
    } catch (error) {
      console.error('删除页面失败:', error)
      ElMessage.error('删除页面失败')
    }
  }).catch(() => {})
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('zh-CN')
}

onMounted(() => {
  loadPages()
})

onBeforeUnmount(() => {
  if (editor) {
    editor.destroy()
    editor = null
  }
})
</script>

<style scoped lang="scss">
.pages-list {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;

  h2 {
    margin: 0;
  }
}

.editor-container {
  width: 100%;
  min-height: 400px;
  border: 1px solid #dcdfe6;
  border-radius: 4px;

  .editor-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 400px;
    cursor: pointer;
    color: #909399;
    transition: all 0.3s ease;

    &:hover {
      color: var(--el-color-primary);
      background: #f5f7fa;
    }

    .el-icon {
      font-size: 48px;
      margin-bottom: 12px;
    }
  }

  .editor-wrapper {
    min-height: 400px;
  }
}
</style>
