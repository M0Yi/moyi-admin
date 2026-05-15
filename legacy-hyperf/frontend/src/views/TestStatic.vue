<template>
  <div class="static-test-page">
    <header class="test-header">
      <h1>建辉慈善基金会</h1>
      <p class="subtitle">让善行温暖世界，让爱传递</p>
    </header>

    <section class="test-section">
      <h2>统计数据</h2>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">💰</div>
          <div class="stat-value">1,580,000元</div>
          <div class="stat-label">历年累计</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📈</div>
          <div class="stat-value">320,000元</div>
          <div class="stat-label">本年捐赠</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">❤️</div>
          <div class="stat-value">5,280</div>
          <div class="stat-label">爱心人士</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🏆</div>
          <div class="stat-value">1,234</div>
          <div class="stat-label">受益人数</div>
        </div>
      </div>
    </section>

    <section class="test-section">
      <h2>精选项目</h2>
      <div class="project-card">
        <div class="project-image">📷</div>
        <div class="project-content">
          <h3>致敬困境中的行善者</h3>
          <p>为那些身处困境但依然坚持行善的人们提供关怀和支持</p>
          <div class="project-progress">
            <div class="progress-bar">
              <div class="progress-fill" style="width: 68.5%"></div>
            </div>
            <div class="progress-text">已筹 685,000 元 / 目标 1,000,000 元</div>
          </div>
          <div class="project-stats">
            <span>👤 已帮助 1,258 人</span>
          </div>
        </div>
      </div>
    </section>

    <section class="test-section cta">
      <h2>您的善举，改变生命</h2>
      <p>每一份捐赠都承载着希望，每一次转发都传递着温暖</p>
      <div class="cta-buttons">
        <el-button type="primary" size="large">立即捐赠</el-button>
        <el-button size="large">了解更多</el-button>
      </div>
    </section>

    <footer class="test-footer">
      <p>© 2026 建辉慈善基金会 版权所有</p>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'

onMounted(() => {
  console.log('Static test page mounted successfully!')
})
</script>

<style scoped lang="scss">
.static-test-page {
  min-height: 100vh;
  background: #f5f7fa;
}

.test-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  padding: 80px 20px;
  text-align: center;

  h1 {
    font-size: 48px;
    margin-bottom: 10px;
  }

  .subtitle {
    font-size: 20px;
    opacity: 0.9;
  }
}

.test-section {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 20px;

  h2 {
    text-align: center;
    font-size: 32px;
    margin-bottom: 30px;
    color: #333;
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;

  @media (max-width: 768px) {
    grid-template-columns: repeat(2, 1fr);
  }
}

.stat-card {
  background: #fff;
  padding: 30px;
  border-radius: 8px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s;

  &:hover {
    transform: translateY(-4px);
  }

  .stat-icon {
    font-size: 48px;
    margin-bottom: 10px;
  }

  .stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
  }

  .stat-label {
    font-size: 14px;
    color: #999;
  }
}

.project-card {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;

  @media (min-width: 768px) {
    flex-direction: row;
  }

  .project-image {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 64px;
    color: #fff;

    @media (min-width: 768px) {
      width: 300px;
      height: auto;
    }
  }

  .project-content {
    flex: 1;
    padding: 20px;

    h3 {
      font-size: 20px;
      margin-bottom: 10px;
      color: #333;
    }

    p {
      color: #666;
      line-height: 1.6;
      margin-bottom: 15px;
    }

    .project-progress {
      margin-bottom: 15px;

      .progress-bar {
        height: 8px;
        background: #f0f0f0;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 8px;

        .progress-fill {
          height: 100%;
          background: #67c23a;
          transition: width 0.3s;
        }
      }

      .progress-text {
        font-size: 12px;
        color: #999;
      }
    }

    .project-stats {
      font-size: 14px;
      color: #666;
    }
  }
}

.cta {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border-radius: 8px;
  margin: 40px 20px;
  text-align: center;
  padding: 60px 20px;

  h2 {
    color: #fff;
    margin-bottom: 10px;
  }

  p {
    font-size: 18px;
    margin-bottom: 30px;
    opacity: 0.9;
  }

  .cta-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
  }
}

.test-footer {
  background: #2c3e50;
  color: #ecf0f1;
  padding: 30px 20px;
  text-align: center;
}

@media (max-width: 768px) {
  .test-header {
    padding: 60px 20px;

    h1 {
      font-size: 32px;
    }

    .subtitle {
      font-size: 16px;
    }
  }

  .test-section h2 {
    font-size: 24px;
  }

  .cta {
    h2 {
      font-size: 28px;
    }

    p {
      font-size: 16px;
    }
  }
}
</style>
