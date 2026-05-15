<template>
  <footer class="simple-footer">
    <div class="footer-content">
      <div class="footer-info">
        <p class="footer-copyright">© {{ currentYear }} 建辉慈善基金会 版权所有</p>
        <div class="footer-links">
          <a
            href="https://beian.miit.gov.cn/"
            target="_blank"
            rel="noopener noreferrer"
            class="footer-link"
          >
            粤ICP备16097782号
          </a>
          <span class="separator">|</span>
          <a
            href="http://www.beian.gov.cn/portal/registerSystemInfo"
            target="_blank"
            rel="noopener noreferrer"
            class="footer-link"
          >
            <svg class="beian-icon" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8-8zm0-14c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4-4zm1 5h-2v-2h2v2z"/>
            </svg>
            <!-- 注意：请替换为真实的公安备案号，格式：粤公网安备 + 12位数字 + 号 -->
            粤公网安备 440304020012号
          </a>
        </div>
      </div>
      <div class="footer-extra">
        <router-link to="/about" class="footer-link">关于我们</router-link>
        <span class="separator">|</span>
        <router-link to="/contact" class="footer-link">联系我们</router-link>
        <span class="separator">|</span>
        <router-link to="/privacy" class="footer-link">隐私政策</router-link>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const currentYear = computed(() => new Date().getFullYear())
</script>

<style scoped lang="scss">
.simple-footer {
  background: var(--color-primary);
  color: rgba(255, 255, 255, 0.9);
  padding: 24px 0;
  font-size: 14px;
  border-top: 3px solid rgba(255, 255, 255, 0.2);

  .footer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
  }

  .footer-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .footer-copyright {
    margin: 0;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95);
  }

  .footer-links {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .footer-link {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: color 0.3s ease;
    display: flex;
    align-items: center;
    gap: 4px;

    &:hover {
      color: #ffffff;
    }
  }

  .beian-icon {
    width: 14px;
    height: 14px;
    vertical-align: middle;
    opacity: 0.9;
  }

  .separator {
    color: rgba(255, 255, 255, 0.4);
  }

  .footer-extra {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

@media (max-width: 768px) {
  .simple-footer {
    padding: 20px 0;

    .footer-content {
      flex-direction: column;
      text-align: center;
      gap: 12px;
    }

    .footer-info {
      align-items: center;
    }

    .footer-links {
      justify-content: center;
    }

    .footer-extra {
      justify-content: center;
    }
  }
}
</style>
