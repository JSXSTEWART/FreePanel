import client from './client'

export interface CronJob {
  id: number
  command: string
  minute: string
  hour: string
  day: string
  month: string
  weekday: string
  is_active: boolean
  last_run?: string
  next_run?: string
  created_at: string
}

export interface CronPreset {
  label: string
  minute: string
  hour: string
  day: string
  month: string
  weekday: string
  description: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const cronApi = {
  list: async (): Promise<CronJob[]> => {
    const response = await client.get<ApiResponse<CronJob[]>>('/cron')
    return response.data.data
  },

  get: async (id: number): Promise<CronJob> => {
    const response = await client.get<ApiResponse<CronJob>>(`/cron/${id}`)
    return response.data.data
  },

  create: async (data: {
    command: string
    minute: string
    hour: string
    day: string
    month: string
    weekday: string
  }): Promise<CronJob> => {
    const response = await client.post<ApiResponse<CronJob>>('/cron', data)
    return response.data.data
  },

  update: async (id: number, data: Partial<{
    command: string
    minute: string
    hour: string
    day: string
    month: string
    weekday: string
  }>): Promise<CronJob> => {
    const response = await client.put<ApiResponse<CronJob>>(`/cron/${id}`, data)
    return response.data.data
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/cron/${id}`)
  },

  toggle: async (id: number): Promise<CronJob> => {
    const response = await client.post<ApiResponse<CronJob>>(`/cron/${id}/toggle`)
    return response.data.data
  },

  getPresets: async (): Promise<CronPreset[]> => {
    const response = await client.get<ApiResponse<CronPreset[]>>('/cron/presets')
    return response.data.data
  },

  getCrontab: async (): Promise<string> => {
    const response = await client.get<ApiResponse<string>>('/cron/crontab')
    return response.data.data
  },
}

export default cronApi
