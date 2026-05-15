import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Project } from '@/types'
import { projectsApi } from '@/api/projects'

export const useProjectStore = defineStore('project', () => {
  // 状态
  const featuredProjects = ref<Project[]>([])
  const loading = ref(false)

  /**
   * 加载精选项目
   */
  const loadFeaturedProjects = async (limit = 6) => {
    loading.value = true
    try {
      const data = await projectsApi.getFeatured(limit)
      featuredProjects.value = data.items
    } catch (error) {
      console.error('Failed to load featured projects:', error)
    } finally {
      loading.value = false
    }
  }

  return {
    featuredProjects,
    loading,
    loadFeaturedProjects
  }
})
