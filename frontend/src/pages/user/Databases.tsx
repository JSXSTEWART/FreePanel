import { Card, CardBody } from "../../components/common/Card";
import Button from "../../components/common/Button";
import { PlusIcon, CircleStackIcon } from "@heroicons/react/24/outline";

export default function Databases() {
  const [activeTab, setActiveTab] = useState<TabType>('databases')
  const [databases, setDatabases] = useState<Database[]>([])
  const [users, setUsers] = useState<DatabaseUser[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showPasswordModal, setShowPasswordModal] = useState<DatabaseUser | null>(null)
  const [deleteConfirm, setDeleteConfirm] = useState<{ type: TabType; item: Database | DatabaseUser } | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const [formData, setFormData] = useState({
    name: '',
    username: '',
    password: '',
    confirmPassword: '',
    host: 'localhost',
  })
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})

  const fetchData = async () => {
    try {
      setLoading(true)
      const [databasesData, usersData] = await Promise.all([
        databasesApi.list(),
        databasesApi.listUsers(),
      ])
      setDatabases(databasesData)
      setUsers(usersData)
    } catch (error) {
      toast.error('Failed to load database data')
      console.error('Error fetching database data:', error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchData()
  }, [])

  const formatSize = (bytes: number): string => {
    if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(2)} GB`
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(2)} MB`
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(2)} KB`
    return `${bytes} B`
  }

  const handleCreateDatabase = async () => {
    const errors: Record<string, string> = {}
    if (!formData.name || !/^[a-zA-Z0-9_]+$/.test(formData.name)) {
      errors.name = 'Database name can only contain letters, numbers, and underscores'
    }
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    try {
      setSubmitting(true)
      await databasesApi.create({ name: formData.name })
      toast.success('Database created successfully')
      setShowCreateModal(false)
      setFormData({ ...formData, name: '' })
      fetchData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create database')
    } finally {
      setSubmitting(false)
    }
  }

  const handleCreateUser = async () => {
    const errors: Record<string, string> = {}
    if (!formData.username || !/^[a-zA-Z0-9_]+$/.test(formData.username)) {
      errors.username = 'Username can only contain letters, numbers, and underscores'
    }
    if (!formData.password || formData.password.length < 8) {
      errors.password = 'Password must be at least 8 characters'
    }
    if (formData.password !== formData.confirmPassword) {
      errors.confirmPassword = 'Passwords do not match'
    }
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    try {
      setSubmitting(true)
      await databasesApi.createUser({
        username: formData.username,
        password: formData.password,
        host: formData.host,
      })
      toast.success('Database user created successfully')
      setShowCreateModal(false)
      setFormData({ ...formData, username: '', password: '', confirmPassword: '' })
      fetchData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create user')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteConfirm) return
    try {
      setSubmitting(true)
      if (deleteConfirm.type === 'databases') {
        await databasesApi.delete((deleteConfirm.item as Database).id)
      } else {
        await databasesApi.deleteUser((deleteConfirm.item as DatabaseUser).id)
      }
      toast.success('Deleted successfully')
      setDeleteConfirm(null)
      fetchData()
    } catch {
      toast.error('Failed to delete')
    } finally {
      setSubmitting(false)
    }
  }

  const handleChangePassword = async () => {
    if (!showPasswordModal) return
    const errors: Record<string, string> = {}
    if (!formData.password || formData.password.length < 8) {
      errors.password = 'Password must be at least 8 characters'
    }
    if (formData.password !== formData.confirmPassword) {
      errors.confirmPassword = 'Passwords do not match'
    }
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    try {
      setSubmitting(true)
      await databasesApi.changeUserPassword(showPasswordModal.id, formData.password)
      toast.success('Password changed successfully')
      setShowPasswordModal(null)
      setFormData({ ...formData, password: '', confirmPassword: '' })
    } catch {
      toast.error('Failed to change password')
    } finally {
      setSubmitting(false)
    }
  }

  const tabs = [
    { key: 'databases' as TabType, label: 'Databases', icon: CircleStackIcon, count: databases.length },
    { key: 'users' as TabType, label: 'Database Users', icon: UserIcon, count: users.length },
  ]

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Databases</h1>
            <p className="text-gray-500">Manage MySQL databases and users</p>
          </div>
        </div>
        <Card>
          <CardBody className="flex items-center justify-center py-12">
            <ArrowPathIcon className="w-8 h-8 text-gray-400 animate-spin" />
            <span className="ml-3 text-gray-500">Loading databases...</span>
          </CardBody>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Databases</h1>
          <p className="text-gray-500">Manage MySQL databases and users</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={fetchData}>
            <ArrowPathIcon className="w-5 h-5 mr-2" />
            Refresh
          </Button>
          <Button variant="primary" onClick={() => setShowCreateModal(true)}>
            <PlusIcon className="w-5 h-5 mr-2" />
            {activeTab === 'databases' ? 'Create Database' : 'Add User'}
          </Button>
        </div>
      </div>

      <div className="border-b border-gray-200">
        <nav className="flex space-x-8">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                activeTab === tab.key
                  ? 'border-primary-500 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <tab.icon className="w-5 h-5 mr-2" />
              {tab.label}
              <span className="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">{tab.count}</span>
            </button>
          ))}
        </nav>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Database
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Size
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Users
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              <tr>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center">
                    <CircleStackIcon className="w-5 h-5 text-gray-400 mr-3" />
                    <span className="font-medium text-gray-900">
                      user_wordpress
                    </span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  24.5 MB
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  1 user
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-primary-600 hover:text-primary-800 mr-3">
                    phpMyAdmin
                  </button>
                  <button className="text-gray-600 hover:text-gray-800 mr-3">
                    Users
                  </button>
                  <button className="text-red-600 hover:text-red-800">
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </CardBody>
      </Card>

      <Modal isOpen={showCreateModal} onClose={() => { setShowCreateModal(false); setFormErrors({}) }} title={activeTab === 'databases' ? 'Create Database' : 'Add Database User'}>
        <ModalBody className="space-y-4">
          {activeTab === 'databases' ? (
            <Input label="Database Name" placeholder="my_database" value={formData.name} onChange={(e) => setFormData({ ...formData, name: e.target.value })} error={formErrors.name} hint="Only letters, numbers, and underscores allowed" />
          ) : (
            <>
              <Input label="Username" placeholder="db_user" value={formData.username} onChange={(e) => setFormData({ ...formData, username: e.target.value })} error={formErrors.username} hint="Only letters, numbers, and underscores allowed" />
              <Input label="Password" type="password" value={formData.password} onChange={(e) => setFormData({ ...formData, password: e.target.value })} error={formErrors.password} />
              <Input label="Confirm Password" type="password" value={formData.confirmPassword} onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })} error={formErrors.confirmPassword} />
              <Input label="Host" value={formData.host} onChange={(e) => setFormData({ ...formData, host: e.target.value })} hint="Use localhost for local connections or % for any host" />
            </>
          )}
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowCreateModal(false)} disabled={submitting}>Cancel</Button>
          <Button variant="primary" onClick={activeTab === 'databases' ? handleCreateDatabase : handleCreateUser} disabled={submitting}>
            {submitting ? 'Creating...' : 'Create'}
          </Button>
        </ModalFooter>
      </Modal>

      <Modal isOpen={!!showPasswordModal} onClose={() => { setShowPasswordModal(null); setFormErrors({}); setFormData({ ...formData, password: '', confirmPassword: '' }) }} title={`Change Password - ${showPasswordModal?.username}`}>
        <ModalBody className="space-y-4">
          <Input label="New Password" type="password" value={formData.password} onChange={(e) => setFormData({ ...formData, password: e.target.value })} error={formErrors.password} />
          <Input label="Confirm New Password" type="password" value={formData.confirmPassword} onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })} error={formErrors.confirmPassword} />
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowPasswordModal(null)} disabled={submitting}>Cancel</Button>
          <Button variant="primary" onClick={handleChangePassword} disabled={submitting}>{submitting ? 'Changing...' : 'Change Password'}</Button>
        </ModalFooter>
      </Modal>

      <ConfirmDialog isOpen={!!deleteConfirm} onClose={() => setDeleteConfirm(null)} onConfirm={handleDelete} title="Delete Confirmation" message={`Are you sure you want to delete this ${deleteConfirm?.type === 'databases' ? 'database' : 'user'}? This action cannot be undone.`} confirmLabel={submitting ? 'Deleting...' : 'Delete'} variant="danger" />
    </div>
  );
}
