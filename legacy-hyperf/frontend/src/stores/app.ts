import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { NavigationItem, Slide } from '@/types'
import { commonApi } from '@/api/common'

export const useAppStore = defineStore('app', () => {
  // 状态
  const navigation = ref<NavigationItem[]>([])
  const slides = ref<Slide[]>([])
  const loading = ref(false)

  /**
   * 加载导航菜单
   */
  const loadNavigation = async () => {
    try {
      const data = await commonApi.getNavigation()
      navigation.value = data.items
    } catch (error) {
      console.error('Failed to load navigation:', error)
    }
  }

  /**
   * 加载轮播图
   */
  const loadSlides = async () => {
    try {
      const data = await commonApi.getSlides()
      // 确保只加载启用的轮播图
      slides.value = (data.items || []).filter((slide: Slide) => slide.is_active)

      console.log('加载轮播图成功:', slides.value.length, '条')
    } catch (error) {
      console.error('Failed to load slides:', error)
      // 失败时使用空数组，不使用mock数据
      slides.value = []
    }
  }

  /**
   * 初始化应用数据
   */
  const initApp = async () => {
    loading.value = true
    try {
      await Promise.all([
        loadNavigation(),
        loadSlides()
      ])
    } finally {
      loading.value = false
    }
  }

  return {
    navigation,
    slides,
    loading,
    loadNavigation,
    loadSlides,
    initApp
  }
})
