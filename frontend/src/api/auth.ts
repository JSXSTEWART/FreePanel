import client, { setAuthToken } from './client'

export interface LoginRequest {
  username: string
  password: string
}

export interface LoginResponse {
  success: boolean
  requires_2fa?: boolean
  temp_token?: string
  user?: User
  token?: string
}

export interface User {
  id: number
  uuid: string
  username: string
  email: string
  role: 'admin' | 'reseller' | 'user'
  two_factor_enabled: boolean
  last_login_at: string | null
  account?: {
    id: number
    uuid: string
    domain: string
    status: string
    package: string
  }
}

export interface Verify2FARequest {
  temp_token: string
  code: string
}

// Login
export const login = async (data: LoginRequest): Promise<LoginResponse> => {
  const response = await client.post<LoginResponse>('/auth/login', data)

  if (response.data.token) {
    setAuthToken(response.data.token)
    if (response.data.user) {
      localStorage.setItem('user', JSON.stringify(response.data.user))
    }
  }

  return response.data
}

// Verify 2FA
export const verify2FA = async (data: Verify2FARequest): Promise<LoginResponse> => {
  const response = await client.post<LoginResponse>('/auth/2fa/verify', data)

  if (response.data.token) {
    setAuthToken(response.data.token)
    if (response.data.user) {
      localStorage.setItem('user', JSON.stringify(response.data.user))
    }
  }

  return response.data
}

// Logout
export const logout = async (): Promise<void> => {
  try {
    await client.post('/auth/logout')
  } finally {
    setAuthToken(null)
    localStorage.removeItem('user')
  }
}

// Get current user
export const getMe = async (): Promise<User> => {
  const response = await client.get<{ success: boolean; user: User }>('/auth/me')
  return response.data.user
}

// Get 2FA QR code
export const get2FAQRCode = async (): Promise<{ secret: string; qr_code: string }> => {
  const response = await client.get('/auth/2fa/qrcode')
  return response.data
}

// Enable 2FA
export const enable2FA = async (password: string, code: string): Promise<{ recovery_codes: string[] }> => {
  const response = await client.post('/auth/2fa/enable', { password, code })
  return response.data
}

// Disable 2FA
export const disable2FA = async (password: string, code: string): Promise<void> => {
  await client.post('/auth/2fa/disable', { password, code })
}

// Forgot password
export const forgotPassword = async (email: string): Promise<void> => {
  await client.post('/auth/password/forgot', { email })
}

// Reset password
export const resetPassword = async (
  email: string,
  token: string,
  password: string,
  password_confirmation: string
): Promise<void> => {
  await client.post('/auth/password/reset', {
    email,
    token,
    password,
    password_confirmation,
  })
}
