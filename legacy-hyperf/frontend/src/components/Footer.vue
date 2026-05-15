<template>
  <footer class="site-footer">
    <!-- 白色LOGO贯穿条 -->
    <div class="footer-brand-strip">
      <img
        src="https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1770800772-a72d5fe8c5c55d8b.png"
        alt="建辉慈善基金会"
        class="footer-logo"
      />
    </div>

    <!-- 主体内容区 -->
    <div class="footer-main">
      <div class="footer-content">
        <!-- 联系信息 -->
        <div class="footer-section">
          <h4 class="footer-title">联系我们</h4>
          <div class="contact-item">
            <svg class="contact-icon" viewBox="0 0 24 24" fill="none">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>+86 0755-83239875</span>
          </div>
          <div class="contact-item">
            <svg class="contact-icon" viewBox="0 0 24 24" fill="none">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M22 6l-10 7L2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>contact@jianhuicishan.org</span>
          </div>
          <div class="contact-item">
            <svg class="contact-icon" viewBox="0 0 24 24" fill="none">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>广东省深圳市福田区</span>
          </div>
        </div>

        <!-- 快速链接 -->
        <div class="footer-section">
          <h4 class="footer-title">快速链接</h4>
          <div class="footer-links">
            <router-link to="/about" class="footer-link">关于我们</router-link>
            <router-link to="/projects" class="footer-link">公益项目</router-link>
            <router-link to="/stories" class="footer-link">生命故事</router-link>
            <router-link to="/articles" class="footer-link">新闻中心</router-link>
            <router-link to="/donate" class="footer-link">爱心捐赠</router-link>
            <router-link to="/disclosure" class="footer-link">信息公开</router-link>
          </div>
        </div>

        <!-- 社交媒体 -->
        <div class="footer-section">
          <h4 class="footer-title">关注我们</h4>
          <div class="footer-social">
            <div
              class="social-link wechat-link"
              @mouseenter="showWechatQR = true"
              @mouseleave="showWechatQR = false"
              title="微信"
            >
              <font-awesome-icon :icon="['fab', 'weixin']" />
              <!-- 二维码弹出层 -->
              <div class="qr-popup" :class="{ show: showWechatQR }">
                <img
                  src="http://www.jianhuicishan.org/_nuxt/img/code-new.8431f65.png"
                  alt="建辉慈善基金会微信二维码"
                />
              </div>
            </div>
            <a href="https://weibo.com/jianhuicishan" target="_blank" rel="noopener noreferrer" class="social-link weibo-link" title="微博">
              <font-awesome-icon :icon="['fab', 'weibo']" />
            </a>
          </div>
        </div>
      </div>

      <!-- 底部版权 -->
      <div class="footer-bottom">
        <p>© 2025 建辉慈善基金会 版权所有 | 粤ICP备16097782号</p>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { ref } from 'vue'

// 微信二维码弹窗
const showWechatQR = ref(false)
</script>

<style scoped lang="scss">
/* ========== Footer ========== */
.site-footer {
  background: var(--color-primary);
  color: #ffffff;
  position: relative;
  overflow: visible;
  scroll-snap-align: start;

  // 白色LOGO贯穿条 - 完全贯穿整个页面高度
  .footer-brand-strip {
    background: #ffffff;
    width: 280px;
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 0;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
    z-index: 2;

    .footer-logo {
      width: 180px;
      height: auto;
      display: block;
    }
  }

  // 主体内容区
  .footer-main {
    margin-left: 280px;
    padding: 60px 80px;
    min-height: 400px;
    display: flex;
    flex-direction: column;
  }

  .footer-content {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 40px;
    flex: 1;
  }

  .footer-section {
    .footer-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 24px;
      color: #ffffff;
    }

    // 联系方式
    .contact-item {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
      color: rgba(255, 255, 255, 0.9);
      transition: all 0.3s ease;

      .contact-icon {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        opacity: 0.8;
      }

      &:hover {
        color: #ffffff;
        .contact-icon {
          opacity: 1;
        }
      }
    }

    // 快速链接
    .footer-links {
      display: flex;
      flex-direction: column;
      gap: 12px;

      .footer-link {
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        padding-left: 16px;

        &::before {
          content: '';
          position: absolute;
          left: 0;
          top: 50%;
          transform: translateY(-50%);
          width: 6px;
          height: 6px;
          background: rgba(255, 255, 255, 0.5);
          border-radius: 50%;
          transition: all 0.3s ease;
        }

        &:hover {
          color: #ffffff;
          padding-left: 20px;

          &::before {
            background: #ffffff;
            width: 8px;
            height: 8px;
          }
        }
      }
    }

    // 社交媒体
    .footer-social {
      display: flex;
      gap: 12px;
      margin-top: 24px;

      .social-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
        transition: all 0.3s ease;
        text-decoration: none;
        position: relative;
        cursor: pointer;

        svg {
          width: 20px;
          height: 20px;
        }

        &:hover {
          background: #ffffff;
          color: var(--color-primary);
          transform: translateY(-3px);
        }

        // 微博链接特殊样式
        &.weibo-link:hover {
          background: #E6162D;
          color: #ffffff;
        }

        // 二维码弹出层
        .qr-popup {
          position: absolute;
          bottom: calc(100% + 15px);
          left: 50%;
          transform: translateX(-50%) translateY(10px);
          background: #ffffff;
          border-radius: 8px;
          padding: 15px;
          box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
          opacity: 0;
          visibility: hidden;
          transition: all 0.3s ease;
          pointer-events: none;
          z-index: 100;

          img {
            width: 180px;
            height: 180px;
            display: block;
            border-radius: 4px;
          }

          &::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid #ffffff;
          }

          &.show {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
          }
        }
      }
    }
  }

  .footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    padding-top: 24px;
    margin-top: 40px;
    text-align: center;
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;

    p {
      margin: 0;
    }
  }
}

/* ========== 响应式设计 ========== */
@media (max-width: 1024px) {
  .site-footer {
    .footer-brand-strip {
      width: 200px;

      .footer-logo {
        width: 140px;
      }
    }

    .footer-main {
      margin-left: 200px;
      padding: 50px 40px;
    }

    .footer-content {
      gap: 30px;
    }
  }
}

@media (max-width: 768px) {
  .site-footer {
    .footer-brand-strip {
      display: none;
    }

    .footer-main {
      margin-left: 0;
      padding: 40px 20px;
    }

    .footer-content {
      grid-template-columns: 1fr;
      gap: 32px;

      .footer-section {
        text-align: center;

        .contact-item {
          justify-content: center;
        }

        .footer-links {
          align-items: center;
        }

        .footer-social {
          justify-content: center;
        }
      }
    }

    .footer-bottom {
      margin-top: 32px;
    }
  }
}
</style>
