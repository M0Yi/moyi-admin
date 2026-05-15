import axios, { type AxiosInstance, type AxiosRequestConfig, type AxiosResponse } from 'axios'
import { ElMessage } from 'element-plus'

// API 响应接口
export interface ApiResponse<T = any> {
  code: number
  message: string
  data: T
  meta: {
    timestamp: number
    request_id: string
  }
}

// 分页响应接口
export interface PaginatedResponse<T = any> {
  items: T[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

// 创建 axios 实例
const instance: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// 请求拦截器
instance.interceptors.request.use(
  (config) => {
    // 可以在这里添加 token 等认证信息
    // const token = localStorage.getItem('token')
    // if (token) {
    //   config.headers.Authorization = `Bearer ${token}`
    // }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 响应拦截器
instance.interceptors.response.use(
  (response: AxiosResponse<ApiResponse>) => {
    const { code, message, data } = response.data

    if (code === 200) {
      return data as any
    } else {
      // 不在这里显示错误消息，让API模块处理
      return Promise.reject(new Error(message))
    }
  },
  (error) => {
    // 不在这里显示错误消息，让API模块的try-catch处理
    // 只在console记录错误用于调试
    console.warn('API request failed:', error.config?.url, error.message)

    return Promise.reject(error)
  }
)

// 封装请求方法
export const request = {
  get<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    return instance.get(url, config)
  },

  post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return instance.post(url, data, config)
  },

  put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return instance.put(url, data, config)
  },

  delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    return instance.delete(url, config)
  },
}

export default instance
