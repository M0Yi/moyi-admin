<template>
  <div class="admin-login">
    <!-- 背景装饰 -->
    <div class="login-bg">
      <div class="bg-circle circle-1"></div>
      <div class="bg-circle circle-2"></div>
      <div class="bg-circle circle-3"></div>
      <div class="bg-particles">
        <span v-for="i in 20" :key="i" class="particle"></span>
      </div>
    </div>

    <div class="login-container">
      <!-- 左侧信息区 -->
      <div class="login-info">
        <div class="info-content">
          <div class="logo-section">
            <div class="logo-icon">
              <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="3"/>
                <path d="M50 20 L50 35 M50 65 L50 80 M20 50 L35 50 M65 50 L80 50" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <circle cx="50" cy="50" r="15" stroke="currentColor" stroke-width="3"/>
              </svg>
            </div>
            <h1>建辉慈善基金会</h1>
            <p class="subtitle">致敬行善者 成为行善者</p>
          </div>

          <div class="feature-list">
            <div v-for="(feature, index) in features" :key="index" class="feature-item">
              <div class="feature-icon">
                <Icon :name="feature.icon" :size="24" color="white" />
              </div>
              <div class="feature-text">
                <h4>{{ feature.title }}</h4>
                <p>{{ feature.desc }}</p>
              </div>
            </div>
          </div>

          <div class="info-footer">
            <p>© 2026 建辉慈善基金会</p>
            <p>让善良的人，被这个世界温柔以待</p>
          </div>
        </div>
      </div>

      <!-- 右侧登录表单 -->
      <div class="login-form-wrapper">
        <div class="login-box">
          <div class="login-header">
            <h2>欢迎回来</h2>
            <p>登录到后台管理系统</p>
          </div>

          <el-form
            ref="loginFormRef"
            :model="loginForm"
            :rules="loginRules"
            class="login-form"
            @keyup.enter="handleLogin"
          >
            <el-form-item prop="username">
              <el-input
                v-model="loginForm.username"
                placeholder="请输入用户名"
                size="large"
                clearable
              >
                <template #prefix>
                  <Icon name="user" :size="18" />
                </template>
              </el-input>
            </el-form-item>

            <el-form-item prop="password">
              <el-input
                v-model="loginForm.password"
                type="password"
                placeholder="请输入密码"
                size="large"
                show-password
              >
                <template #prefix>
                  <Icon name="lock" :size="18" />
                </template>
              </el-input>
            </el-form-item>

            <el-form-item>
              <div class="form-options">
                <el-checkbox v-model="loginForm.remember">记住我</el-checkbox>
                <el-link type="primary">忘记密码？</el-link>
              </div>
            </el-form-item>

            <el-form-item>
              <el-button
                type="primary"
                size="large"
                :loading="loading"
                class="login-button"
                @click="handleLogin"
              >
                <template v-if="!loading">
                  <span>登 录</span>
                </template>
                <template v-else>
                  <span>登录中...</span>
                </template>
              </el-button>
            </el-form-item>
          </el-form>

          <div class="login-tips">
            <el-alert
              title="测试账号"
              type="info"
              :closable="false"
              show-icon
            >
              <template #default>
                用户名：<strong>admin</strong> &nbsp;&nbsp; 密码：<strong>123456</strong>
              </template>
            </el-alert>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import Icon from '@/components/Icon.vue'
import request from '@/utils/request'

const router = useRouter()
const loginFormRef = ref<FormInstance>()
const loading = ref(false)

const loginForm = reactive({
  username: '',
  password: '',
  remember: false
})

const features = [
  {
    icon: 'chart',
    title: '数据可视化',
    desc: '直观的数据统计和图表展示'
  },
  {
    icon: 'settings',
    title: '灵活配置',
    desc: '强大的内容管理和系统配置'
  },
  {
    icon: 'shield',
    title: '安全可靠',
    desc: '完善的权限管理和操作日志'
  }
]

const loginRules: FormRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, max: 20, message: '用户名长度在 3 到 20 个字符', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, max: 20, message: '密码长度在 6 到 20 个字符', trigger: 'blur' }
  ]
}

const handleLogin = async () => {
  if (!loginFormRef.value) return

  try {
    await loginFormRef.value.validate()
    loading.value = true

    try {
      // 调用真实的登录 API
      const response = await request({
        url: '/admin/login',
        method: 'post',
        data: {
          username: loginForm.username,
          password: loginForm.password
        }
      })

      // 保存 token 和用户信息
      localStorage.setItem('admin_token', response.token)
      localStorage.setItem('admin_user', JSON.stringify(response.user))

      ElMessage.success({
        message: '登录成功，欢迎回来！',
        duration: 2000
      })
      router.push('/admin/dashboard')
    } catch (error: any) {
      ElMessage.error(error.response?.data?.message || '登录失败，请检查用户名和密码')
    } finally {
      loading.value = false
    }
  } catch (error) {
    console.error('表单验证失败:', error)
  }
}
</script>

<style lang="scss" scoped>
.admin-login {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #ff5000 0%, #ff6a1f 50%, #ff8c42 100%);
  padding: 20px;
  position: relative;
  overflow: hidden;
}

