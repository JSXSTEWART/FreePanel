import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit'
import * as authApi from '../api/auth'
import type { User } from '../api/auth'

interface AuthState {
  user: User | null
  isAuthenticated: boolean
  isLoading: boolean
  error: string | null
  requires2FA: boolean
  tempToken: string | null
}

const initialState: AuthState = {
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  isAuthenticated: !!localStorage.getItem('token'),
  isLoading: false,
  error: null,
  requires2FA: false,
  tempToken: null,
}

export const login = createAsyncThunk(
  'auth/login',
  async (credentials: authApi.LoginRequest, { rejectWithValue }) => {
    try {
      const response = await authApi.login(credentials)
      return response
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      return rejectWithValue(err.response?.data?.message || 'Login failed')
    }
  }
)

export const verify2FA = createAsyncThunk(
  'auth/verify2FA',
  async (data: authApi.Verify2FARequest, { rejectWithValue }) => {
    try {
      const response = await authApi.verify2FA(data)
      return response
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      return rejectWithValue(err.response?.data?.message || 'Verification failed')
    }
  }
)

export const logout = createAsyncThunk('auth/logout', async () => {
  await authApi.logout()
})

export const fetchUser = createAsyncThunk('auth/fetchUser', async (_, { rejectWithValue }) => {
  try {
    const user = await authApi.getMe()
    return user
  } catch (error: unknown) {
    const err = error as { response?: { data?: { message?: string } } }
    return rejectWithValue(err.response?.data?.message || 'Failed to fetch user')
  }
})

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    clearError: (state) => {
      state.error = null
    },
    clear2FA: (state) => {
      state.requires2FA = false
      state.tempToken = null
    },
  },
  extraReducers: (builder) => {
    builder
      // Login
      .addCase(login.pending, (state) => {
        state.isLoading = true
        state.error = null
      })
      .addCase(login.fulfilled, (state, action) => {
        state.isLoading = false
        if (action.payload.requires_2fa) {
          state.requires2FA = true
          state.tempToken = action.payload.temp_token || null
        } else if (action.payload.user) {
          state.user = action.payload.user
          state.isAuthenticated = true
        }
      })
      .addCase(login.rejected, (state, action) => {
        state.isLoading = false
        state.error = action.payload as string
      })
      // Verify 2FA
      .addCase(verify2FA.pending, (state) => {
        state.isLoading = true
        state.error = null
      })
      .addCase(verify2FA.fulfilled, (state, action) => {
        state.isLoading = false
        state.requires2FA = false
        state.tempToken = null
        if (action.payload.user) {
          state.user = action.payload.user
          state.isAuthenticated = true
        }
      })
      .addCase(verify2FA.rejected, (state, action) => {
        state.isLoading = false
        state.error = action.payload as string
      })
      // Logout
      .addCase(logout.fulfilled, (state) => {
        state.user = null
        state.isAuthenticated = false
        state.requires2FA = false
        state.tempToken = null
      })
      // Fetch User
      .addCase(fetchUser.pending, (state) => {
        state.isLoading = true
      })
      .addCase(fetchUser.fulfilled, (state, action) => {
        state.isLoading = false
        state.user = action.payload
        localStorage.setItem('user', JSON.stringify(action.payload))
      })
      .addCase(fetchUser.rejected, (state) => {
        state.isLoading = false
        state.user = null
        state.isAuthenticated = false
      })
  },
})

export const { clearError, clear2FA } = authSlice.actions
export default authSlice.reducer
