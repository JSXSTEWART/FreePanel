import client from './client'

export interface Plan {
  id: number
  name: string
  description?: string
  disk_quota: number
  bandwidth_quota: number
  max_domains: number
  max_subdomains: number
  max_email_accounts: number
  max_email_forwarders: number
  max_databases: number
  max_ftp_accounts: number
  max_parked_domains: number
  features: string[]
  is_active: boolean
  is_reseller_package: boolean
}

export interface AccountUsage {
  disk_used: number
  disk_quota: number
  disk_percentage: number
  bandwidth_used: number
  bandwidth_quota: number
  bandwidth_percentage: number
  domains_used: number
  domains_limit: number
  subdomains_used: number
  subdomains_limit: number
  email_accounts_used: number
  email_accounts_limit: number
  databases_used: number
  databases_limit: number
  ftp_accounts_used: number
  ftp_accounts_limit: number
}

export interface Feature {
  name: string
  description: string
  is_enabled: boolean
  is_pro: boolean
}

export interface FeatureGate {
  feature: string
  has_access: boolean
  upgrade_plan?: string
  upgrade_message?: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const planApi = {
  // Get current user's plan
  getCurrentPlan: async (): Promise<Plan> => {
    const response = await client.get<ApiResponse<Plan>>('/auth/me')
    return response.data.data.account?.package || null
  },

  // Get account usage/quota information
  getUsage: async (): Promise<AccountUsage> => {
    const response = await client.get<ApiResponse<AccountUsage>>('/stats/resource-usage')
    return response.data.data
  },

  // Check if a specific feature is available
  checkFeature: async (feature: string): Promise<FeatureGate> => {
    try {
      // Make a simple request to check feature access
      const response = await client.get<ApiResponse<FeatureGate>>(`/features/${feature}/check`)
      return response.data.data
    } catch (error: unknown) {
      const err = error as { response?: { status?: number } }
      if (err.response?.status === 403) {
        return {
          feature,
          has_access: false,
          upgrade_message: 'This feature requires a higher plan',
        }
      }
      throw error
    }
  },

  // Get list of all features and their availability
  getFeatures: async (): Promise<Feature[]> => {
    const response = await client.get<ApiResponse<Feature[]>>('/features')
    return response.data.data
  },

  // Format quota values for display
  formatQuota: (value: number, type: 'disk' | 'bandwidth'): string => {
    if (value === 0) return 'Unlimited'

    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    let unitIndex = 0
    let size = value

    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024
      unitIndex++
    }

    return `${size.toFixed(2)} ${units[unitIndex]}`
  },

  // Format limit values for display
  formatLimit: (value: number): string => {
    if (value === 0) return 'Unlimited'
    return value.toString()
  },
}

export default planApi
