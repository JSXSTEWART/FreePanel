import client from './client'

export interface PhpConfig {
  version: string
  memory_limit: string
  max_execution_time: number
  max_input_time: number
  post_max_size: string
  upload_max_filesize: string
  max_file_uploads: number
  error_reporting: string
  display_errors: boolean
  log_errors: boolean
  date_timezone: string
  allow_url_fopen: boolean
  allow_url_include: boolean
}

export interface PhpVersion {
  version: string
  path: string
  is_active: boolean
  is_default: boolean
}

export interface PhpExtension {
  name: string
  version?: string
  is_enabled: boolean
  is_zend: boolean
}

export interface PhpInfo {
  version: string
  api: string
  extensions: string[]
  ini_path: string
  loaded_extensions: number
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const phpApi = {
  getConfig: async (): Promise<PhpConfig> => {
    const response = await client.get<ApiResponse<PhpConfig>>('/php/config')
    return response.data.data
  },

  updateConfig: async (data: Partial<PhpConfig>): Promise<PhpConfig> => {
    const response = await client.put<ApiResponse<PhpConfig>>('/php/config', data)
    return response.data.data
  },

  getVersions: async (): Promise<PhpVersion[]> => {
    const response = await client.get<ApiResponse<PhpVersion[]>>('/php/versions')
    return response.data.data
  },

  setVersion: async (version: string): Promise<{ version: string }> => {
    const response = await client.put<ApiResponse<{ version: string }>>('/php/version', { version })
    return response.data.data
  },

  getInfo: async (): Promise<PhpInfo> => {
    const response = await client.get<ApiResponse<PhpInfo>>('/php/info')
    return response.data.data
  },

  getExtensions: async (): Promise<PhpExtension[]> => {
    const response = await client.get<ApiResponse<PhpExtension[]>>('/php/extensions')
    return response.data.data
  },
}

export default phpApi
