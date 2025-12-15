import { useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { login, verify2FA, clearError, clear2FA } from '../../store/authSlice'
import type { RootState, AppDispatch } from '../../store'

export default function Login() {
  const dispatch = useDispatch<AppDispatch>()
  const { isLoading, error, requires2FA, tempToken } = useSelector((state: RootState) => state.auth)

  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [twoFactorCode, setTwoFactorCode] = useState('')

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    dispatch(clearError())
    await dispatch(login({ username, password }))
  }

  const handle2FAVerify = async (e: React.FormEvent) => {
    e.preventDefault()
    dispatch(clearError())
    if (tempToken) {
      await dispatch(verify2FA({ temp_token: tempToken, code: twoFactorCode }))
    }
  }

  const handleBack = () => {
    dispatch(clear2FA())
    setTwoFactorCode('')
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-900 via-primary-800 to-primary-900 px-4">
      <div className="max-w-md w-full">
        {/* Logo */}
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold text-white">FreePanel</h1>
          <p className="mt-2 text-primary-200">Web Hosting Control Panel</p>
        </div>

        {/* Login Card */}
        <div className="bg-white rounded-2xl shadow-xl p-8">
          {!requires2FA ? (
            <>
              <h2 className="text-2xl font-semibold text-gray-900 text-center mb-6">
                Sign in to your account
              </h2>

              <form onSubmit={handleLogin} className="space-y-5">
                {error && (
                  <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                    {error}
                  </div>
                )}

                <div>
                  <label htmlFor="username" className="label">
                    Username or Email
                  </label>
                  <input
                    id="username"
                    type="text"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    className="input"
                    placeholder="Enter your username"
                    required
                    autoFocus
                  />
                </div>

                <div>
                  <label htmlFor="password" className="label">
                    Password
                  </label>
                  <input
                    id="password"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="input"
                    placeholder="Enter your password"
                    required
                  />
                </div>

                <button
                  type="submit"
                  disabled={isLoading}
                  className="btn-primary w-full py-3"
                >
                  {isLoading ? (
                    <span className="flex items-center justify-center">
                      <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Signing in...
                    </span>
                  ) : (
                    'Sign in'
                  )}
                </button>
              </form>
            </>
          ) : (
            <>
              <h2 className="text-2xl font-semibold text-gray-900 text-center mb-2">
                Two-Factor Authentication
              </h2>
              <p className="text-gray-500 text-center mb-6">
                Enter the 6-digit code from your authenticator app
              </p>

              <form onSubmit={handle2FAVerify} className="space-y-5">
                {error && (
                  <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                    {error}
                  </div>
                )}

                <div>
                  <label htmlFor="code" className="label">
                    Authentication Code
                  </label>
                  <input
                    id="code"
                    type="text"
                    value={twoFactorCode}
                    onChange={(e) => setTwoFactorCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                    className="input text-center text-2xl tracking-widest"
                    placeholder="000000"
                    maxLength={6}
                    required
                    autoFocus
                  />
                </div>

                <button
                  type="submit"
                  disabled={isLoading || twoFactorCode.length !== 6}
                  className="btn-primary w-full py-3"
                >
                  {isLoading ? (
                    <span className="flex items-center justify-center">
                      <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Verifying...
                    </span>
                  ) : (
                    'Verify'
                  )}
                </button>

                <button
                  type="button"
                  onClick={handleBack}
                  className="btn-secondary w-full"
                >
                  Back to login
                </button>
              </form>
            </>
          )}
        </div>

        {/* Footer */}
        <p className="mt-6 text-center text-primary-300 text-sm">
          FreePanel v1.0.0 - Open Source Hosting Control Panel
        </p>
      </div>
    </div>
  )
}
