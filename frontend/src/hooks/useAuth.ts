import { useEffect } from 'react'
import { useSelector, useDispatch } from 'react-redux'
import { useNavigate } from 'react-router-dom'
import type { RootState, AppDispatch } from '../store'
import { logout as logoutAction, fetchUser } from '../store/authSlice'

export const useAuth = () => {
  const dispatch = useDispatch<AppDispatch>()
  const navigate = useNavigate()
  const { user, isAuthenticated, isLoading, error, requires2FA, tempToken } = useSelector(
    (state: RootState) => state.auth
  )

  useEffect(() => {
    // Verify token on mount if we have one
    const token = localStorage.getItem('token')
    if (token && !user) {
      dispatch(fetchUser())
    }
  }, [dispatch, user])

  const logout = async () => {
    await dispatch(logoutAction())
    navigate('/login')
  }

  return {
    user,
    isAuthenticated,
    isLoading,
    error,
    requires2FA,
    tempToken,
    logout,
    isAdmin: user?.role === 'admin',
    isReseller: user?.role === 'reseller',
  }
}
