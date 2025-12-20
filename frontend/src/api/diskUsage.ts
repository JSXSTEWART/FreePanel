import client from './client'

export interface DiskUsageSummary {
  total_used: number
  total_quota: number
  files_used: number
  databases_used: number
  emails_used: number
  breakdown: {
    category: string
    size: number
    percentage: number
  }[]
}

export interface DirectoryUsage {
  path: string
  size: number
  items_count: number
  last_modified: string
  subdirectories: {
    name: string
    path: string
    size: number
  }[]
}

export interface DatabaseUsage {
  name: string
  size: number
  tables_count: number
}

export interface EmailUsage {
  account: string
  size: number
  messages_count: number
}

export interface LargestFile {
  path: string
  size: number
  type: string
  last_modified: string
}

export interface FilesByType {
  type: string
  extension: string
  count: number
  total_size: number
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const diskUsageApi = {
  getSummary: async (): Promise<DiskUsageSummary> => {
    const response = await client.get<ApiResponse<DiskUsageSummary>>('/disk-usage')
    return response.data.data
  },

  getDirectory: async (path?: string): Promise<DirectoryUsage> => {
    const response = await client.get<ApiResponse<DirectoryUsage>>('/disk-usage/directory', {
      params: { path },
    })
    return response.data.data
  },

  getDatabases: async (): Promise<DatabaseUsage[]> => {
    const response = await client.get<ApiResponse<DatabaseUsage[]>>('/disk-usage/databases')
    return response.data.data
  },

  getEmails: async (): Promise<EmailUsage[]> => {
    const response = await client.get<ApiResponse<EmailUsage[]>>('/disk-usage/emails')
    return response.data.data
  },

  getLargestFiles: async (limit?: number): Promise<LargestFile[]> => {
    const response = await client.get<ApiResponse<LargestFile[]>>('/disk-usage/largest-files', {
      params: { limit },
    })
    return response.data.data
  },

  getByType: async (): Promise<FilesByType[]> => {
    const response = await client.get<ApiResponse<FilesByType[]>>('/disk-usage/by-type')
    return response.data.data
  },
}

export default diskUsageApi
