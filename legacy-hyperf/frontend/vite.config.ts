import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 3100,
    strictPort: true, // 端口被占用时报错，不尝试其他端口
    host: true, // 监听所有网络接口
    allowedHosts: true, // 允许所有域名访问
    proxy: {
      '/api': {
        target: 'http://localhost:6501',
        changeOrigin: true,
      },
    },
  },
})
