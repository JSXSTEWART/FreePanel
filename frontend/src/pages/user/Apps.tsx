import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import Modal from '../../components/common/Modal'
import {
  CubeIcon,
  ArrowPathIcon,
  TrashIcon,
  ExclamationTriangleIcon,
  Cog6ToothIcon,
} from '@heroicons/react/24/outline'
import { appsApi, AvailableApp, InstalledApp, domainsApi } from '../../api'

export default function Apps() {
  const [availableApps, setAvailableApps] = useState<AvailableApp[]>([])
  const [installedApps, setInstalledApps] = useState<InstalledApp[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Modals
  const [showInstallModal, setShowInstallModal] = useState(false)
  const [showUninstallModal, setShowUninstallModal] = useState(false)
  const [selectedApp, setSelectedApp] = useState<AvailableApp | null>(null)
  const [selectedInstalled, setSelectedInstalled] = useState<InstalledApp | null>(null)

  // Form states
  const [domains, setDomains] = useState<string[]>([])
  const [installForm, setInstallForm] = useState({
    domain: '',
    path: '',
    admin_user: '',
    admin_password: '',
    admin_email: '',
    site_name: '',
  })
  const [submitting, setSubmitting] = useState(false)

  const fetchData = async () => {
    try {
      setLoading(true)
      setError(null)
      const [available, installed] = await Promise.all([
        appsApi.getAvailable(),
        appsApi.getInstalled(),
      ])
      setAvailableApps(available)
      setInstalledApps(installed)
    } catch (err) {
      setError('Failed to load applications')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }

  const fetchDomains = async () => {
    try {
      const data = await domainsApi.list()
      setDomains(data.map((d) => d.domain))
    } catch (err) {
      console.error('Failed to load domains:', err)
    }
  }

  useEffect(() => {
    fetchData()
    fetchDomains()
  }, [])

  const handleInstall = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedApp) return

    setSubmitting(true)
    try {
      await appsApi.install({
        app_id: selectedApp.id,
        domain: installForm.domain,
        path: installForm.path || '/',
        admin_user: installForm.admin_user || undefined,
        admin_password: installForm.admin_password || undefined,
        admin_email: installForm.admin_email || undefined,
        site_name: installForm.site_name || undefined,
      })
      setShowInstallModal(false)
      setSelectedApp(null)
      setInstallForm({
        domain: '',
        path: '',
        admin_user: '',
        admin_password: '',
        admin_email: '',
        site_name: '',
      })
      fetchData()
    } catch (err) {
      console.error('Failed to install application:', err)
      alert('Failed to install application')
    } finally {
      setSubmitting(false)
    }
  }

  const handleUninstall = async () => {
    if (!selectedInstalled) return

    setSubmitting(true)
    try {
      await appsApi.uninstall(selectedInstalled.id)
      setShowUninstallModal(false)
      setSelectedInstalled(null)
      fetchData()
    } catch (err) {
      console.error('Failed to uninstall application:', err)
      alert('Failed to uninstall application')
    } finally {
      setSubmitting(false)
    }
  }

  const handleUpdate = async (app: InstalledApp) => {
    try {
      await appsApi.update(app.id)
      fetchData()
    } catch (err) {
      console.error('Failed to update application:', err)
      alert('Failed to update application')
    }
  }

  const openInstallModal = (app: AvailableApp) => {
    setSelectedApp(app)
    setShowInstallModal(true)
  }

  const getCategoryColor = (category: string) => {
    const colors: Record<string, string> = {
      cms: 'bg-blue-100 text-blue-800',
      ecommerce: 'bg-green-100 text-green-800',
      forum: 'bg-purple-100 text-purple-800',
      blog: 'bg-amber-100 text-amber-800',
      storage: 'bg-cyan-100 text-cyan-800',
    }
    return colors[category] || 'bg-gray-100 text-gray-800'
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <ExclamationTriangleIcon className="w-12 h-12 text-red-500 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-gray-900 mb-2">Error loading applications</h3>
        <p className="text-gray-500 mb-4">{error}</p>
        <Button variant="primary" onClick={fetchData}>
          Try Again
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Application Installer</h1>
        <p className="text-gray-500">One-click install popular web applications</p>
      </div>

      {/* Available Apps */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {availableApps.length === 0 ? (
          <div className="col-span-full text-center py-12">
            <CubeIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <h3 className="text-lg font-medium text-gray-900 mb-2">No apps available</h3>
            <p className="text-gray-500">Applications will appear here when available</p>
          </div>
        ) : (
          availableApps.map((app) => (
            <Card key={app.id} className="hover:shadow-md transition-shadow">
              <CardBody>
                <div className="flex items-start space-x-4">
                  <div className="flex-shrink-0 w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                    {app.icon ? (
                      <img src={app.icon} alt={app.name} className="w-8 h-8" />
                    ) : (
                      <CubeIcon className="w-8 h-8 text-gray-400" />
                    )}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <h3 className="font-semibold text-gray-900">{app.name}</h3>
                      <span
                        className={`text-xs px-2 py-0.5 rounded-full ${getCategoryColor(
                          app.category
                        )}`}
                      >
                        {app.category}
                      </span>
                    </div>
                    <p className="text-sm text-gray-500 line-clamp-2">{app.description}</p>
                    <div className="mt-3 flex items-center justify-between">
                      <span className="text-xs text-gray-400">v{app.version}</span>
                      <Button
                        variant="primary"
                        size="sm"
                        onClick={() => openInstallModal(app)}
                      >
                        Install
                      </Button>
                    </div>
                    {app.requirements && (
                      <div className="mt-2 text-xs text-gray-400">
                        {app.requirements.php_version && (
                          <span>PHP {app.requirements.php_version}+</span>
                        )}
                        {app.requirements.database && <span> | MySQL required</span>}
                      </div>
                    )}
                  </div>
                </div>
              </CardBody>
            </Card>
          ))
        )}
      </div>

      {/* Installed Apps */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Installed Applications</h2>
        <Card>
          <CardBody className="p-0">
            {installedApps.length === 0 ? (
              <div className="text-center py-12">
                <CubeIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <h3 className="text-lg font-medium text-gray-900 mb-2">No apps installed</h3>
                <p className="text-gray-500">Install an application to get started</p>
              </div>
            ) : (
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Application
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Domain
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Version
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Installed
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {installedApps.map((app) => (
                    <tr key={app.id}>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <CubeIcon className="w-5 h-5 text-primary-500 mr-3" />
                          <span className="font-medium text-gray-900">{app.name}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {app.domain}
                        {app.path !== '/' && app.path}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {app.version}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {new Date(app.installed_at).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        {app.admin_url && (
                          <a
                            href={app.admin_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary-600 hover:text-primary-800 mr-3"
                          >
                            <Cog6ToothIcon className="w-4 h-4 inline mr-1" />
                            Manage
                          </a>
                        )}
                        <button
                          onClick={() => handleUpdate(app)}
                          className="text-gray-600 hover:text-gray-800 mr-3"
                        >
                          <ArrowPathIcon className="w-4 h-4 inline mr-1" />
                          Update
                        </button>
                        <button
                          onClick={() => {
                            setSelectedInstalled(app)
                            setShowUninstallModal(true)
                          }}
                          className="text-red-600 hover:text-red-800"
                        >
                          <TrashIcon className="w-4 h-4 inline mr-1" />
                          Uninstall
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Install Modal */}
      <Modal
        isOpen={showInstallModal}
        onClose={() => setShowInstallModal(false)}
        title={`Install ${selectedApp?.name}`}
      >
        <form onSubmit={handleInstall} className="space-y-4">
          <div>
            <label className="label">Domain</label>
            <select
              className="input"
              value={installForm.domain}
              onChange={(e) => setInstallForm({ ...installForm, domain: e.target.value })}
              required
            >
              <option value="">Select domain</option>
              {domains.map((domain) => (
                <option key={domain} value={domain}>
                  {domain}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="label">Installation Path</label>
            <input
              type="text"
              className="input"
              placeholder="/ (root) or /blog"
              value={installForm.path}
              onChange={(e) => setInstallForm({ ...installForm, path: e.target.value })}
            />
            <p className="text-xs text-gray-500 mt-1">Leave empty for root directory</p>
          </div>
          <div>
            <label className="label">Site Name</label>
            <input
              type="text"
              className="input"
              placeholder="My Website"
              value={installForm.site_name}
              onChange={(e) => setInstallForm({ ...installForm, site_name: e.target.value })}
            />
          </div>
          <div>
            <label className="label">Admin Username</label>
            <input
              type="text"
              className="input"
              placeholder="admin"
              value={installForm.admin_user}
              onChange={(e) => setInstallForm({ ...installForm, admin_user: e.target.value })}
            />
          </div>
          <div>
            <label className="label">Admin Password</label>
            <input
              type="password"
              className="input"
              placeholder="Strong password"
              value={installForm.admin_password}
              onChange={(e) => setInstallForm({ ...installForm, admin_password: e.target.value })}
            />
          </div>
          <div>
            <label className="label">Admin Email</label>
            <input
              type="email"
              className="input"
              placeholder="admin@example.com"
              value={installForm.admin_email}
              onChange={(e) => setInstallForm({ ...installForm, admin_email: e.target.value })}
            />
          </div>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowInstallModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" disabled={submitting}>
              {submitting ? 'Installing...' : 'Install'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Uninstall Confirmation Modal */}
      <Modal
        isOpen={showUninstallModal}
        onClose={() => setShowUninstallModal(false)}
        title="Uninstall Application"
      >
        <div className="space-y-4">
          <div className="bg-red-50 border border-red-200 rounded-lg p-4">
            <div className="flex items-start">
              <ExclamationTriangleIcon className="w-5 h-5 text-red-600 mt-0.5 mr-3" />
              <div>
                <h4 className="font-medium text-red-800">Warning</h4>
                <p className="text-sm text-red-700 mt-1">
                  This will permanently delete the application and all its data including the
                  database. This action cannot be undone.
                </p>
              </div>
            </div>
          </div>
          <p className="text-gray-600">
            Are you sure you want to uninstall <strong>{selectedInstalled?.name}</strong> from{' '}
            <strong>
              {selectedInstalled?.domain}
              {selectedInstalled?.path !== '/' && selectedInstalled?.path}
            </strong>
            ?
          </p>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowUninstallModal(false)}>
              Cancel
            </Button>
            <Button type="button" variant="danger" onClick={handleUninstall} disabled={submitting}>
              {submitting ? 'Uninstalling...' : 'Uninstall'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
