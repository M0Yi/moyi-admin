<template>
  <svg
    :class="['icon', `icon-${name}`, sizeClass]"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    v-bind="$attrs"
  >
    <!-- Search Icon -->
    <template v-if="name === 'search'">
      <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </template>

    <!-- Close Icon -->
    <template v-else-if="name === 'close'">
      <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </template>

    <!-- Menu Icon -->
    <template v-else-if="name === 'menu'">
      <path d="M3 12H21M3 6H21M3 18H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </template>

    <!-- Chevron Right -->
    <template v-else-if="name === 'chevron-right'">
      <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </template>

    <!-- Chevron Down -->
    <template v-else-if="name === 'chevron-down'">
      <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </template>

    <!-- Arrow Right -->
    <template v-else-if="name === 'arrow-right'">
      <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </template>

    <!-- Check -->
    <template v-else-if="name === 'check'">
      <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </template>

    <!-- Heart -->
    <template v-else-if="name === 'heart'">
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </template>
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  name: string
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md'
})

const sizeClass = computed(() => {
  return {
    sm: 'icon-sm',
    md: 'icon-md',
    lg: 'icon-lg'
  }[props.size]
})
</script>

<style scoped lang="scss">
.icon {
  display: inline-block;
  vertical-align: middle;

  &.icon-sm {
    width: 16px;
    height: 16px;
  }

  &.icon-md {
    width: 20px;
    height: 20px;
  }

  &.icon-lg {
    width: 24px;
    height: 24px;
  }
}
</style>
