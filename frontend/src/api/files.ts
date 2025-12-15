import client from './client'

export interface FileItem {
  name: string
  path: string
  type: 'file' | 'directory'
  size: number
  permissions: string
  owner: string
  group: string
  modified_at: string
}

export interface QuotaInfo {
  used: number
  limit: number
  inodes_used: number
  inodes_limit: number
}

export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const filesApi = {
  list: async (path: string): Promise<FileItem[]> => {
    const response = await client.get<ApiResponse<FileItem[]>>('/files/list', { params: { path } })
    return response.data.data
  },

  read: async (path: string): Promise<{ content: string; encoding: string }> => {
    const response = await client.get('/files/read', { params: { path } })
    return response.data.data
  },

  write: async (path: string, content: string): Promise<void> => {
    await client.put('/files/write', { path, content })
  },

  upload: async (path: string, file: File, onProgress?: (percent: number) => void): Promise<void> => {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('path', path)

    await client.post('/files/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        if (onProgress && progressEvent.total) {
          onProgress(Math.round((progressEvent.loaded * 100) / progressEvent.total))
        }
      },
    })
  },

  download: async (path: string): Promise<Blob> => {
    const response = await client.get('/files/download', {
      params: { path },
      responseType: 'blob',
    })
    return response.data
  },

  mkdir: async (path: string): Promise<void> => {
    await client.post('/files/mkdir', { path })
  },

  copy: async (source: string, destination: string): Promise<void> => {
    await client.post('/files/copy', { source, destination })
  },

  move: async (source: string, destination: string): Promise<void> => {
    await client.post('/files/move', { source, destination })
  },

  delete: async (path: string): Promise<void> => {
    await client.delete('/files/delete', { params: { path } })
  },

  permissions: async (path: string, mode: string): Promise<void> => {
    await client.post('/files/permissions', { path, mode })
  },

  compress: async (paths: string[], destination: string, format: 'zip' | 'tar.gz'): Promise<void> => {
    await client.post('/files/compress', { paths, destination, format })
  },

  extract: async (path: string, destination: string): Promise<void> => {
    await client.post('/files/extract', { path, destination })
  },

  search: async (path: string, query: string): Promise<FileItem[]> => {
    const response = await client.get<ApiResponse<FileItem[]>>('/files/search', {
      params: { path, query },
    })
    return response.data.data
  },

  quota: async (): Promise<QuotaInfo> => {
    const response = await client.get<ApiResponse<QuotaInfo>>('/files/quota')
    return response.data.data
  },
}

export default filesApi
