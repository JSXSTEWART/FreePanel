import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { handleOAuthCallback } from '../../api/auth'
import { useDispatch } from 'react-redux'
import { setUser } from '../../store/authSlice'

export default function OAuthCallback() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const dispatch = useDispatch()
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const processCallback = async () => {
      try {
        // Extract provider from state parameter
        const params = new URLSearchParams(window.location.search)
        const state = params.get('state')
        
        let provider = 'google' // default fallback
        if (state) {
          try {
            const stateData = JSON.parse(atob(state))
            provider = stateData.provider || 'google'
          } catch (e) {
            console.warn('Could not decode state parameter, using default provider')
          }
        }
        
        // Check for error
        if (params.get('error')) {
          setError(params.get('error_description') || 'OAuth authentication failed')
          setTimeout(() => navigate('/login'), 3000)
          return
        }

        // Handle the callback
        const response = await handleOAuthCallback(provider, params)

        if (response.success && response.user) {
          dispatch(setUser(response.user))
          navigate('/dashboard')
        } else {
          setError('Authentication failed')
          setTimeout(() => navigate('/login'), 3000)
        }
      } catch (err: any) {
        console.error('OAuth callback error:', err)
        setError(err.response?.data?.message || 'Authentication failed')
        setTimeout(() => navigate('/login'), 3000)
      }
    }

    processCallback()
  }, [navigate, dispatch, searchParams])

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-900 via-primary-800 to-primary-900 px-4">
      <div className="max-w-md w-full">
        <div className="bg-white rounded-2xl shadow-xl p-8 text-center">
          {error ? (
            <>
              <div className="text-red-600 text-5xl mb-4">✕</div>
              <h2 className="text-2xl font-semibold text-gray-900 mb-2">
                Authentication Failed
              </h2>
              <p className="text-gray-600 mb-4">{error}</p>
              <p className="text-sm text-gray-500">Redirecting to login...</p>
            </>
          ) : (
            <>
              <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-primary-600 mx-auto mb-4"></div>
              <h2 className="text-2xl font-semibold text-gray-900 mb-2">
                Completing Authentication
              </h2>
              <p className="text-gray-600">Please wait...</p>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
