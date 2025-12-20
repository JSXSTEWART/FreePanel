import client from './client'

export interface GitRepository {
  id: number
  name: string
  url: string
  branch: string
  path: string
  auto_deploy: boolean
  deploy_key?: string
  last_deployed_at?: string
  last_commit?: {
    hash: string
    message: string
    author: string
    date: string
  }
  created_at: string
}

export interface DeployLog {
  id: number
  commit_hash: string
  status: 'pending' | 'running' | 'success' | 'failed'
  output?: string
  started_at: string
  completed_at?: string
}

export interface GitFile {
  name: string
  path: string
  type: 'file' | 'directory'
  size?: number
  last_modified?: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const gitApi = {
  list: async (): Promise<GitRepository[]> => {
    const response = await client.get<ApiResponse<GitRepository[]>>('/git')
    return response.data.data
  },

  get: async (id: number): Promise<GitRepository> => {
    const response = await client.get<ApiResponse<GitRepository>>(`/git/${id}`)
    return response.data.data
  },

  create: async (data: {
    name: string
    path: string
    auto_deploy?: boolean
  }): Promise<GitRepository> => {
    const response = await client.post<ApiResponse<GitRepository>>('/git', data)
    return response.data.data
  },

  clone: async (data: {
    url: string
    path: string
    branch?: string
    name?: string
  }): Promise<GitRepository> => {
    const response = await client.post<ApiResponse<GitRepository>>('/git/clone', data)
    return response.data.data
  },

  update: async (id: number, data: Partial<{
    name: string
    branch: string
    auto_deploy: boolean
  }>): Promise<GitRepository> => {
    const response = await client.put<ApiResponse<GitRepository>>(`/git/${id}`, data)
    return response.data.data
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/git/${id}`)
  },

  pull: async (id: number): Promise<{ output: string }> => {
    const response = await client.post<ApiResponse<{ output: string }>>(`/git/${id}/pull`)
    return response.data.data
  },

  deploy: async (id: number): Promise<DeployLog> => {
    const response = await client.post<ApiResponse<DeployLog>>(`/git/${id}/deploy`)
    return response.data.data
  },

  getDeployLogs: async (id: number): Promise<DeployLog[]> => {
    const response = await client.get<ApiResponse<DeployLog[]>>(`/git/${id}/deploy-logs`)
    return response.data.data
  },

  getFiles: async (id: number, path?: string): Promise<GitFile[]> => {
    const response = await client.get<ApiResponse<GitFile[]>>(`/git/${id}/files`, {
      params: { path },
    })
    return response.data.data
  },

  getFileContent: async (id: number, path: string): Promise<string> => {
    const response = await client.get<ApiResponse<string>>(`/git/${id}/file`, {
      params: { path },
    })
    return response.data.data
  },
}

export default gitApi
