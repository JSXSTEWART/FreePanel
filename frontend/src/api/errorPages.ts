import client from './client'

export interface ErrorPage {
  id: number
  error_code: number
  content: string
  domain_id?: number
  domain_name?: string
  is_active: boolean
  created_at: string
}

export interface ErrorCode {
  code: number
  name: string
  description: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const errorPagesApi = {
  list: async (): Promise<ErrorPage[]> => {
    const response = await client.get<ApiResponse<ErrorPage[]>>('/error-pages')
    return response.data.data
  },

  getCodes: async (): Promise<ErrorCode[]> => {
    const response = await client.get<ApiResponse<ErrorCode[]>>('/error-pages/codes')
    return response.data.data
  },

  get: async (id: number): Promise<ErrorPage> => {
    const response = await client.get<ApiResponse<ErrorPage>>(`/error-pages/${id}`)
    return response.data.data
  },

  create: async (data: {
    error_code: number
    content: string
    domain_id?: number
  }): Promise<ErrorPage> => {
    const response = await client.post<ApiResponse<ErrorPage>>('/error-pages', data)
    return response.data.data
  },

  update: async (id: number, data: {
    content?: string
  }): Promise<ErrorPage> => {
    const response = await client.put<ApiResponse<ErrorPage>>(`/error-pages/${id}`, data)
    return response.data.data
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/error-pages/${id}`)
  },

  toggle: async (id: number): Promise<ErrorPage> => {
    const response = await client.post<ApiResponse<ErrorPage>>(`/error-pages/${id}/toggle`)
    return response.data.data
  },
}

export default errorPagesApi
