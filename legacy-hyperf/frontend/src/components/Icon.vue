<template>
  <Icon :icon="iconName" :width="size" :height="size" :color="color" />
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { computed } from 'vue'

const props = defineProps<{
  name: string
  size?: number | string
  color?: string
}>()

// 图标名称映射到流行的图标集
const iconName = computed(() => {
  const name = props.name.toLowerCase()

  // Heroicons 图标集 - Tailwind CSS 官方推荐
  const heroiconsMap: Record<string, string> = {
    // 基础图标
    'home': 'heroicons:home',
    'user': 'heroicons:user',
    'users': 'heroicons:users',
    'settings': 'heroicons:cog-6-tooth',
    'menu': 'heroicons:bars-3',
    'close': 'heroicons:x-mark',
    'check': 'heroicons:check',
    'search': 'heroicons:magnifying-glass',
    'filter': 'heroicons:funnel',
    'refresh': 'heroicons:arrow-path',
    'delete': 'heroicons:trash',
    'edit': 'heroicons:pencil',
    'add': 'heroicons:plus',
    'plus': 'heroicons:plus',
    'upload': 'heroicons:arrow-up-tray',
    'download': 'heroicons:arrow-down-tray',
    'eye': 'heroicons:eye',
    'eye-off': 'heroicons:eye-slash',
    'lock': 'heroicons:lock-closed',
    'unlock': 'heroicons:lock-open',
    'bell': 'heroicons:bell',
    'calendar': 'heroicons:calendar-days',
    'clock': 'heroicons:clock',
    'star': 'heroicons:star',
    'heart': 'heroicons:heart',
    'donations': 'heroicons:heart',
    'shield': 'heroicons:shield-check',
    'warning': 'heroicons:exclamation-triangle',
    'info': 'heroicons:information-circle',
    'success': 'heroicons:check-circle',
    'error': 'heroicons:x-circle',
    'logout': 'heroicons:arrow-right-on-rectangle',
    'login': 'heroicons:arrow-left-on-rectangle',

    // 方向图标
    'arrow-up': 'heroicons:arrow-up',
    'arrow-down': 'heroicons:arrow-down',
    'arrow-left': 'heroicons:arrow-left',
    'arrow-right': 'heroicons:arrow-right',
    'chevron-up': 'heroicons:chevron-up',
    'chevron-down': 'heroicons:chevron-down',
    'chevron-left': 'heroicons:chevron-left',
    'chevron-right': 'heroicons:chevron-right',

    // 折叠
    'fold': 'heroicons:bars-3-bottom-left',
    'unfold': 'heroicons:bars-3-bottom-right',
    'expand': 'heroicons:arrows-pointing-out',
    'fullscreen': 'heroicons:arrows-pointing-out',
    'exit-fullscreen': 'heroicons:arrows-pointing-in',

    // 内容相关
    'article': 'heroicons:document-text',
    'articles': 'heroicons:document-duplicate',
    'file': 'heroicons:document',
    'folder': 'heroicons:folder',
    'image': 'heroicons:photo',
    'picture': 'heroicons:photo',
    'video': 'heroicons:video-camera',
    'link': 'heroicons:link',
    'book': 'heroicons:book-open',
    'copy': 'heroicons:clipboard-document',

    // 数据相关
    'chart': 'heroicons:chart-bar',
    'trend': 'heroicons:arrow-trending-up',
    'database': 'heroicons:circle-stack',
    'data': 'heroicons:chart-pie',
    'analytics': 'heroicons:presentation-chart-bar',

    // 操作相关
    'save': 'heroicons:bookmark',
    'share': 'heroicons:share-network',
    'print': 'heroicons:printer',
    'zoom-in': 'heroicons:magnifying-glass-plus',
    'zoom-out': 'heroicons:magnifying-glass-minus',
    'more': 'heroicons:ellipsis-horizontal',
    'more-vertical': 'heroicons:ellipsis-vertical',
  }

  // Lucide 图标集 - 优雅的线条图标
  const lucideMap: Record<string, string> = {
    // 仪表板
    'dashboard': 'lucide:layout-dashboard',
    'layout': 'lucide:layout',
    'grid': 'lucide:grid',
    'list': 'lucide:list',
    'table': 'lucide:table',

    // 导航
    'navigation': 'lucide:navigation',
    'menu': 'lucide:menu',
    'more': 'lucide:more-horizontal',

    // 编辑
    'edit': 'lucide:edit-2',
    'trash': 'lucide:trash-2',
    'copy': 'lucide:copy',
    'cut': 'lucide:scissors',
    'paste': 'lucide:clipboard',

    // 文件
    'file': 'lucide:file',
    'files': 'lucide:files',
    'folder': 'lucide:folder',
    'folder-open': 'lucide:folder-open',
    'download': 'lucide:download',
    'upload': 'lucide:upload',

    // 系统
    'settings': 'lucide:settings',
    'tools': 'lucide:wrench',
    'log': 'lucide:scroll-text',
    'monitor': 'lucide:monitor',
    'desktop': 'lucide:monitor',
    'smartphone': 'lucide:smartphone',
    'mobile': 'lucide:smartphone',
    'tablet': 'lucide:tablet',
    'refresh-cw': 'lucide:refresh-cw',
  }

  // Tabler Icons 图标集 - 现代设计
  const tablerMap: Record<string, string> = {
    'dashboard': 'tabler:dashboard',
    'home': 'tabler:home',
    'settings': 'tabler:settings',
    'user': 'tabler:user',
    'users': 'tabler:users',
    'chart': 'tabler:chart-bar',
    'trend': 'tabler:trending-up',
    'refresh': 'tabler:refresh',
    'search': 'tabler:search',
    'filter': 'tabler:filter',
    'edit': 'tabler:edit',
    'delete': 'tabler:trash',
    'add': 'tabler:plus',
    'close': 'tabler:x',
    'check': 'tabler:check',
    'lock': 'tabler:lock',
    'unlock': 'tabler:unlock',
    'bell': 'tabler:bell',
    'calendar': 'tabler:calendar',
    'clock': 'tabler:clock',
    'eye': 'tabler:eye',
    'eye-off': 'tabler:eye-off',
    'star': 'tabler:star',
    'heart': 'tabler:heart',
    'shield': 'tabler:shield',
    'logout': 'tabler:logout',
    'login': 'tabler:login',
    'arrow-up': 'tabler:arrow-up',
    'arrow-down': 'tabler:arrow-down',
    'arrow-left': 'tabler:arrow-left',
    'arrow-right': 'tabler:arrow-right',
    'chevron-up': 'tabler:chevron-up',
    'chevron-down': 'tabler:chevron-down',
    'chevron-left': 'tabler:chevron-left',
    'chevron-right': 'tabler:chevron-right',
    'fold': 'tabler:menu-2',
    'unfold': 'tabler:layout',
    'expand': 'tabler:maximize',
    'fullscreen': 'tabler:maximize',
    'article': 'tabler:article',
    'articles': 'tabler:articles',
    'file': 'tabler:file-text',
    'folder': 'tabler:folder',
    'image': 'tabler:photo',
    'picture': 'tabler:photo',
    'video': 'tabler:video',
    'link': 'tabler:link',
    'copy': 'tabler:copy',
    'save': 'tabler:device-floppy',
    'share': 'tabler:share',
    'print': 'tabler:print',
    'zoom-in': 'tabler:zoom-in',
    'zoom-out': 'tabler:zoom-out',
    'more': 'tabler:dots',
    'more-vertical': 'tabler:dots-vertical',
  }

  // 优先使用 Heroicons，然后 Lucide，最后 Tabler
  return heroiconsMap[name] || lucideMap[name] || tablerMap[name] || `lucide:${name}`
})

const size = computed(() => {
  return typeof props.size === 'number' ? props.size : parseInt(props.size || '20') || 20
})
</script>

<style lang="scss" scoped>
:deep(.iconify) {
  display: inline-block;
  vertical-align: middle;
}
</style>
