import client from './client'

export interface MimeType {
  id: number
  extension: string
  mime_type: string
  is_system: boolean
  created_at: string
}

export interface ApacheHandler {
  extension: string
  handler: string
  is_system: boolean
}

export interface DirectoryIndexes {
  indexes: string[]
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const mimeTypesApi = {
  list: async (): Promise<MimeType[]> => {
    const response = await client.get<ApiResponse<MimeType[]>>('/mime-types')
    return response.data.data
  },

  create: async (data: {
    extension: string
    mime_type: string
  }): Promise<MimeType> => {
    const response = await client.post<ApiResponse<MimeType>>('/mime-types', data)
    return response.data.data
  },

  update: async (id: number, data: {
    mime_type: string
  }): Promise<MimeType> => {
    const response = await client.put<ApiResponse<MimeType>>(`/mime-types/${id}`, data)
    return response.data.data
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/mime-types/${id}`)
  },

  // Handlers
  getHandlers: async (): Promise<ApacheHandler[]> => {
    const response = await client.get<ApiResponse<ApacheHandler[]>>('/mime-types/handlers')
    return response.data.data
  },

  addHandler: async (data: {
    extension: string
    handler: string
  }): Promise<ApacheHandler> => {
    const response = await client.post<ApiResponse<ApacheHandler>>('/mime-types/handlers', data)
    return response.data.data
  },

  removeHandler: async (extension: string): Promise<void> => {
    await client.delete('/mime-types/handlers', {
      data: { extension },
    })
  },

  // Directory Indexes
  getIndexes: async (): Promise<DirectoryIndexes> => {
    const response = await client.get<ApiResponse<DirectoryIndexes>>('/mime-types/indexes')
    return response.data.data
  },

  updateIndexes: async (indexes: string[]): Promise<DirectoryIndexes> => {
    const response = await client.put<ApiResponse<DirectoryIndexes>>('/mime-types/indexes', { indexes })
    return response.data.data
  },
}

export default mimeTypesApi
