<template>
  <div class="disclosure-page">
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <section class="disclosure-section">
      <div class="container">
        <el-row :gutter="24" class="disclosure-grid">
          <el-col :xs="12" :sm="8" :md="6" v-for="item in disclosureItems" :key="item.id">
            <div class="disclosure-card" @click="goToCategory(item.slug)">
              <div class="disclosure-icon" :style="{ background: item.color }">
                <span class="icon">{{ item.icon }}</span>
              </div>
              <h3 class="disclosure-title">{{ item.name }}</h3>
              <p class="disclosure-desc">{{ item.description }}</p>
            </div>
          </el-col>
        </el-row>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { articlesApi } from '@/api/articles'

const router = useRouter()

const disclosureItems = ref([
  {
    id: 1,
    name: '年度报告',
    slug: 'annual',
    icon: '📊',
    color: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
    description: '基金会年度工作报告'
  },
  {
    id: 2,
    name: '财务报告',
    slug: 'financial',
    icon: '💰',
    color: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    description: '财务收支情况报告'
  },
  {
    id: 3,
    name: '审计报告',
    slug: 'audit',
    icon: '📋',
    color: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
    description: '年度审计报告'
  },
  {
    id: 4,
    name: '季度报告',
    slug: 'quarterly',
    icon: '📈',
    color: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
    description: '季度工作报告'
  },
  {
    id: 5,
    name: '投资活动',
    slug: 'investment',
    icon: '📈',
    color: 'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
    description: '投资活动情况披露'
  }
])

const goToCategory = (slug: string) => {
  router.push(`/articles/${slug}`)
}
</script>

<style scoped lang="scss">
.disclosure-page {
  min-height: 100vh;
  background: #f5f7fa;
}

.disclosure-section {
  padding: 60px 0;

  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}
.disclosure-grid {
  margin-top: 40px;
}

.disclosure-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 32px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  border: 2px solid transparent;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  margin-bottom: 24px;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    border-color: var(--color-primary);
  }

  .disclosure-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;

    .icon {
      font-size: 36px;
}
  .disclosure-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin: 0 0 12px 0;
  }

  .disclosure-desc {
    font-size: 14px;
    color: #666;
    margin: 0;
}
  .disclosure-section {
    padding: 40px 0;
}




.page-header {
  position: relative;
  width: 100%;
  height: 300px;
  overflow: hidden;

  .header-bg {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
}


}

.page-header {
  position: relative;
  width: 100%;
  height: 300px;
  overflow: hidden;

  .header-bg {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
}

@media (max-width: 768px) {
  .page-header {
    height: 200px;
  }
}
</style>
