import client from './client'

export interface AccessLogEntry {
  timestamp: string
  ip: string
  method: string
  path: string
  status: number
  size: number
  referer?: string
  user_agent?: string
  domain?: string
}

export interface AccessLogStats {
  total_requests: number
  unique_visitors: number
  bandwidth_used: number
  top_pages: { path: string; count: number }[]
  top_referrers: { referrer: string; count: number }[]
  status_codes: { code: number; count: number }[]
  requests_by_hour: { hour: number; count: number }[]
}

export interface LogFile {
  name: string
  domain: string
  size: number
  last_modified: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const accessLogsApi = {
  list: async (): Promise<LogFile[]> => {
    const response = await client.get<ApiResponse<LogFile[]>>('/access-logs')
    return response.data.data
  },

  view: async (options?: {
    domain?: string
    lines?: number
    offset?: number
  }): Promise<AccessLogEntry[]> => {
    const response = await client.get<ApiResponse<AccessLogEntry[]>>('/access-logs/view', {
      params: options,
    })
    return response.data.data
  },

  download: async (domain?: string): Promise<Blob> => {
    const response = await client.get('/access-logs/download', {
      params: { domain },
      responseType: 'blob',
    })
    return response.data
  },

  search: async (options: {
    query: string
    domain?: string
    start_date?: string
    end_date?: string
    limit?: number
  }): Promise<AccessLogEntry[]> => {
    const response = await client.get<ApiResponse<AccessLogEntry[]>>('/access-logs/search', {
      params: options,
    })
    return response.data.data
  },

  getStats: async (options?: {
    domain?: string
    start_date?: string
    end_date?: string
  }): Promise<AccessLogStats> => {
    const response = await client.get<ApiResponse<AccessLogStats>>('/access-logs/stats', {
      params: options,
    })
    return response.data.data
  },
}

export default accessLogsApi
