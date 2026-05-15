<template>
  <div style="padding: 20px;">
    <h1>API测试页面</h1>

    <div style="margin: 20px 0; padding: 15px; border: 1px solid #ddd;">
      <h2>1. 检查request工具</h2>
      <el-button @click="testRequest" :loading="loading1">测试request</el-button>
      <div v-if="result1" style="margin-top: 10px;">
        <p><strong>类型:</strong> {{ result1.type }}</p>
        <p><strong>是否为数组:</strong> {{ result1.isArray }}</p>
        <p><strong>长度:</strong> {{ result1.length }}</p>
        <pre style="background: #f5f5f5; padding: 10px; max-height: 200px; overflow: auto;">{{ result1.data }}</pre>
      </div>
    </div>

    <div style="margin: 20px 0; padding: 15px; border: 1px solid #ddd;">
      <h2>2. 检查fetch直接调用</h2>
      <el-button @click="testFetch" :loading="loading2">测试fetch</el-button>
      <div v-if="result2" style="margin-top: 10px;">
        <p><strong>状态码:</strong> {{ result2.status }}</p>
        <pre style="background: #f5f5f5; padding: 10px; max-height: 200px; overflow: auto;">{{ result2.data }}</pre>
      </div>
    </div>

    <div style="margin: 20px 0; padding: 15px; border: 1px solid #ddd;">
      <h2>3. 检查环境变量</h2>
      <el-button @click="checkEnv">检查环境</el-button>
      <div v-if="result3" style="margin-top: 10px;">
        <pre style="background: #f5f5f5; padding: 10px;">{{ result3 }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { getPartnerCategories } from '@/api/admin'

const loading1 = ref(false)
const loading2 = ref(false)
const result1 = ref<any>(null)
const result2 = ref<any>(null)
const result3 = ref<any>(null)

const testRequest = async () => {
  loading1.value = true
  try {
    console.log('=== 开始测试request ===')
    const data = await getPartnerCategories()
    console.log('request返回数据:', data)
    console.log('数据类型:', typeof data)
    console.log('是否为数组:', Array.isArray(data))
    console.log('数据长度:', Array.isArray(data) ? data.length : 'N/A')

    result1.value = {
      type: typeof data,
      isArray: Array.isArray(data),
      length: Array.isArray(data) ? data.length : 'N/A',
      data: JSON.stringify(data, null, 2)
    }
  } catch (error: any) {
    console.error('request错误:', error)
    result1.value = {
      error: error.message,
      stack: error.stack
    }
  } finally {
    loading1.value = false
  }
}

const testFetch = async () => {
  loading2.value = true
  try {
    console.log('=== 开始测试fetch ===')
    const response = await fetch('/api/v1/admin/partner-categories')
    console.log('fetch响应:', response)

    const data = await response.json()
    console.log('fetch数据:', data)

    result2.value = {
      status: response.status,
      data: JSON.stringify(data, null, 2)
    }
  } catch (error: any) {
    console.error('fetch错误:', error)
    result2.value = {
      error: error.message,
      stack: error.stack
    }
  } finally {
    loading2.value = false
  }
}

const checkEnv = () => {
  result3.value = {
    'VITE_API_BASE_URL': import.meta.env.VITE_API_BASE_URL,
    'BASE_URL': import.meta.env.BASE_URL,
    'MODE': import.meta.env.MODE,
    'DEV': import.meta.env.DEV,
    'PROD': import.meta.env.PROD
  }
}
</script>
