import client from './client'

export interface BandwidthStats {
  current_usage: number
  limit: number
  usage_percent: number
  history: Record<string, number>
  by_domain: Record<string, number>
  period: string
}

export interface VisitorStats {
  total_visits: number
  unique_visitors: number
  page_views: number
  history: Record<string, number>
  top_pages: Record<string, number>
  top_referrers: Record<string, number>
  browsers: Record<string, number>
  countries: Record<string, number>
  period: string
}

export interface ErrorStats {
  total_errors: number
  by_status_code: Record<string, number>
  recent_errors: Array<{
    timestamp: number
    datetime: string
    level: string
    client: string
    message: string
    domain: string
  }>
  top_error_urls: Record<string, number>
  period: string
}

export interface ResourceUsage {
  disk: {
    used: number
    limit: number
    percent: number
    breakdown: Record<string, number>
  }
  bandwidth: {
    used: number
    limit: number
    percent: number
  }
  inodes: {
    used: number
    limit: number
    percent: number
  }
  quotas: {
    email_accounts: { used: number; limit: number }
    databases: { used: number; limit: number }
    domains: { used: number; limit: number }
    subdomains: { used: number; limit: number }
    ftp_accounts: { used: number; limit: number }
  }
}

export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const statsApi = {
  getBandwidth: async (period?: 'day' | 'week' | 'month' | 'year'): Promise<BandwidthStats> => {
    const response = await client.get<ApiResponse<BandwidthStats>>('/stats/bandwidth', {
      params: { period },
    })
    return response.data.data
  },

  getVisitors: async (params?: {
    period?: 'day' | 'week' | 'month' | 'year'
    domain?: string
  }): Promise<VisitorStats> => {
    const response = await client.get<ApiResponse<VisitorStats>>('/stats/visitors', { params })
    return response.data.data
  },

  getErrors: async (params?: {
    period?: 'day' | 'week' | 'month' | 'year'
    domain?: string
    limit?: number
  }): Promise<ErrorStats> => {
    const response = await client.get<ApiResponse<ErrorStats>>('/stats/errors', { params })
    return response.data.data
  },

  getResourceUsage: async (): Promise<ResourceUsage> => {
    const response = await client.get<ApiResponse<ResourceUsage>>('/stats/resource-usage')
    return response.data.data
  },
}

export default statsApi
