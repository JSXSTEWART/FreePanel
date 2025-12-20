import client from './client'

export interface Application {
  id: number
  name: string
  type: 'nodejs' | 'python'
  version: string
  path: string
  entry_point: string
  port: number
  status: 'running' | 'stopped' | 'error'
  domain?: string
  env_vars?: Record<string, string>
  created_at: string
  started_at?: string
}

export interface Runtime {
  type: 'nodejs' | 'python'
  versions: string[]
  default_version: string
}

export interface ApplicationLogs {
  logs: string
  has_more: boolean
}

export interface ApplicationMetrics {
  cpu_usage: number
  memory_usage: number
  memory_limit: number
  uptime: number
  requests: number
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const applicationsApi = {
  list: async (): Promise<Application[]> => {
    const response = await client.get<ApiResponse<Application[]>>('/applications')
    return response.data.data
  },

  getRuntimes: async (): Promise<Runtime[]> => {
    const response = await client.get<ApiResponse<Runtime[]>>('/applications/runtimes')
    return response.data.data
  },

  get: async (id: number): Promise<Application> => {
    const response = await client.get<ApiResponse<Application>>(`/applications/${id}`)
    return response.data.data
  },

  create: async (data: {
    name: string
    type: 'nodejs' | 'python'
    version: string
    path: string
    entry_point: string
    port?: number
    domain?: string
    env_vars?: Record<string, string>
  }): Promise<Application> => {
    const response = await client.post<ApiResponse<Application>>('/applications', data)
    return response.data.data
  },

  update: async (id: number, data: Partial<{
    name: string
    version: string
    entry_point: string
    port: number
    domain: string
  }>): Promise<Application> => {
    const response = await client.put<ApiResponse<Application>>(`/applications/${id}`, data)
    return response.data.data
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/applications/${id}`)
  },

  start: async (id: number): Promise<Application> => {
    const response = await client.post<ApiResponse<Application>>(`/applications/${id}/start`)
    return response.data.data
  },

  stop: async (id: number): Promise<Application> => {
    const response = await client.post<ApiResponse<Application>>(`/applications/${id}/stop`)
    return response.data.data
  },

  restart: async (id: number): Promise<Application> => {
    const response = await client.post<ApiResponse<Application>>(`/applications/${id}/restart`)
    return response.data.data
  },

  getLogs: async (id: number, lines?: number): Promise<ApplicationLogs> => {
    const response = await client.get<ApiResponse<ApplicationLogs>>(`/applications/${id}/logs`, {
      params: { lines },
    })
    return response.data.data
  },

  getMetrics: async (id: number): Promise<ApplicationMetrics> => {
    const response = await client.get<ApiResponse<ApplicationMetrics>>(`/applications/${id}/metrics`)
    return response.data.data
  },

  updateEnv: async (id: number, env_vars: Record<string, string>): Promise<Application> => {
    const response = await client.put<ApiResponse<Application>>(`/applications/${id}/env`, { env_vars })
    return response.data.data
  },
}

export default applicationsApi
