import client from './client'

export interface Redirect {
  id: number
  source_path: string
  destination_url: string
  type: 'permanent' | 'temporary'
  wildcard: boolean
  domain_id?: number
  domain_name?: string
  is_active: boolean
  created_at: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const redirectsApi = {
  list: async (): Promise<Redirect[]> => {
    const response = await client.get<ApiResponse<Redirect[]>>('/redirects')
    return response.data.data
  },

  get: async (id: number): Promise<Redirect> => {
    const response = await client.get<ApiResponse<Redirect>>(`/redirects/${id}`)
    return response.data.data
  },

  create: async (data: {
    source_path: string
    destination_url: string
    type: 'permanent' | 'temporary'
    wildcard?: boolean
    domain_id?: number
  }): Promise<Redirect> => {
    const response = await client.post<ApiResponse<Redirect>>('/redirects', data)
    return response.data.data
  },

  update: async (id: number, data: Partial<{
    source_path: string
    destination_url: string
    type: 'permanent' | 'temporary'
    wildcard: boolean
  }>): Promise<Redirect> => {
    const response = await client.put<ApiResponse<Redirect>>(`/redirects/${id}`, data)
    return response.data.data
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/redirects/${id}`)
  },

  toggle: async (id: number): Promise<Redirect> => {
    const response = await client.post<ApiResponse<Redirect>>(`/redirects/${id}/toggle`)
    return response.data.data
  },
}

export default redirectsApi
