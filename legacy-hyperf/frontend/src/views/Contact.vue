<template>
  <div class="contact-page">
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <section class="contact-section">
      <div class="container">
        <!-- 基本信息 -->
        <div class="contact-card">
          <h2 class="section-title">联系方式</h2>
          <div class="contact-grid">
            <div class="contact-item">
              <div class="contact-details">
                <h3>官方网站</h3>
                <p>www.jianhuicishan.org</p>
              </div>
            </div>

            <div class="contact-item">
              <div class="contact-details">
                <h3>联系电话</h3>
                <p>0755-83239875</p>
              </div>
            </div>

            <div class="contact-item">
              <div class="contact-details">
                <h3>电子邮箱</h3>
                <p>fuwu@jianhuicishan.org</p>
              </div>
            </div>

            <div class="contact-item">
              <div class="contact-details">
                <h3>办公地址</h3>
                <p>深圳市南山区粤兴五道9号北理工创新大厦1103室</p>
              </div>
            </div>
          </div>
        </div>

        <!-- 社交媒体 -->
        <div class="contact-card">
          <h2 class="section-title">社交媒体</h2>
          <div class="social-list">
            <div class="social-item">
              <div class="social-details">
                <h3>公众号</h3>
                <p>建辉慈善基金会、致敬行善者、建辉小红花之家</p>
              </div>
            </div>

            <div class="social-item">
              <div class="social-details">
                <h3>微博</h3>
                <p>建辉慈善基金会</p>
              </div>
            </div>

            <div class="social-item">
              <div class="social-details">
                <h3>抖音号</h3>
                <p>建辉慈善基金会、致敬平凡好人</p>
              </div>
            </div>

            <div class="social-item">
              <div class="social-details">
                <h3>视频号</h3>
                <p>致敬行善者、小红花之家</p>
              </div>
            </div>

            <div class="social-item">
              <div class="social-details">
                <h3>小红书</h3>
                <p>建辉慈善基金会</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
// 联系我们页面
</script>

<style scoped lang="scss">
.contact-page {
  min-height: 100vh;
  background: #f5f7fa;
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

.contact-section {
  padding: 60px 0;

  .container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px;
  }
}

.contact-card {
  background: #ffffff;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  margin-bottom: 32px;

  .section-title {
    font-size: 28px;
    color: #333;
    margin: 0 0 32px 0;
    text-align: center;
    position: relative;

    &::after {
      content: '';
      display: block;
      width: 60px;
      height: 3px;
      background: var(--color-primary);
      margin: 12px auto 0;
      border-radius: 2px;
    }
  }
}

.contact-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 32px;
}

.contact-item {
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
  transition: all 0.3s ease;

  &:hover {
    background: #e9ecef;
  }

  .contact-details {
    h3 {
      font-size: 16px;
      color: #666;
      margin: 0 0 8px 0;
      font-weight: 500;
    }

    p {
      font-size: 16px;
      color: #333;
      margin: 0;
      font-weight: 600;
      line-height: 1.5;
    }
  }
}

.social-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.social-item {
  padding: 20px;
  background: #f8f9fa;
  border-left: 4px solid var(--color-primary);
  border-radius: 8px;
  transition: all 0.3s ease;

  &:hover {
    background: #e9ecef;
    transform: translateX(4px);
  }

  .social-details {
    h3 {
      font-size: 16px;
      color: #666;
      margin: 0 0 8px 0;
      font-weight: 500;
    }

    p {
      font-size: 15px;
      color: #333;
      margin: 0;
      line-height: 1.6;
    }
  }
}

@media (max-width: 768px) {
  .page-header {
    height: 200px;
  }

  .contact-grid {
    grid-template-columns: 1fr;
  }

  .contact-card {
    padding: 24px;
  }

  .contact-section {
    padding: 40px 0;
  }
}
</style>
