import client from './client'

export interface BlockedIp {
  id: number
  ip_address: string
  reason?: string
  expires_at?: string
  created_at: string
}

export interface HotlinkProtection {
  is_enabled: boolean
  allowed_urls: string[]
  protected_extensions: string[]
  allow_direct_requests: boolean
  redirect_url?: string
}

export interface ProtectedDirectory {
  id: number
  path: string
  name: string
  users: ProtectedDirectoryUser[]
  created_at: string
}

export interface ProtectedDirectoryUser {
  id: number
  username: string
  created_at: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const securityApi = {
  // IP Blocking
  getBlockedIps: async (): Promise<BlockedIp[]> => {
    const response = await client.get<ApiResponse<BlockedIp[]>>('/security/blocked-ips')
    return response.data.data
  },

  blockIp: async (data: {
    ip_address: string
    reason?: string
    expires_at?: string
  }): Promise<BlockedIp> => {
    const response = await client.post<ApiResponse<BlockedIp>>('/security/blocked-ips', data)
    return response.data.data
  },

  unblockIp: async (id: number): Promise<void> => {
    await client.delete(`/security/blocked-ips/${id}`)
  },

  // Hotlink Protection
  getHotlinkProtection: async (): Promise<HotlinkProtection> => {
    const response = await client.get<ApiResponse<HotlinkProtection>>('/security/hotlink-protection')
    return response.data.data
  },

  updateHotlinkProtection: async (data: Partial<HotlinkProtection>): Promise<HotlinkProtection> => {
    const response = await client.post<ApiResponse<HotlinkProtection>>('/security/hotlink-protection', data)
    return response.data.data
  },

  // Directory Protection
  getProtectedDirectories: async (): Promise<ProtectedDirectory[]> => {
    const response = await client.get<ApiResponse<ProtectedDirectory[]>>('/security/protected-directories')
    return response.data.data
  },

  protectDirectory: async (data: {
    path: string
    name: string
  }): Promise<ProtectedDirectory> => {
    const response = await client.post<ApiResponse<ProtectedDirectory>>('/security/protected-directories', data)
    return response.data.data
  },

  unprotectDirectory: async (id: number): Promise<void> => {
    await client.delete(`/security/protected-directories/${id}`)
  },

  addDirectoryUser: async (directoryId: number, data: {
    username: string
    password: string
  }): Promise<ProtectedDirectoryUser> => {
    const response = await client.post<ApiResponse<ProtectedDirectoryUser>>(
      `/security/protected-directories/${directoryId}/users`,
      data
    )
    return response.data.data
  },

  removeDirectoryUser: async (directoryId: number, userId: number): Promise<void> => {
    await client.delete(`/security/protected-directories/${directoryId}/users/${userId}`)
  },
}

export default securityApi
