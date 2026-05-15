<template>
  <footer class="app-footer">
    <div class="footer-main">
      <div class="footer-container">
        <!-- Logo & Slogan -->
        <div class="footer-logo-section">
          <img
            src="https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1770800772-a72d5fe8c5c55d8b.png"
            alt="建辉慈善基金会"
            class="footer-logo"
          />
          <p class="footer-slogan">致敬行善者 让好人有好报</p>
          <p class="footer-desc">工作时间：工作日 9:30-18:30</p>
        </div>

        <!-- Contact Info -->
        <div class="footer-info-section">
          <div class="info-item">
            <svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
            </svg>
            <span>官方电话：400-XXX-XXXX</span>
          </div>
          <div class="info-item">
            <svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
            <span>官方邮箱：fuwu@jianhuicishan.org</span>
          </div>
          <div class="info-item">
            <svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
            <span>办公地址：深圳市南山区XXXXXXXX</span>
          </div>
        </div>

        <!-- Social Media -->
        <div class="footer-social-section">
          <p class="social-title">实时动态与信息，关注我们</p>
          <div class="social-grid">
            <div class="social-item" v-for="item in socialLinks" :key="item.name">
              <div class="social-icon-wrapper">
                <img :src="item.icon" :alt="item.name" class="social-icon" />
              </div>
              <span class="social-name">{{ item.name }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Copyright -->
    <div class="footer-bottom">
      <div class="footer-container">
        <p>京 ICP 备 XXXXXXXXX 号 -1</p>
        <p>京公网安备 XXXXXXXXXXXXX 号</p>
        <p>All Rights Reserved. 建辉慈善基金会 版权所有 &copy; {{ currentYear }}</p>
        <a href="#">隐私政策</a>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const currentYear = computed(() => new Date().getFullYear())

const socialLinks = [
  { name: '官方微信订阅号', icon: '' },
  { name: '官方微信服务号', icon: '' },
  { name: '官方微博', icon: '' },
  { name: '官方抖音', icon: '' },
  { name: '官方视频号', icon: '' },
]
</script>

<style scoped lang="scss">
.app-footer {
  margin-top: 0;
}

.footer-main {
  background: #2c2c2c;
  padding: 60px 0 40px;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

// Logo section
.footer-logo-section {
  text-align: center;
  margin-bottom: 40px;

  .footer-logo {
    height: 56px;
    width: auto;
    display: inline-block;
    margin-bottom: 12px;
    filter: brightness(0) invert(1);
    opacity: 0.9;
  }

  .footer-slogan {
    font-size: 16px;
    color: rgba(255, 255, 255, 0.9);
    margin: 0 0 8px;
    font-weight: 600;
    letter-spacing: 2px;
  }

  .footer-desc {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
  }
}

// Info section
.footer-info-section {
  display: flex;
  justify-content: center;
  gap: 48px;
  margin-bottom: 40px;
  flex-wrap: wrap;

  .info-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(255, 255, 255, 0.75);
    font-size: 14px;

    .info-icon {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
      opacity: 0.7;
    }
  }
}

// Social section
.footer-social-section {
  text-align: center;

  .social-title {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.6);
    margin: 0 0 20px;
  }

  .social-grid {
    display: flex;
    justify-content: center;
    gap: 32px;
    flex-wrap: wrap;
  }

  .social-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: opacity 0.2s;

    &:hover {
      opacity: 0.8;
    }

    .social-icon-wrapper {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;

      .social-icon {
        width: 20px;
        height: 20px;
      }
    }

    .social-name {
      font-size: 11px;
      color: rgba(255, 255, 255, 0.5);
    }
  }
}

// Bottom
.footer-bottom {
  background: #222;
  padding: 24px 0;
  text-align: center;

  .footer-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
  }

  p {
    margin: 0;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.4);
  }

  a {
    color: rgba(255, 255, 255, 0.4);
    text-decoration: none;
    font-size: 12px;
    transition: color 0.2s;

    &:hover {
      color: rgba(255, 255, 255, 0.7);
    }
  }
}

// Responsive
@media (max-width: 768px) {
  .footer-main {
    padding: 40px 0 28px;
  }

  .footer-logo-section {
    .footer-logo {
      height: 44px;
    }

    .footer-slogan {
      font-size: 14px;
    }
  }

  .footer-info-section {
    flex-direction: column;
    align-items: center;
    gap: 12px;
    margin-bottom: 28px;

    .info-item {
      font-size: 13px;
    }
  }

  .social-grid {
    gap: 20px !important;
  }
}
</style>
