import axios, { AxiosInstance, AxiosRequestConfig } from 'axios'
import toast from 'react-hot-toast'

// API base configuration
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

// Create axios instance
const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// Token management
let authToken: string | null = localStorage.getItem('auth_token')

export const setAuthToken = (token: string | null) => {
  authToken = token
  if (token) {
    localStorage.setItem('auth_token', token)
    api.defaults.headers.common['Authorization'] = `Bearer ${token}`
  } else {
    localStorage.removeItem('auth_token')
    delete api.defaults.headers.common['Authorization']
  }
}

// Initialize token if exists
if (authToken) {
  setAuthToken(authToken)
}

// Request interceptor
api.interceptors.request.use(
  (config) => {
    // Add auth token if available
    if (authToken) {
      config.headers.Authorization = `Bearer ${authToken}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
api.interceptors.response.use(
  (response) => {
    return response
  },
  (error) => {
    // Handle common errors
    if (error.response) {
      const { status, data } = error.response
      
      switch (status) {
        case 401:
          // Unauthorized - clear token and redirect to login
          setAuthToken(null)
          toast.error('Session expired. Please login again.')
          window.location.href = '/login'
          break
        case 403:
          toast.error('Access denied. Insufficient permissions.')
          break
        case 404:
          toast.error('Resource not found.')
          break
        case 422:
          // Validation errors
          if (data.detail && Array.isArray(data.detail)) {
            data.detail.forEach((err: any) => {
              toast.error(`${err.loc?.join(' ')}: ${err.msg}`)
            })
          } else {
            toast.error('Validation error occurred.')
          }
          break
        case 500:
          toast.error('Server error. Please try again later.')
          break
        default:
          toast.error(data?.message || 'An error occurred.')
      }
    } else if (error.request) {
      // Network error
      toast.error('Network error. Please check your connection.')
    } else {
      // Other error
      toast.error('An unexpected error occurred.')
    }
    
    return Promise.reject(error)
  }
)

// API methods
export const apiCall = async <T = any>(config: AxiosRequestConfig): Promise<T> => {
  const response = await api(config)
  return response.data
}

// Authentication API
export const authApi = {
  login: (credentials: { username: string; password: string }) =>
    apiCall({
      method: 'POST',
      url: '/api/v1/auth/token',
      data: new URLSearchParams(credentials),
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    }),
  
  register: (userData: any) =>
    apiCall({
      method: 'POST',
      url: '/api/v1/auth/register',
      data: userData,
    }),
  
  getCurrentUser: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/auth/me',
    }),
  
  verifyToken: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/auth/verify-token',
    }),
}

// Stocks API
export const stocksApi = {
  getStocks: (params?: any) =>
    apiCall({
      method: 'GET',
      url: '/api/v1/stocks',
      params,
    }),
  
  getStock: (id: number) =>
    apiCall({
      method: 'GET',
      url: `/api/v1/stocks/${id}`,
    }),
  
  createStock: (data: any) =>
    apiCall({
      method: 'POST',
      url: '/api/v1/stocks',
      data,
    }),
  
  updateStock: (id: number, data: any) =>
    apiCall({
      method: 'PUT',
      url: `/api/v1/stocks/${id}`,
      data,
    }),
  
  deleteStock: (id: number) =>
    apiCall({
      method: 'DELETE',
      url: `/api/v1/stocks/${id}`,
    }),
  
  getStockMovements: (id: number, params?: any) =>
    apiCall({
      method: 'GET',
      url: `/api/v1/stocks/${id}/movements`,
      params,
    }),
  
  createStockMovement: (id: number, data: any) =>
    apiCall({
      method: 'POST',
      url: `/api/v1/stocks/${id}/movements`,
      data,
    }),
  
  getLowStockAlerts: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/stocks/alerts/low-stock',
    }),
  
  reserveStock: (id: number, quantity: number) =>
    apiCall({
      method: 'POST',
      url: `/api/v1/stocks/${id}/reserve`,
      params: { quantity },
    }),
}

// Transfers API
export const transfersApi = {
  getTransfers: (params?: any) =>
    apiCall({
      method: 'GET',
      url: '/api/v1/transfers',
      params,
    }),
  
  getTransfer: (id: number) =>
    apiCall({
      method: 'GET',
      url: `/api/v1/transfers/${id}`,
    }),
  
  createTransfer: (data: any) =>
    apiCall({
      method: 'POST',
      url: '/api/v1/transfers',
      data,
    }),
  
  approveTransfer: (id: number) =>
    apiCall({
      method: 'PUT',
      url: `/api/v1/transfers/${id}/approve`,
    }),
  
  completeTransfer: (id: number, data: any) =>
    apiCall({
      method: 'PUT',
      url: `/api/v1/transfers/${id}/complete`,
      data,
    }),
  
  cancelTransfer: (id: number) =>
    apiCall({
      method: 'PUT',
      url: `/api/v1/transfers/${id}/cancel`,
    }),
  
  getTransferStats: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/transfers/dashboard/stats',
    }),
}

// Analytics API
export const analyticsApi = {
  getStockForecast: (stockId: number, daysAhead?: number) =>
    apiCall({
      method: 'GET',
      url: `/api/v1/analytics/stock-forecasting/${stockId}`,
      params: { days_ahead: daysAhead },
    }),
  
  getReorderRecommendations: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/analytics/reorder-recommendations',
    }),
  
  getStockOptimization: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/analytics/stock-optimization',
    }),
  
  getDashboardMetrics: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/analytics/dashboard/metrics',
    }),
}

// Dashboard API
export const dashboardApi = {
  getOverview: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/dashboard/overview',
    }),
  
  getRealTimeMetrics: () =>
    apiCall({
      method: 'GET',
      url: '/api/v1/dashboard/real-time-metrics',
    }),
  
  getStockTrends: (params?: any) =>
    apiCall({
      method: 'GET',
      url: '/api/v1/dashboard/stock-trends',
      params,
    }),
  
  getAlerts: (params?: any) =>
    apiCall({
      method: 'GET',
      url: '/api/v1/dashboard/alerts',
      params,
    }),
  
  getPerformanceMetrics: (params?: any) =>
    apiCall({
      method: 'GET',
      url: '/api/v1/dashboard/performance-metrics',
      params,
    }),
  
  exportData: (params: any) =>
    apiCall({
      method: 'GET',
      url: '/api/v1/dashboard/export-data',
      params,
    }),
}

export default api