import { useState, useEffect } from 'react'
import { Card, CardHeader, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { useAuth } from '../../hooks/useAuth'
import { get2FAQRCode, enable2FA, disable2FA } from '../../api/auth'
import client from '../../api/client'
import toast from 'react-hot-toast'
import {
  ShieldCheckIcon,
  KeyIcon,
  UserIcon,
  EyeIcon,
  EyeSlashIcon,
  ClipboardDocumentIcon,
} from '@heroicons/react/24/outline'

export default function Settings() {
  const { user, refreshUser } = useAuth()
  const [loading, setLoading] = useState(false)

  // Profile form
  const [email, setEmail] = useState(user?.email || '')

  // Password form
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    new_password: '',
    confirm_password: '',
  })
  const [showPasswords, setShowPasswords] = useState(false)

  // 2FA state
  const [show2FASetup, setShow2FASetup] = useState(false)
  const [show2FADisable, setShow2FADisable] = useState(false)
  const [qrCode, setQrCode] = useState<{ secret: string; qr_code: string } | null>(null)
  const [twoFACode, setTwoFACode] = useState('')
  const [twoFAPassword, setTwoFAPassword] = useState('')
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([])

  // API tokens
  const [tokens, setTokens] = useState<Array<{ id: number; name: string; created_at: string }>>([])
  const [showTokenModal, setShowTokenModal] = useState(false)
  const [newTokenName, setNewTokenName] = useState('')
  const [newToken, setNewToken] = useState<string | null>(null)

  useEffect(() => {
    loadTokens()
  }, [])

  const loadTokens = async () => {
    try {
      const response = await client.get<{ data: Array<{ id: number; name: string; created_at: string }> }>('/user/tokens')
      setTokens(response.data.data || [])
    } catch (error) {
      // Token API might not exist yet
    }
  }

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!email) {
      toast.error('Email is required')
      return
    }

    try {
      setLoading(true)
      await client.put('/user/profile', { email })
      toast.success('Profile updated successfully')
      refreshUser?.()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to update profile')
    } finally {
      setLoading(false)
    }
  }

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault()
    if (passwordForm.new_password !== passwordForm.confirm_password) {
      toast.error('Passwords do not match')
      return
    }
    if (passwordForm.new_password.length < 8) {
      toast.error('Password must be at least 8 characters')
      return
    }

    try {
      setLoading(true)
      await client.put('/user/password', {
        current_password: passwordForm.current_password,
        password: passwordForm.new_password,
        password_confirmation: passwordForm.confirm_password,
      })
      toast.success('Password changed successfully')
      setPasswordForm({ current_password: '', new_password: '', confirm_password: '' })
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to change password')
    } finally {
      setLoading(false)
    }
  }

  const handleSetup2FA = async () => {
    try {
      setLoading(true)
      const data = await get2FAQRCode()
      setQrCode(data)
      setShow2FASetup(true)
    } catch (error) {
      toast.error('Failed to setup 2FA')
    } finally {
      setLoading(false)
    }
  }

  const handleEnable2FA = async () => {
    if (!twoFACode || !twoFAPassword) {
      toast.error('Please enter your password and 2FA code')
      return
    }

    try {
      setLoading(true)
      const result = await enable2FA(twoFAPassword, twoFACode)
      setRecoveryCodes(result.recovery_codes)
      toast.success('Two-factor authentication enabled')
      refreshUser?.()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to enable 2FA')
    } finally {
      setLoading(false)
    }
  }

  const handleDisable2FA = async () => {
    if (!twoFACode || !twoFAPassword) {
      toast.error('Please enter your password and 2FA code')
      return
    }

    try {
      setLoading(true)
      await disable2FA(twoFAPassword, twoFACode)
      toast.success('Two-factor authentication disabled')
      setShow2FADisable(false)
      setTwoFACode('')
      setTwoFAPassword('')
      refreshUser?.()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to disable 2FA')
    } finally {
      setLoading(false)
    }
  }

  const handleCreateToken = async () => {
    if (!newTokenName) {
      toast.error('Please enter a token name')
      return
    }

    try {
      setLoading(true)
      const response = await client.post<{ data: { token: string } }>('/user/tokens', { name: newTokenName })
      setNewToken(response.data.data.token)
      loadTokens()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create token')
    } finally {
      setLoading(false)
    }
  }

  const handleDeleteToken = async (id: number) => {
    if (!confirm('Are you sure you want to revoke this token?')) return

    try {
      await client.delete(`/user/tokens/${id}`)
      toast.success('Token revoked')
      loadTokens()
    } catch (error) {
      toast.error('Failed to revoke token')
    }
  }

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text)
      toast.success('Copied to clipboard')
    } catch {
      toast.error('Failed to copy to clipboard')
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Account Settings</h1>
        <p className="text-gray-500">Manage your account preferences and security</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Profile */}
        <Card>
          <CardHeader>
            <div className="flex items-center">
              <UserIcon className="w-5 h-5 text-gray-500 mr-2" />
              <h2 className="text-lg font-semibold text-gray-900">Profile</h2>
            </div>
          </CardHeader>
          <CardBody>
            <form onSubmit={handleUpdateProfile} className="space-y-4">
              <div>
                <label className="label">Username</label>
                <input type="text" className="input bg-gray-100" value={user?.username} disabled />
              </div>
              <div>
                <label className="label">Email</label>
                <input
                  type="email"
                  className="input"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </div>
              <Button variant="primary" type="submit" disabled={loading}>
                {loading ? 'Saving...' : 'Save Changes'}
              </Button>
            </form>
          </CardBody>
        </Card>

        {/* Change Password */}
        <Card>
          <CardHeader>
            <div className="flex items-center">
              <KeyIcon className="w-5 h-5 text-gray-500 mr-2" />
              <h2 className="text-lg font-semibold text-gray-900">Change Password</h2>
            </div>
          </CardHeader>
          <CardBody>
            <form onSubmit={handleChangePassword} className="space-y-4">
              <div>
                <label className="label">Current Password</label>
                <div className="relative">
                  <input
                    type={showPasswords ? 'text' : 'password'}
                    className="input pr-10"
                    value={passwordForm.current_password}
                    onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })}
                  />
                  <button
                    type="button"
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
                    onClick={() => setShowPasswords(!showPasswords)}
                  >
                    {showPasswords ? <EyeSlashIcon className="w-5 h-5" /> : <EyeIcon className="w-5 h-5" />}
                  </button>
                </div>
              </div>
              <div>
                <label className="label">New Password</label>
                <input
                  type={showPasswords ? 'text' : 'password'}
                  className="input"
                  placeholder="Min 8 characters"
                  value={passwordForm.new_password}
                  onChange={(e) => setPasswordForm({ ...passwordForm, new_password: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Confirm New Password</label>
                <input
                  type={showPasswords ? 'text' : 'password'}
                  className="input"
                  value={passwordForm.confirm_password}
                  onChange={(e) => setPasswordForm({ ...passwordForm, confirm_password: e.target.value })}
                />
              </div>
              <Button variant="primary" type="submit" disabled={loading}>
                {loading ? 'Updating...' : 'Update Password'}
              </Button>
            </form>
          </CardBody>
        </Card>

        {/* Two-Factor Auth */}
        <Card>
          <CardHeader>
            <div className="flex items-center">
              <ShieldCheckIcon className="w-5 h-5 text-gray-500 mr-2" />
              <h2 className="text-lg font-semibold text-gray-900">Two-Factor Authentication</h2>
            </div>
          </CardHeader>
          <CardBody>
            {user?.two_factor_enabled ? (
              <div className="space-y-4">
                <div className="flex items-center p-4 bg-green-50 rounded-lg">
                  <ShieldCheckIcon className="w-8 h-8 text-green-500 mr-3" />
                  <div>
                    <p className="font-medium text-green-800">2FA is enabled</p>
                    <p className="text-sm text-green-600">Your account is protected with two-factor authentication.</p>
                  </div>
                </div>
                <Button variant="danger" onClick={() => setShow2FADisable(true)}>
                  Disable 2FA
                </Button>
              </div>
            ) : (
              <div className="space-y-4">
                <p className="text-gray-600">
                  Add an extra layer of security to your account by enabling two-factor authentication.
                </p>
                <Button variant="success" onClick={handleSetup2FA} disabled={loading}>
                  Enable 2FA
                </Button>
              </div>
            )}
          </CardBody>
        </Card>

        {/* API Tokens */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <KeyIcon className="w-5 h-5 text-gray-500 mr-2" />
                <h2 className="text-lg font-semibold text-gray-900">API Tokens</h2>
              </div>
              <Button variant="secondary" onClick={() => setShowTokenModal(true)} className="text-sm">
                Generate Token
              </Button>
            </div>
          </CardHeader>
          <CardBody>
            {tokens.length === 0 ? (
              <p className="text-gray-500 text-center py-4">
                No API tokens. Generate one to access your account programmatically.
              </p>
            ) : (
              <div className="space-y-2">
                {tokens.map((token) => (
                  <div key={token.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                      <p className="font-medium text-gray-900">{token.name}</p>
                      <p className="text-xs text-gray-500">Created {new Date(token.created_at).toLocaleDateString()}</p>
                    </div>
                    <button
                      onClick={() => handleDeleteToken(token.id)}
                      className="text-red-600 hover:text-red-800 text-sm"
                    >
                      Revoke
                    </button>
                  </div>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      </div>

      {/* 2FA Setup Modal */}
      {show2FASetup && qrCode && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Setup Two-Factor Authentication</h3>
            </div>
            <div className="p-6 space-y-4">
              {recoveryCodes.length > 0 ? (
                <div className="space-y-4">
                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p className="text-green-800 font-medium mb-2">2FA Enabled Successfully!</p>
                    <p className="text-green-700 text-sm">Save these recovery codes in a safe place:</p>
                  </div>
                  <div className="bg-gray-100 rounded-lg p-4 font-mono text-sm">
                    {recoveryCodes.map((code, i) => (
                      <div key={i}>{code}</div>
                    ))}
                  </div>
                  <Button
                    variant="secondary"
                    className="w-full"
                    onClick={() => copyToClipboard(recoveryCodes.join('\n'))}
                  >
                    <ClipboardDocumentIcon className="w-4 h-4 mr-2" />
                    Copy Codes
                  </Button>
                </div>
              ) : (
                <>
                  <p className="text-gray-600 text-sm">
                    Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):
                  </p>
                  <div className="flex justify-center">
                    <img src={qrCode.qr_code} alt="2FA QR Code" className="w-48 h-48" />
                  </div>
                  <div>
                    <label className="label text-xs">Or enter this code manually:</label>
                    <div className="flex items-center">
                      <input
                        type="text"
                        className="input font-mono text-sm bg-gray-100"
                        value={qrCode.secret}
                        readOnly
                      />
                      <button
                        type="button"
                        className="ml-2 p-2 text-gray-500 hover:text-gray-700"
                        onClick={() => copyToClipboard(qrCode.secret)}
                      >
                        <ClipboardDocumentIcon className="w-5 h-5" />
                      </button>
                    </div>
                  </div>
                  <div>
                    <label className="label">Your Password</label>
                    <input
                      type="password"
                      className="input"
                      value={twoFAPassword}
                      onChange={(e) => setTwoFAPassword(e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="label">Verification Code</label>
                    <input
                      type="text"
                      className="input"
                      placeholder="Enter 6-digit code"
                      value={twoFACode}
                      onChange={(e) => setTwoFACode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                    />
                  </div>
                </>
              )}
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => {
                setShow2FASetup(false)
                setQrCode(null)
                setTwoFACode('')
                setTwoFAPassword('')
                setRecoveryCodes([])
              }}>
                {recoveryCodes.length > 0 ? 'Done' : 'Cancel'}
              </Button>
              {recoveryCodes.length === 0 && (
                <Button variant="primary" onClick={handleEnable2FA} disabled={loading}>
                  {loading ? 'Enabling...' : 'Enable 2FA'}
                </Button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* 2FA Disable Modal */}
      {show2FADisable && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Disable Two-Factor Authentication</h3>
            </div>
            <div className="p-6 space-y-4">
              <p className="text-gray-600">
                Enter your password and a verification code to disable 2FA.
              </p>
              <div>
                <label className="label">Password</label>
                <input
                  type="password"
                  className="input"
                  value={twoFAPassword}
                  onChange={(e) => setTwoFAPassword(e.target.value)}
                />
              </div>
              <div>
                <label className="label">Verification Code</label>
                <input
                  type="text"
                  className="input"
                  placeholder="Enter 6-digit code"
                  value={twoFACode}
                  onChange={(e) => setTwoFACode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => {
                setShow2FADisable(false)
                setTwoFACode('')
                setTwoFAPassword('')
              }}>Cancel</Button>
              <Button variant="danger" onClick={handleDisable2FA} disabled={loading}>
                {loading ? 'Disabling...' : 'Disable 2FA'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Create Token Modal */}
      {showTokenModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Generate API Token</h3>
            </div>
            <div className="p-6 space-y-4">
              {newToken ? (
                <div className="space-y-4">
                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p className="text-green-800 text-sm">Token created! Copy it now, you won't see it again.</p>
                  </div>
                  <div className="flex items-center">
                    <input
                      type="text"
                      className="input font-mono text-sm bg-gray-100"
                      value={newToken}
                      readOnly
                    />
                    <button
                      type="button"
                      className="ml-2 p-2 text-gray-500 hover:text-gray-700"
                      onClick={() => copyToClipboard(newToken)}
                    >
                      <ClipboardDocumentIcon className="w-5 h-5" />
                    </button>
                  </div>
                </div>
              ) : (
                <div>
                  <label className="label">Token Name</label>
                  <input
                    type="text"
                    className="input"
                    placeholder="e.g., My API Script"
                    value={newTokenName}
                    onChange={(e) => setNewTokenName(e.target.value)}
                  />
                </div>
              )}
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => {
                setShowTokenModal(false)
                setNewTokenName('')
                setNewToken(null)
              }}>
                {newToken ? 'Done' : 'Cancel'}
              </Button>
              {!newToken && (
                <Button variant="primary" onClick={handleCreateToken} disabled={loading}>
                  {loading ? 'Generating...' : 'Generate Token'}
                </Button>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
