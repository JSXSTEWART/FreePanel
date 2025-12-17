import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { emailApi, domainsApi, EmailAccount, EmailForwarder, Domain } from '../../api'
import toast from 'react-hot-toast'
import {
  PlusIcon,
  EnvelopeIcon,
  TrashIcon,
  KeyIcon,
  ArrowRightIcon,
} from '@heroicons/react/24/outline'

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  if (bytes === -1) return 'Unlimited'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

export default function Email() {
  const [accounts, setAccounts] = useState<EmailAccount[]>([])
  const [forwarders, setForwarders] = useState<EmailForwarder[]>([])
  const [domains, setDomains] = useState<Domain[]>([])
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState<'accounts' | 'forwarders'>('accounts')
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showPasswordModal, setShowPasswordModal] = useState(false)
  const [showForwarderModal, setShowForwarderModal] = useState(false)
  const [selectedAccount, setSelectedAccount] = useState<EmailAccount | null>(null)
  const [actionLoading, setActionLoading] = useState<number | null>(null)

  // Form state
  const [newAccount, setNewAccount] = useState({
    email: '',
    domain: '',
    password: '',
    confirmPassword: '',
    quota: 1024,
  })
  const [newPassword, setNewPassword] = useState({ password: '', confirmPassword: '' })
  const [newForwarder, setNewForwarder] = useState({ source: '', sourceDomain: '', destination: '' })

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      setLoading(true)
      const [accountsData, forwardersData, domainsData] = await Promise.all([
        emailApi.listAccounts(),
        emailApi.listForwarders(),
        domainsApi.list(),
      ])
      setAccounts(accountsData)
      setForwarders(forwardersData)
      setDomains(domainsData)
      if (domainsData.length > 0) {
        const mainDomain = domainsData.find(d => d.is_main) || domainsData[0]
        setNewAccount(prev => ({ ...prev, domain: mainDomain.name }))
        setNewForwarder(prev => ({ ...prev, sourceDomain: mainDomain.name }))
      }
    } catch (error) {
      toast.error('Failed to load email accounts')
    } finally {
      setLoading(false)
    }
  }

  const handleCreateAccount = async () => {
    if (!newAccount.email || !newAccount.password) {
      toast.error('Please fill in all required fields')
      return
    }
    if (newAccount.password !== newAccount.confirmPassword) {
      toast.error('Passwords do not match')
      return
    }
    if (newAccount.password.length < 8) {
      toast.error('Password must be at least 8 characters')
      return
    }

    try {
      setActionLoading(-1)
      await emailApi.createAccount({
        email: `${newAccount.email}@${newAccount.domain}`,
        password: newAccount.password,
        quota: newAccount.quota * 1024 * 1024,
      })
      toast.success('Email account created successfully')
      setShowCreateModal(false)
      setNewAccount({ email: '', domain: newAccount.domain, password: '', confirmPassword: '', quota: 1024 })
      loadData()
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to create email account')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDeleteAccount = async (account: EmailAccount) => {
    if (!confirm(`Are you sure you want to delete "${account.email}"? All emails will be lost.`)) {
      return
    }

    try {
      setActionLoading(account.id)
      await emailApi.deleteAccount(account.id)
      toast.success('Email account deleted')
      loadData()
    } catch (error) {
      toast.error('Failed to delete email account')
    } finally {
      setActionLoading(null)
    }
  }

  const handleChangePassword = async () => {
    if (!selectedAccount) return
    if (newPassword.password !== newPassword.confirmPassword) {
      toast.error('Passwords do not match')
      return
    }
    if (newPassword.password.length < 8) {
      toast.error('Password must be at least 8 characters')
      return
    }

    try {
      setActionLoading(selectedAccount.id)
      await emailApi.changePassword(selectedAccount.id, newPassword.password)
      toast.success('Password changed successfully')
      setShowPasswordModal(false)
      setSelectedAccount(null)
      setNewPassword({ password: '', confirmPassword: '' })
    } catch (error) {
      toast.error('Failed to change password')
    } finally {
      setActionLoading(null)
    }
  }

  const handleCreateForwarder = async () => {
    if (!newForwarder.source || !newForwarder.destination) {
      toast.error('Please fill in all required fields')
      return
    }

    try {
      setActionLoading(-2)
      await emailApi.createForwarder({
        source: `${newForwarder.source}@${newForwarder.sourceDomain}`,
        destination: newForwarder.destination,
      })
      toast.success('Forwarder created successfully')
      setShowForwarderModal(false)
      setNewForwarder({ source: '', sourceDomain: newForwarder.sourceDomain, destination: '' })
      loadData()
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to create forwarder')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDeleteForwarder = async (forwarder: EmailForwarder) => {
    if (!confirm(`Are you sure you want to delete this forwarder?`)) {
      return
    }

    try {
      setActionLoading(forwarder.id + 10000)
      await emailApi.deleteForwarder(forwarder.id)
      toast.success('Forwarder deleted')
      loadData()
    } catch (error) {
      toast.error('Failed to delete forwarder')
    } finally {
      setActionLoading(null)
    }
  }

  const openPasswordModal = (account: EmailAccount) => {
    setSelectedAccount(account)
    setShowPasswordModal(true)
  }

  const getQuotaPercent = (account: EmailAccount): number => {
    if (!account.quota || account.quota <= 0) return 0
    return Math.min(100, (account.quota_used / account.quota) * 100)
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Email Accounts</h1>
          <p className="text-gray-500">Manage your email accounts and forwarders</p>
        </div>
        <div className="flex space-x-3">
          {activeTab === 'accounts' ? (
            <Button variant="primary" onClick={() => setShowCreateModal(true)}>
              <PlusIcon className="w-5 h-5 mr-2" />
              Create Email
            </Button>
          ) : (
            <Button variant="primary" onClick={() => setShowForwarderModal(true)}>
              <PlusIcon className="w-5 h-5 mr-2" />
              Add Forwarder
            </Button>
          )}
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-primary-600">{accounts.length}</div>
            <div className="text-sm text-gray-500">Email Accounts</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-green-600">{forwarders.length}</div>
            <div className="text-sm text-gray-500">Forwarders</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-blue-600">
              {formatBytes(accounts.reduce((sum, a) => sum + a.quota_used, 0))}
            </div>
            <div className="text-sm text-gray-500">Total Storage Used</div>
          </CardBody>
        </Card>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('accounts')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'accounts'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Email Accounts ({accounts.length})
          </button>
          <button
            onClick={() => setActiveTab('forwarders')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'forwarders'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Forwarders ({forwarders.length})
          </button>
        </nav>
      </div>

      {/* Email Accounts Table */}
      {activeTab === 'accounts' && (
        <Card>
          <CardBody className="p-0">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quota Used</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {accounts.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-6 py-8 text-center text-gray-500">
                      No email accounts found. Create your first email account to get started.
                    </td>
                  </tr>
                ) : (
                  accounts.map((account) => (
                    <tr key={account.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <EnvelopeIcon className="w-5 h-5 text-gray-400 mr-3" />
                          <span className="font-medium text-gray-900">{account.email}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="w-36">
                          <div className="flex justify-between text-xs mb-1">
                            <span>{formatBytes(account.quota_used)}</span>
                            <span>/ {formatBytes(account.quota)}</span>
                          </div>
                          <div className="w-full bg-gray-200 rounded-full h-1.5">
                            <div
                              className={`h-1.5 rounded-full ${getQuotaPercent(account) > 90 ? 'bg-red-500' : 'bg-blue-500'}`}
                              style={{ width: `${getQuotaPercent(account)}%` }}
                            ></div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {new Date(account.created_at).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        <div className="flex space-x-2">
                          <button
                            onClick={() => openPasswordModal(account)}
                            className="text-primary-600 hover:text-primary-800"
                            title="Change Password"
                          >
                            <KeyIcon className="w-4 h-4" />
                          </button>
                          <a
                            href={`/webmail?email=${encodeURIComponent(account.email)}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-gray-600 hover:text-gray-800"
                            title="Webmail"
                          >
                            <EnvelopeIcon className="w-4 h-4" />
                          </a>
                          <button
                            onClick={() => handleDeleteAccount(account)}
                            className="text-red-600 hover:text-red-800 disabled:opacity-50"
                            disabled={actionLoading === account.id}
                            title="Delete"
                          >
                            <TrashIcon className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      {/* Forwarders Table */}
      {activeTab === 'forwarders' && (
        <Card>
          <CardBody className="p-0">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {forwarders.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                      No forwarders found. Create a forwarder to redirect emails.
                    </td>
                  </tr>
                ) : (
                  forwarders.map((forwarder) => (
                    <tr key={forwarder.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <EnvelopeIcon className="w-5 h-5 text-gray-400 mr-3" />
                          <span className="font-medium text-gray-900">{forwarder.source}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <ArrowRightIcon className="w-5 h-5 text-gray-400" />
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="text-gray-900">{forwarder.destination}</span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {new Date(forwarder.created_at).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        <button
                          onClick={() => handleDeleteForwarder(forwarder)}
                          className="text-red-600 hover:text-red-800 disabled:opacity-50"
                          disabled={actionLoading === forwarder.id + 10000}
                          title="Delete"
                        >
                          <TrashIcon className="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      {/* Create Email Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Create Email Account</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Email Address *</label>
                <div className="flex">
                  <input
                    type="text"
                    className="input rounded-r-none"
                    placeholder="user"
                    value={newAccount.email}
                    onChange={(e) => setNewAccount({ ...newAccount, email: e.target.value.toLowerCase() })}
                  />
                  <span className="px-3 py-2 bg-gray-100 border-y border-gray-300 text-gray-600">@</span>
                  <select
                    className="input rounded-l-none"
                    value={newAccount.domain}
                    onChange={(e) => setNewAccount({ ...newAccount, domain: e.target.value })}
                  >
                    {domains.map((domain) => (
                      <option key={domain.id} value={domain.name}>{domain.name}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label className="label">Password *</label>
                <input
                  type="password"
                  className="input"
                  placeholder="Min 8 characters"
                  value={newAccount.password}
                  onChange={(e) => setNewAccount({ ...newAccount, password: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Confirm Password *</label>
                <input
                  type="password"
                  className="input"
                  placeholder="Confirm password"
                  value={newAccount.confirmPassword}
                  onChange={(e) => setNewAccount({ ...newAccount, confirmPassword: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Mailbox Quota (MB)</label>
                <input
                  type="number"
                  className="input"
                  placeholder="1024"
                  value={newAccount.quota}
                  onChange={(e) => setNewAccount({ ...newAccount, quota: parseInt(e.target.value) || 1024 })}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowCreateModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleCreateAccount}
                disabled={actionLoading === -1}
              >
                {actionLoading === -1 ? 'Creating...' : 'Create Account'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Change Password Modal */}
      {showPasswordModal && selectedAccount && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                Change Password for {selectedAccount.email}
              </h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">New Password *</label>
                <input
                  type="password"
                  className="input"
                  placeholder="Min 8 characters"
                  value={newPassword.password}
                  onChange={(e) => setNewPassword({ ...newPassword, password: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Confirm Password *</label>
                <input
                  type="password"
                  className="input"
                  placeholder="Confirm password"
                  value={newPassword.confirmPassword}
                  onChange={(e) => setNewPassword({ ...newPassword, confirmPassword: e.target.value })}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => {
                setShowPasswordModal(false)
                setSelectedAccount(null)
                setNewPassword({ password: '', confirmPassword: '' })
              }}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleChangePassword}
                disabled={actionLoading === selectedAccount.id}
              >
                {actionLoading === selectedAccount.id ? 'Changing...' : 'Change Password'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Create Forwarder Modal */}
      {showForwarderModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Create Email Forwarder</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Forward From *</label>
                <div className="flex">
                  <input
                    type="text"
                    className="input rounded-r-none"
                    placeholder="user"
                    value={newForwarder.source}
                    onChange={(e) => setNewForwarder({ ...newForwarder, source: e.target.value.toLowerCase() })}
                  />
                  <span className="px-3 py-2 bg-gray-100 border-y border-gray-300 text-gray-600">@</span>
                  <select
                    className="input rounded-l-none"
                    value={newForwarder.sourceDomain}
                    onChange={(e) => setNewForwarder({ ...newForwarder, sourceDomain: e.target.value })}
                  >
                    {domains.map((domain) => (
                      <option key={domain.id} value={domain.name}>{domain.name}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label className="label">Forward To *</label>
                <input
                  type="email"
                  className="input"
                  placeholder="destination@example.com"
                  value={newForwarder.destination}
                  onChange={(e) => setNewForwarder({ ...newForwarder, destination: e.target.value })}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowForwarderModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleCreateForwarder}
                disabled={actionLoading === -2}
              >
                {actionLoading === -2 ? 'Creating...' : 'Create Forwarder'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
