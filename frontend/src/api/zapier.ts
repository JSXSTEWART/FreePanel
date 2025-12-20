import client from './client'

export interface ZapierConnection {
  id: number
  user_id: number
  client_id: string
  connected_at: string
  last_used_at?: string
  tools_used?: string[]
}

export interface ZapierTool {
  name: string
  description: string
  category: string
  parameters: {
    name: string
    type: string
    required: boolean
    description: string
  }[]
}

export interface ZapierExecuteResult {
  success: boolean
  tool: string
  result: unknown
  execution_time_ms: number
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const zapierApi = {
  connect: async (data: {
    client_id: string
    auth_token: string
  }): Promise<ZapierConnection> => {
    const response = await client.post<ApiResponse<ZapierConnection>>('/zapier/connect', data)
    return response.data.data
  },

  getConnection: async (): Promise<ZapierConnection | null> => {
    try {
      const response = await client.get<ApiResponse<ZapierConnection>>('/zapier/connection')
      return response.data.data
    } catch (error: unknown) {
      const err = error as { response?: { status?: number } }
      if (err.response?.status === 404) {
        return null
      }
      throw error
    }
  },

  listTools: async (): Promise<ZapierTool[]> => {
    const response = await client.get<ApiResponse<ZapierTool[]>>('/zapier/tools')
    return response.data.data
  },

  executeTool: async (data: {
    tool_name: string
    parameters: Record<string, unknown>
  }): Promise<ZapierExecuteResult> => {
    const response = await client.post<ApiResponse<ZapierExecuteResult>>('/zapier/execute', data)
    return response.data.data
  },

  disconnect: async (): Promise<void> => {
    await client.delete('/zapier/disconnect')
  },
}

export default zapierApi
