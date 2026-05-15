<template>
  <div class="debug-partners" style="padding: 20px;">
    <h1>合作伙伴数据调试</h1>

    <div style="margin: 20px 0;">
      <h2>1. 测试分类API</h2>
      <el-button @click="testCategories" :loading="loadingCategories">测试分类API</el-button>
      <div v-if="categoriesData" style="margin-top: 10px;">
        <p><strong>结果:</strong></p>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">{{ JSON.stringify(categoriesData, null, 2) }}</pre>
      </div>
    </div>

    <div style="margin: 20px 0;">
      <h2>2. 测试合作伙伴API</h2>
      <el-button @click="testPartners" :loading="loadingPartners">测试合作伙伴API</el-button>
      <div v-if="partnersData" style="margin-top: 10px;">
        <p><strong>结果:</strong></p>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; max-height: 400px;">{{ JSON.stringify(partnersData, null, 2) }}</pre>
      </div>
    </div>

    <div style="margin: 20px 0;">
      <h2>3. 测试API模块</h2>
      <el-button @click="testApiModule" :loading="loadingApiModule">测试API模块</el-button>
      <div v-if="apiModuleResult" style="margin-top: 10px;">
        <p><strong>结果:</strong></p>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">{{ JSON.stringify(apiModuleResult, null, 2) }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { getPartnerCategories, getPartners } from '@/api/admin'

const loadingCategories = ref(false)
const loadingPartners = ref(false)
const loadingApiModule = ref(false)

const categoriesData = ref(null)
const partnersData = ref(null)
const apiModuleResult = ref(null)

// 直接fetch测试
const testCategories = async () => {
  loadingCategories.value = true
  try {
    const response = await fetch('/api/v1/admin/partner-categories')
    const result = await response.json()
    console.log('分类API原始响应:', result)
    categoriesData.value = result
  } catch (error) {
    console.error('错误:', error)
    categoriesData.value = { error: error.message }
  } finally {
    loadingCategories.value = false
  }
}

const testPartners = async () => {
  loadingPartners.value = true
  try {
    const response = await fetch('/api/v1/admin/partners-list?limit=3')
    const result = await response.json()
    console.log('合作伙伴API原始响应:', result)
    partnersData.value = result
  } catch (error) {
    console.error('错误:', error)
    partnersData.value = { error: error.message }
  } finally {
    loadingPartners.value = false
  }
}

// 测试API模块
const testApiModule = async () => {
  loadingApiModule.value = true
  try {
    console.log('调用getPartnerCategories...')
    const categories = await getPartnerCategories()
    console.log('getPartnerCategories返回:', categories)

    console.log('调用getPartners...')
    const partners = await getPartners({ limit: 3 })
    console.log('getPartners返回:', partners)

    apiModuleResult.value = {
      categoriesType: typeof categories,
      categoriesIsArray: Array.isArray(categories),
      categoriesLength: Array.isArray(categories) ? categories.length : 'N/A',
      categoriesFirst: Array.isArray(categories) ? categories[0] : categories,
      partnersType: typeof partners,
      partnersItems: partners?.items,
      partnersMeta: partners?.meta
    }
  } catch (error) {
    console.error('错误:', error)
    apiModuleResult.value = { error: error.message, stack: error.stack }
  } finally {
    loadingApiModule.value = false
  }
}
</script>
