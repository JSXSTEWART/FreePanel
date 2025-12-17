import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { databasesApi, Database, DatabaseUser } from '../../api'
import toast from 'react-hot-toast'
import {
  PlusIcon,
  CircleStackIcon,
  TrashIcon,
  UserPlusIcon,
  KeyIcon,
} from '@heroicons/react/24/outline'

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

export default function Databases() {
  const [databases, setDatabases] = useState<Database[]>([])
  const [users, setUsers] = useState<DatabaseUser[]>([])
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState<'databases' | 'users'>('databases')
  const [showCreateDbModal, setShowCreateDbModal] = useState(false)
  const [showCreateUserModal, setShowCreateUserModal] = useState(false)
  const [showPasswordModal, setShowPasswordModal] = useState(false)
  const [showGrantModal, setShowGrantModal] = useState(false)
  const [selectedUser, setSelectedUser] = useState<DatabaseUser | null>(null)
  const [selectedDatabase, setSelectedDatabase] = useState<Database | null>(null)
  const [actionLoading, setActionLoading] = useState<number | null>(null)

  // Form state
  const [newDbName, setNewDbName] = useState('')
  const [newUser, setNewUser] = useState({ username: '', password: '', confirmPassword: '' })
  const [newPassword, setNewPassword] = useState({ password: '', confirmPassword: '' })
  const [grantUserId, setGrantUserId] = useState<number | null>(null)

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      setLoading(true)
      const [dbsData, usersData] = await Promise.all([
        databasesApi.list(),
        databasesApi.listUsers(),
      ])
      setDatabases(dbsData)
      setUsers(usersData)
    } catch (error) {
      toast.error('Failed to load databases')
    } finally {
      setLoading(false)
    }
  }

  const handleCreateDatabase = async () => {
    if (!newDbName) {
      toast.error('Please enter a database name')
      return
    }

    try {
      setActionLoading(-1)
      await databasesApi.create({ name: newDbName })
      toast.success('Database created successfully')
      setShowCreateDbModal(false)
      setNewDbName('')
      loadData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create database')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDeleteDatabase = async (db: Database) => {
    if (!confirm(`Are you sure you want to delete "${db.name}"? All data will be lost.`)) {
      return
    }

    try {
      setActionLoading(db.id)
      await databasesApi.delete(db.id)
      toast.success('Database deleted')
      loadData()
    } catch (error) {
      toast.error('Failed to delete database')
    } finally {
      setActionLoading(null)
    }
  }

  const handleCreateUser = async () => {
    if (!newUser.username || !newUser.password) {
      toast.error('Please fill in all required fields')
      return
    }
    if (newUser.password !== newUser.confirmPassword) {
      toast.error('Passwords do not match')
      return
    }
    if (newUser.password.length < 8) {
      toast.error('Password must be at least 8 characters')
      return
    }

    try {
      setActionLoading(-2)
      await databasesApi.createUser({
        username: newUser.username,
        password: newUser.password,
      })
      toast.success('Database user created successfully')
      setShowCreateUserModal(false)
      setNewUser({ username: '', password: '', confirmPassword: '' })
      loadData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create user')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDeleteUser = async (user: DatabaseUser) => {
    if (!confirm(`Are you sure you want to delete user "${user.username}"?`)) {
      return
    }

    try {
      setActionLoading(user.id + 10000)
      await databasesApi.deleteUser(user.id)
      toast.success('User deleted')
      loadData()
    } catch (error) {
      toast.error('Failed to delete user')
    } finally {
      setActionLoading(null)
    }
  }

  const handleChangePassword = async () => {
    if (!selectedUser) return
    if (newPassword.password !== newPassword.confirmPassword) {
      toast.error('Passwords do not match')
      return
    }
    if (newPassword.password.length < 8) {
      toast.error('Password must be at least 8 characters')
      return
    }

    try {
      setActionLoading(selectedUser.id + 10000)
      await databasesApi.changeUserPassword(selectedUser.id, newPassword.password)
      toast.success('Password changed successfully')
      setShowPasswordModal(false)
      setSelectedUser(null)
      setNewPassword({ password: '', confirmPassword: '' })
    } catch (error) {
      toast.error('Failed to change password')
    } finally {
      setActionLoading(null)
    }
  }

  const handleGrantAccess = async () => {
    if (!selectedDatabase || !grantUserId) {
      toast.error('Please select a user')
      return
    }

    try {
      setActionLoading(selectedDatabase.id)
      await databasesApi.grant(selectedDatabase.id, grantUserId, ['ALL PRIVILEGES'])
      toast.success('Access granted successfully')
      setShowGrantModal(false)
      setSelectedDatabase(null)
      setGrantUserId(null)
      loadData()
    } catch (error) {
      toast.error('Failed to grant access')
    } finally {
      setActionLoading(null)
    }
  }

  const openPasswordModal = (user: DatabaseUser) => {
    setSelectedUser(user)
    setShowPasswordModal(true)
  }

  const openGrantModal = (db: Database) => {
    setSelectedDatabase(db)
    setShowGrantModal(true)
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
          <h1 className="text-2xl font-bold text-gray-900">Databases</h1>
          <p className="text-gray-500">Manage MySQL databases and users</p>
        </div>
        <div className="flex space-x-3">
          {activeTab === 'databases' ? (
            <Button variant="primary" onClick={() => setShowCreateDbModal(true)}>
              <PlusIcon className="w-5 h-5 mr-2" />
              Create Database
            </Button>
          ) : (
            <Button variant="primary" onClick={() => setShowCreateUserModal(true)}>
              <UserPlusIcon className="w-5 h-5 mr-2" />
              Add User
            </Button>
          )}
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-primary-600">{databases.length}</div>
            <div className="text-sm text-gray-500">Databases</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-green-600">{users.length}</div>
            <div className="text-sm text-gray-500">Database Users</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-blue-600">
              {formatBytes(databases.reduce((sum, db) => sum + db.size, 0))}
            </div>
            <div className="text-sm text-gray-500">Total Size</div>
          </CardBody>
        </Card>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('databases')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'databases'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Databases ({databases.length})
          </button>
          <button
            onClick={() => setActiveTab('users')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'users'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Users ({users.length})
          </button>
        </nav>
      </div>

      {/* Databases Table */}
      {activeTab === 'databases' && (
        <Card>
          <CardBody className="p-0">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Database</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tables</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {databases.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-6 py-8 text-center text-gray-500">
                      No databases found. Create your first database to get started.
                    </td>
                  </tr>
                ) : (
                  databases.map((db) => (
                    <tr key={db.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <CircleStackIcon className="w-5 h-5 text-gray-400 mr-3" />
                          <span className="font-medium text-gray-900">{db.name}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {formatBytes(db.size)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {db.tables_count || 0} tables
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        <div className="flex space-x-2">
                          <a
                            href={`/phpmyadmin?db=${encodeURIComponent(db.name)}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary-600 hover:text-primary-800"
                            title="phpMyAdmin"
                          >
                            phpMyAdmin
                          </a>
                          <button
                            onClick={() => openGrantModal(db)}
                            className="text-gray-600 hover:text-gray-800"
                            title="Grant User Access"
                          >
                            <UserPlusIcon className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDeleteDatabase(db)}
                            className="text-red-600 hover:text-red-800 disabled:opacity-50"
                            disabled={actionLoading === db.id}
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

      {/* Users Table */}
      {activeTab === 'users' && (
        <Card>
          <CardBody className="p-0">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Host</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Databases</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {users.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-6 py-8 text-center text-gray-500">
                      No database users found. Create a user to manage database access.
                    </td>
                  </tr>
                ) : (
                  users.map((user) => (
                    <tr key={user.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="font-medium text-gray-900">{user.username}</span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {user.host}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {user.databases.length > 0 ? user.databases.join(', ') : 'None'}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        <div className="flex space-x-2">
                          <button
                            onClick={() => openPasswordModal(user)}
                            className="text-primary-600 hover:text-primary-800"
                            title="Change Password"
                          >
                            <KeyIcon className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDeleteUser(user)}
                            className="text-red-600 hover:text-red-800 disabled:opacity-50"
                            disabled={actionLoading === user.id + 10000}
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

      {/* Create Database Modal */}
      {showCreateDbModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Create Database</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Database Name *</label>
                <input
                  type="text"
                  className="input"
                  placeholder="my_database"
                  value={newDbName}
                  onChange={(e) => setNewDbName(e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))}
                  maxLength={64}
                />
                <p className="text-xs text-gray-500 mt-1">Lowercase letters, numbers, and underscores only</p>
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowCreateDbModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleCreateDatabase}
                disabled={actionLoading === -1}
              >
                {actionLoading === -1 ? 'Creating...' : 'Create Database'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Create User Modal */}
      {showCreateUserModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Create Database User</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Username *</label>
                <input
                  type="text"
                  className="input"
                  placeholder="db_user"
                  value={newUser.username}
                  onChange={(e) => setNewUser({ ...newUser, username: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '') })}
                  maxLength={32}
                />
              </div>
              <div>
                <label className="label">Password *</label>
                <input
                  type="password"
                  className="input"
                  placeholder="Min 8 characters"
                  value={newUser.password}
                  onChange={(e) => setNewUser({ ...newUser, password: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Confirm Password *</label>
                <input
                  type="password"
                  className="input"
                  placeholder="Confirm password"
                  value={newUser.confirmPassword}
                  onChange={(e) => setNewUser({ ...newUser, confirmPassword: e.target.value })}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowCreateUserModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleCreateUser}
                disabled={actionLoading === -2}
              >
                {actionLoading === -2 ? 'Creating...' : 'Create User'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Change Password Modal */}
      {showPasswordModal && selectedUser && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                Change Password for {selectedUser.username}
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
                setSelectedUser(null)
                setNewPassword({ password: '', confirmPassword: '' })
              }}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleChangePassword}
                disabled={actionLoading === (selectedUser.id + 10000)}
              >
                {actionLoading === (selectedUser.id + 10000) ? 'Changing...' : 'Change Password'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Grant Access Modal */}
      {showGrantModal && selectedDatabase && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                Grant Access to {selectedDatabase.name}
              </h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Select User *</label>
                <select
                  className="input"
                  value={grantUserId || ''}
                  onChange={(e) => setGrantUserId(parseInt(e.target.value) || null)}
                >
                  <option value="">Select a user...</option>
                  {users.map((user) => (
                    <option key={user.id} value={user.id}>{user.username}</option>
                  ))}
                </select>
              </div>
              <p className="text-sm text-gray-500">
                The user will be granted ALL PRIVILEGES on this database.
              </p>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => {
                setShowGrantModal(false)
                setSelectedDatabase(null)
                setGrantUserId(null)
              }}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleGrantAccess}
                disabled={actionLoading === selectedDatabase.id || !grantUserId}
              >
                {actionLoading === selectedDatabase.id ? 'Granting...' : 'Grant Access'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