// 背景装饰
.login-bg {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  overflow: hidden;
  z-index: 0;

  .bg-circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    animation: float 20s infinite ease-in-out;

    &.circle-1 {
      width: 400px;
      height: 400px;
      top: -200px;
      right: -100px;
      animation-delay: 0s;
    }

    &.circle-2 {
      width: 300px;
      height: 300px;
      bottom: -150px;
      left: -50px;
      animation-delay: 5s;
    }

    &.circle-3 {
      width: 200px;
      height: 200px;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      animation-delay: 10s;
    }
  }

  .bg-particles {
    .particle {
      position: absolute;
      width: 4px;
      height: 4px;
      background: rgba(255, 255, 255, 0.6);
      border-radius: 50%;
      animation: rise 10s infinite ease-in;
    }

    @for $i from 1 through 20 {
      .particle:nth-child(#{$i}) {
        left: #{random(100)}%;
        animation-delay: #{random(10)}s;
        animation-duration: #{8 + random(4)}s;
      }
    }
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0) rotate(0deg);
  }
  50% {
    transform: translateY(-30px) rotate(180deg);
  }
}

@keyframes rise {
  0% {
    bottom: -10%;
    opacity: 0;
  }
  50% {
    opacity: 1;
  }
  100% {
    bottom: 110%;
    opacity: 0;
  }
}

.login-container {
  position: relative;
  z-index: 1;
  display: flex;
  width: 100%;
  max-width: 1000px;
  background: white;
  border-radius: 20px;
  box-shadow: 0 30px 80px rgba(255, 80, 0, 0.3);
  overflow: hidden;
  animation: slideUp 0.6s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

// 左侧信息区
.login-info {
  flex: 1;
  background: linear-gradient(135deg, #ff5000 0%, #ff6a1f 100%);
  padding: 60px 50px;
  color: white;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></svg>') repeat;
    opacity: 0.5;
  }

  .info-content {
    position: relative;
    z-index: 1;
  }

  .logo-section {
    margin-bottom: 50px;

    .logo-icon {
      width: 60px;
      height: 60px;
      margin-bottom: 20px;
      color: white;
      animation: pulse 2s ease-in-out infinite;
    }

    h1 {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 12px;
      letter-spacing: 1px;
    }

    .subtitle {
      font-size: 16px;
      opacity: 0.9;
      font-weight: 300;
    }
  }

  .feature-list {
    .feature-item {
      display: flex;
      align-items: flex-start;
      margin-bottom: 30px;
      padding: 20px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      backdrop-filter: blur(10px);
      transition: all 0.3s ease;

      &:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateX(10px);
      }

      .feature-icon {
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-right: 16px;
        flex-shrink: 0;
      }

      .feature-text {
        flex: 1;

        h4 {
          font-size: 16px;
          margin-bottom: 6px;
          font-weight: 600;
        }

        p {
          font-size: 13px;
          opacity: 0.8;
          line-height: 1.5;
          margin: 0;
        }
      }
    }
  }

  .info-footer {
    text-align: center;
    padding-top: 30px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);

    p {
      font-size: 13px;
      opacity: 0.8;
      margin: 5px 0;
    }
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

// 右侧登录表单
.login-form-wrapper {
  width: 450px;
  padding: 60px 50px;
  display: flex;
  align-items: center;
  background: white;
}

.login-box {
  width: 100%;

  .login-header {
    margin-bottom: 40px;

    h2 {
      font-size: 28px;
      color: #1a1a1a;
      margin-bottom: 8px;
      font-weight: 700;
    }

    p {
      font-size: 14px;
      color: #999;
      margin: 0;
    }
  }

  .login-form {
    :deep(.el-form-item) {
      margin-bottom: 24px;
    }

    :deep(.el-input__wrapper) {
      padding: 12px 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
      border: 1px solid #e5e7eb;
      transition: all 0.3s ease;

      &:hover {
        box-shadow: 0 4px 12px rgba(255, 80, 0, 0.1);
        border-color: #ff5000;
      }

      &.is-focus {
        box-shadow: 0 4px 12px rgba(255, 80, 0, 0.2);
        border-color: #ff5000;
      }
    }

    :deep(.el-input__prefix) {
      color: #ff5000;
      font-size: 18px;
    }

    .form-options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;

      :deep(.el-checkbox__label) {
        color: #666;
      }
    }

    .login-button {
      width: 100%;
      height: 50px;
      font-size: 16px;
      font-weight: 600;
      background: linear-gradient(135deg, #ff5000 0%, #ff6a1f 100%);
      border: none;
      box-shadow: 0 8px 20px rgba(255, 80, 0, 0.3);
      transition: all 0.3s ease;

      &:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(255, 80, 0, 0.4);
      }

      &:active {
        transform: translateY(0);
      }
    }
  }

  .login-tips {
    margin-top: 24px;

    :deep(.el-alert) {
      background: #fff5f0;
      border-color: #ffe0d0;

      .el-alert__title {
        color: #ff5000;
        font-size: 13px;
      }

      strong {
        color: #ff5000;
      }
    }
  }
}

// 响应式
@media (max-width: 968px) {
  .login-container {
    flex-direction: column;
    max-width: 500px;
  }

  .login-info {
    padding: 40px 30px;

    .feature-list {
      display: none;
    }
  }

  .login-form-wrapper {
    width: 100%;
    padding: 40px 30px;
  }
}
</style>
