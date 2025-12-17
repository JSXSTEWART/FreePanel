import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import toast from 'react-hot-toast'
import {
  UserPlusIcon,
  MagnifyingGlassIcon,
  PauseCircleIcon,
  PlayCircleIcon,
  TrashIcon,
  PencilIcon
} from '@heroicons/react/24/outline'
import { accountsApi, packagesApi, Account, Package } from '../../api'

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  if (bytes === -1) return 'Unlimited'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

export default function Accounts() {
  const [accounts, setAccounts] = useState<Account[]>([])
  const [packages, setPackages] = useState<Package[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [actionLoading, setActionLoading] = useState<number | null>(null)

  // Form state
  const [formData, setFormData] = useState({
    username: '',
    password: '',
    email: '',
    domain: '',
    package_id: 0,
  })

  // Stats
  const totalAccounts = accounts.length
  const activeAccounts = accounts.filter(a => a.status === 'active').length
  const suspendedAccounts = accounts.filter(a => a.status === 'suspended').length
  const totalDiskUsed = accounts.reduce((sum, a) => sum + a.disk_used, 0)

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      setLoading(true)
      const [accountsData, packagesData] = await Promise.all([
        accountsApi.list({ per_page: 100 }),
        packagesApi.list(),
      ])
      setAccounts(accountsData.data)
      setPackages(packagesData)
      if (packagesData.length > 0) {
        const defaultPkg = packagesData.find(p => p.is_default) || packagesData[0]
        setFormData(prev => ({ ...prev, package_id: defaultPkg.id }))
      }
    } catch (error) {
      toast.error('Failed to load accounts')
    } finally {
      setLoading(false)
    }
  }

  const filteredAccounts = accounts.filter(
    acc => acc.username.toLowerCase().includes(search.toLowerCase()) ||
           acc.domain.toLowerCase().includes(search.toLowerCase()) ||
           acc.user.email.toLowerCase().includes(search.toLowerCase())
  )

  const handleCreate = async () => {
    try {
      if (!formData.username || !formData.password || !formData.email || !formData.domain) {
        toast.error('Please fill in all required fields')
        return
      }

      setActionLoading(-1)
      await accountsApi.create(formData)
      toast.success('Account created successfully')
      setShowCreateModal(false)
      setFormData({ username: '', password: '', email: '', domain: '', package_id: formData.package_id })
      loadData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create account')
    } finally {
      setActionLoading(null)
    }
  }

  const handleSuspend = async (account: Account) => {
    try {
      setActionLoading(account.id)
      await accountsApi.suspend(account.id, 'Suspended by administrator')
      toast.success('Account suspended')
      loadData()
    } catch (error) {
      toast.error('Failed to suspend account')
    } finally {
      setActionLoading(null)
    }
  }

  const handleUnsuspend = async (account: Account) => {
    try {
      setActionLoading(account.id)
      await accountsApi.unsuspend(account.id)
      toast.success('Account unsuspended')
      loadData()
    } catch (error) {
      toast.error('Failed to unsuspend account')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDelete = async (account: Account) => {
    if (!confirm(`Are you sure you want to terminate account "${account.username}"? This action cannot be undone.`)) {
      return
    }

    try {
      setActionLoading(account.id)
      await accountsApi.delete(account.id)
      toast.success('Account terminated')
      loadData()
    } catch (error) {
      toast.error('Failed to terminate account')
    } finally {
      setActionLoading(null)
    }
  }

  const getDiskUsagePercent = (account: Account): number => {
    if (!account.package?.disk_quota || account.package.disk_quota <= 0) return 0
    return Math.min(100, (account.disk_used / account.package.disk_quota) * 100)
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
          <h1 className="text-2xl font-bold text-gray-900">Hosting Accounts</h1>
          <p className="text-gray-500">Manage all hosting accounts on this server</p>
        </div>
        <Button variant="primary" onClick={() => setShowCreateModal(true)}>
          <UserPlusIcon className="w-5 h-5 mr-2" />
          Create Account
        </Button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-primary-600">{totalAccounts}</div>
            <div className="text-sm text-gray-500">Total Accounts</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-green-600">{activeAccounts}</div>
            <div className="text-sm text-gray-500">Active</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-red-600">{suspendedAccounts}</div>
            <div className="text-sm text-gray-500">Suspended</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-blue-600">{formatBytes(totalDiskUsed)}</div>
            <div className="text-sm text-gray-500">Total Disk Used</div>
          </CardBody>
        </Card>
      </div>

      {/* Search */}
      <div className="relative">
        <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          type="text"
          placeholder="Search accounts by username, domain, or email..."
          className="input pl-10 w-full"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      {/* Accounts Table */}
      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Disk Usage</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredAccounts.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-6 py-8 text-center text-gray-500">
                    {search ? 'No accounts found matching your search' : 'No accounts found'}
                  </td>
                </tr>
              ) : (
                filteredAccounts.map((account) => (
                  <tr key={account.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div>
                        <div className="font-medium text-gray-900">{account.username}</div>
                        <div className="text-sm text-gray-500">{account.user.email}</div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <a href={`https://${account.domain}`} target="_blank" rel="noopener noreferrer"
                         className="text-primary-600 hover:text-primary-800">
                        {account.domain}
                      </a>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                        {account.package?.name || 'Unknown'}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {formatBytes(account.disk_used)} / {formatBytes(account.package?.disk_quota || 0)}
                      </div>
                      <div className="w-24 bg-gray-200 rounded-full h-2 mt-1">
                        <div
                          className={`h-2 rounded-full ${getDiskUsagePercent(account) > 90 ? 'bg-red-500' : 'bg-primary-600'}`}
                          style={{ width: `${getDiskUsagePercent(account)}%` }}
                        ></div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                        account.status === 'active'
                          ? 'bg-green-100 text-green-800'
                          : 'bg-red-100 text-red-800'
                      }`}>
                        {account.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(account.created_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      <div className="flex space-x-2">
                        <button
                          className="p-1 text-gray-600 hover:text-gray-800"
                          title="Edit"
                          disabled={actionLoading === account.id}
                        >
                          <PencilIcon className="w-4 h-4" />
                        </button>
                        {account.status === 'active' ? (
                          <button
                            className="p-1 text-yellow-600 hover:text-yellow-800 disabled:opacity-50"
                            title="Suspend"
                            disabled={actionLoading === account.id}
                            onClick={() => handleSuspend(account)}
                          >
                            <PauseCircleIcon className="w-4 h-4" />
                          </button>
                        ) : (
                          <button
                            className="p-1 text-green-600 hover:text-green-800 disabled:opacity-50"
                            title="Unsuspend"
                            disabled={actionLoading === account.id}
                            onClick={() => handleUnsuspend(account)}
                          >
                            <PlayCircleIcon className="w-4 h-4" />
                          </button>
                        )}
                        <button
                          className="p-1 text-red-600 hover:text-red-800 disabled:opacity-50"
                          title="Delete"
                          disabled={actionLoading === account.id}
                          onClick={() => handleDelete(account)}
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

      {/* Create Account Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Create New Account</h3>
            </div>
            <div className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">Username *</label>
                  <input
                    type="text"
                    className="input"
                    placeholder="johndoe"
                    value={formData.username}
                    onChange={(e) => setFormData({ ...formData, username: e.target.value.toLowerCase().replace(/[^a-z0-9]/g, '') })}
                    maxLength={16}
                  />
                  <p className="text-xs text-gray-500 mt-1">3-16 lowercase letters and numbers</p>
                </div>
                <div>
                  <label className="label">Password *</label>
                  <input
                    type="password"
                    className="input"
                    placeholder="Min 8 characters"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  />
                </div>
              </div>
              <div>
                <label className="label">Domain *</label>
                <input
                  type="text"
                  className="input"
                  placeholder="example.com"
                  value={formData.domain}
                  onChange={(e) => setFormData({ ...formData, domain: e.target.value.toLowerCase() })}
                />
              </div>
              <div>
                <label className="label">Email *</label>
                <input
                  type="email"
                  className="input"
                  placeholder="user@example.com"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Package *</label>
                <select
                  className="input"
                  value={formData.package_id}
                  onChange={(e) => setFormData({ ...formData, package_id: parseInt(e.target.value) })}
                >
                  {packages.map((pkg) => (
                    <option key={pkg.id} value={pkg.id}>
                      {pkg.name} - {formatBytes(pkg.disk_quota)} Disk, {formatBytes(pkg.bandwidth)} Bandwidth
                      {pkg.is_default ? ' (Default)' : ''}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowCreateModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleCreate}
                disabled={actionLoading === -1}
              >
                {actionLoading === -1 ? 'Creating...' : 'Create Account'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
