import client from './client'

export interface SetupStatus {
  setup_required: boolean
  has_admin: boolean
  has_default_package: boolean
  requirements: Requirement[]
  version: string
}

export interface Requirement {
  name: string
  required: string
  current: string
  status: boolean
}

export interface SetupData {
  admin_username: string
  admin_email: string
  admin_password: string
  admin_password_confirmation: string
  server_hostname?: string
  server_ip?: string
  nameservers?: string[]
}

export interface SetupResponse {
  success: boolean
  message: string
  user?: {
    id: number
    uuid: string
    username: string
    email: string
    role: string
  }
  token?: string
  errors?: Record<string, string[]>
}

export const setupApi = {
  /**
   * Check if setup is required
   */
  getStatus: async (): Promise<SetupStatus> => {
    const response = await client.get('/setup/status')
    return response.data
  },

  /**
   * Get system requirements
   */
  getRequirements: async (): Promise<{ requirements: Requirement[]; all_met: boolean }> => {
    const response = await client.get('/setup/requirements')
    return response.data
  },

  /**
   * Complete initial setup
   */
  initialize: async (data: SetupData): Promise<SetupResponse> => {
    const response = await client.post('/setup/initialize', data)
    return response.data
  },
}

export default setupApi
