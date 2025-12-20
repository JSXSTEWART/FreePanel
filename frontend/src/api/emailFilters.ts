import client from './client'

export interface EmailFilter {
  id: number
  name: string
  condition_field: string
  condition_operator: string
  condition_value: string
  action: string
  action_value?: string
  order: number
  is_active: boolean
  created_at: string
}

export interface FilterOptions {
  fields: { value: string; label: string }[]
  operators: { value: string; label: string }[]
  actions: { value: string; label: string }[]
}

export interface SpamSettings {
  spam_score_threshold: number
  spam_action: 'deliver' | 'mark' | 'quarantine' | 'delete'
  spam_folder: string
  learn_from_spam: boolean
  learn_from_ham: boolean
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const emailFiltersApi = {
  list: async (): Promise<EmailFilter[]> => {
    const response = await client.get<ApiResponse<EmailFilter[]>>('/email-filters')
    return response.data.data
  },

  getOptions: async (): Promise<FilterOptions> => {
    const response = await client.get<ApiResponse<FilterOptions>>('/email-filters/options')
    return response.data.data
  },

  get: async (id: number): Promise<EmailFilter> => {
    const response = await client.get<ApiResponse<EmailFilter>>(`/email-filters/${id}`)
    return response.data.data
  },

  create: async (data: {
    name: string
    condition_field: string
    condition_operator: string
    condition_value: string
    action: string
    action_value?: string
  }): Promise<EmailFilter> => {
    const response = await client.post<ApiResponse<EmailFilter>>('/email-filters', data)
    return response.data.data
  },

  update: async (id: number, data: Partial<{
    name: string
    condition_field: string
    condition_operator: string
    condition_value: string
    action: string
    action_value?: string
  }>): Promise<EmailFilter> => {
    const response = await client.put<ApiResponse<EmailFilter>>(`/email-filters/${id}`, data)
    return response.data.data
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/email-filters/${id}`)
  },

  toggle: async (id: number): Promise<EmailFilter> => {
    const response = await client.post<ApiResponse<EmailFilter>>(`/email-filters/${id}/toggle`)
    return response.data.data
  },

  reorder: async (ids: number[]): Promise<void> => {
    await client.post('/email-filters/reorder', { ids })
  },

  // Spam Settings
  getSpamSettings: async (): Promise<SpamSettings> => {
    const response = await client.get<ApiResponse<SpamSettings>>('/email-filters/spam/settings')
    return response.data.data
  },

  updateSpamSettings: async (data: Partial<SpamSettings>): Promise<SpamSettings> => {
    const response = await client.put<ApiResponse<SpamSettings>>('/email-filters/spam/settings', data)
    return response.data.data
  },

  // Whitelist
  getWhitelist: async (): Promise<string[]> => {
    const response = await client.get<ApiResponse<string[]>>('/email-filters/spam/whitelist')
    return response.data.data
  },

  addToWhitelist: async (email: string): Promise<void> => {
    await client.post('/email-filters/spam/whitelist', { email })
  },

  removeFromWhitelist: async (email: string): Promise<void> => {
    await client.delete(`/email-filters/spam/whitelist/${encodeURIComponent(email)}`)
  },

  // Blacklist
  getBlacklist: async (): Promise<string[]> => {
    const response = await client.get<ApiResponse<string[]>>('/email-filters/spam/blacklist')
    return response.data.data
  },

  addToBlacklist: async (email: string): Promise<void> => {
    await client.post('/email-filters/spam/blacklist', { email })
  },

  removeFromBlacklist: async (email: string): Promise<void> => {
    await client.delete(`/email-filters/spam/blacklist/${encodeURIComponent(email)}`)
  },
}

export default emailFiltersApi
