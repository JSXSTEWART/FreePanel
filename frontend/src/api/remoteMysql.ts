import client from './client'

export interface RemoteMysqlHost {
  id: number
  host: string
  created_at: string
}

export interface RemoteMysqlTestResult {
  success: boolean
  host: string
  latency_ms?: number
  error?: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const remoteMysqlApi = {
  list: async (): Promise<RemoteMysqlHost[]> => {
    const response = await client.get<ApiResponse<RemoteMysqlHost[]>>('/remote-mysql')
    return response.data.data
  },

  add: async (host: string): Promise<RemoteMysqlHost> => {
    const response = await client.post<ApiResponse<RemoteMysqlHost>>('/remote-mysql', { host })
    return response.data.data
  },

  remove: async (host: string): Promise<void> => {
    await client.delete('/remote-mysql', {
      data: { host },
    })
  },

  test: async (host: string): Promise<RemoteMysqlTestResult> => {
    const response = await client.post<ApiResponse<RemoteMysqlTestResult>>('/remote-mysql/test', { host })
    return response.data.data
  },
}

export default remoteMysqlApi
