import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { appsApi, domainsApi, AvailableApp, InstalledApp, Domain } from '../../api'
import toast from 'react-hot-toast'
import {
  TrashIcon,
  ArrowPathIcon,
  Cog6ToothIcon,
  GlobeAltIcon,
  MagnifyingGlassIcon,
} from '@heroicons/react/24/outline'

export default function Apps() {
  const [availableApps, setAvailableApps] = useState<AvailableApp[]>([])
  const [installedApps, setInstalledApps] = useState<InstalledApp[]>([])
  const [domains, setDomains] = useState<Domain[]>([])
  const [loading, setLoading] = useState(true)
  const [showInstallModal, setShowInstallModal] = useState(false)
  const [selectedApp, setSelectedApp] = useState<AvailableApp | null>(null)
  const [actionLoading, setActionLoading] = useState<number | string | null>(null)
  const [searchTerm, setSearchTerm] = useState('')

  // Form state
  const [installData, setInstallData] = useState({
    domain: '',
    path: '/',
    admin_user: 'admin',
    admin_email: '',
    admin_password: '',
    site_name: '',
  })

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      setLoading(true)
      const [available, installed, domainsData] = await Promise.all([
        appsApi.getAvailable(),
        appsApi.getInstalled(),
        domainsApi.list(),
      ])
      setAvailableApps(available)
      setInstalledApps(installed)
      setDomains(domainsData)
      if (domainsData.length > 0) {
        setInstallData(prev => ({ ...prev, domain: domainsData[0].name }))
      }
    } catch (error) {
      toast.error('Failed to load applications')
    } finally {
      setLoading(false)
    }
  }

  const openInstallModal = (app: AvailableApp) => {
    setSelectedApp(app)
    setInstallData({
      domain: domains[0]?.name || '',
      path: '/',
      admin_user: 'admin',
      admin_email: '',
      admin_password: '',
      site_name: app.name + ' Site',
    })
    setShowInstallModal(true)
  }

  const handleInstall = async () => {
    if (!selectedApp) return
    if (!installData.domain || !installData.admin_email || !installData.admin_password) {
      toast.error('Please fill in all required fields')
      return
    }

    try {
      setActionLoading(selectedApp.id)
      await appsApi.install({
        app_id: selectedApp.id,
        domain: installData.domain,
        path: installData.path,
        admin_user: installData.admin_user,
        admin_email: installData.admin_email,
        admin_password: installData.admin_password,
        site_name: installData.site_name,
      })
      toast.success(`${selectedApp.name} is being installed. This may take a few minutes.`)
      setShowInstallModal(false)
      loadData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to install application')
    } finally {
      setActionLoading(null)
    }
  }

  const handleUninstall = async (app: InstalledApp) => {
    if (!confirm(`Are you sure you want to uninstall ${app.name}? All data will be lost.`)) {
      return
    }

    try {
      setActionLoading(app.id)
      await appsApi.uninstall(app.id)
      toast.success('Application uninstalled successfully')
      loadData()
    } catch (error) {
      toast.error('Failed to uninstall application')
    } finally {
      setActionLoading(null)
    }
  }

  const handleUpdate = async (app: InstalledApp) => {
    try {
      setActionLoading(app.id)
      await appsApi.update(app.id)
      toast.success('Application is being updated')
      loadData()
    } catch (error) {
      toast.error('Failed to update application')
    } finally {
      setActionLoading(null)
    }
  }

  const filteredApps = availableApps.filter(app =>
    app.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    app.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
    app.category.toLowerCase().includes(searchTerm.toLowerCase())
  )

  const categories = [...new Set(availableApps.map(app => app.category))]

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Application Installer</h1>
        <p className="text-gray-500">One-click install popular web applications</p>
      </div>

      {/* Search */}
      <div className="relative">
        <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          type="text"
          className="input pl-10"
          placeholder="Search applications..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
        />
      </div>

      {/* Categories */}
      {categories.map((category) => {
        const categoryApps = filteredApps.filter(app => app.category === category)
        if (categoryApps.length === 0) return null

        return (
          <div key={category}>
            <h2 className="text-lg font-semibold text-gray-900 mb-4">{category}</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {categoryApps.map((app) => (
                <Card key={app.id} className="hover:shadow-md transition-shadow">
                  <CardBody>
                    <div className="flex items-start space-x-4">
                      {app.icon ? (
                        <img src={app.icon} alt={app.name} className="w-12 h-12 rounded" />
                      ) : (
                        <div className="w-12 h-12 bg-primary-100 rounded flex items-center justify-center text-2xl">
                          {app.name.charAt(0)}
                        </div>
                      )}
                      <div className="flex-1 min-w-0">
                        <h3 className="font-semibold text-gray-900 truncate">{app.name}</h3>
                        <p className="text-sm text-gray-500 line-clamp-2">{app.description}</p>
                        <div className="mt-3 flex items-center justify-between">
                          <span className="text-xs text-gray-400">v{app.version}</span>
                          <Button
                            variant="primary"
                            className="text-sm py-1 px-3"
                            onClick={() => openInstallModal(app)}
                            disabled={actionLoading === app.id}
                          >
                            {actionLoading === app.id ? 'Installing...' : 'Install'}
                          </Button>
                        </div>
                      </div>
                    </div>
                  </CardBody>
                </Card>
              ))}
            </div>
          </div>
        )
      })}

      {filteredApps.length === 0 && (
        <div className="text-center py-12 text-gray-500">
          No applications found matching "{searchTerm}"
        </div>
      )}

      {/* Installed Apps */}
      {installedApps.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Installed Applications</h2>
          <Card>
            <CardBody className="p-0">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Installed</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {installedApps.map((app) => (
                    <tr key={app.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <div className="w-8 h-8 bg-primary-100 rounded flex items-center justify-center text-sm mr-3">
                            {app.name.charAt(0)}
                          </div>
                          <span className="font-medium text-gray-900">{app.name}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <a
                          href={`https://${app.domain}${app.path}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex items-center text-primary-600 hover:text-primary-800"
                        >
                          <GlobeAltIcon className="w-4 h-4 mr-1" />
                          {app.domain}{app.path !== '/' ? app.path : ''}
                        </a>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {app.version}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {new Date(app.installed_at).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        <div className="flex space-x-2">
                          {app.admin_url && (
                            <a
                              href={app.admin_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-gray-600 hover:text-gray-800"
                              title="Admin Panel"
                            >
                              <Cog6ToothIcon className="w-4 h-4" />
                            </a>
                          )}
                          <button
                            onClick={() => handleUpdate(app)}
                            className="text-primary-600 hover:text-primary-800 disabled:opacity-50"
                            disabled={actionLoading === app.id}
                            title="Update"
                          >
                            <ArrowPathIcon className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleUninstall(app)}
                            className="text-red-600 hover:text-red-800 disabled:opacity-50"
                            disabled={actionLoading === app.id}
                            title="Uninstall"
                          >
                            <TrashIcon className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardBody>
          </Card>
        </div>
      )}

      {/* Install Modal */}
      {showInstallModal && selectedApp && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Install {selectedApp.name}</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Domain *</label>
                <select
                  className="input"
                  value={installData.domain}
                  onChange={(e) => setInstallData({ ...installData, domain: e.target.value })}
                >
                  {domains.map((domain) => (
                    <option key={domain.id} value={domain.name}>{domain.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">Directory Path</label>
                <input
                  type="text"
                  className="input"
                  placeholder="/"
                  value={installData.path}
                  onChange={(e) => setInstallData({ ...installData, path: e.target.value })}
                />
                <p className="text-xs text-gray-500 mt-1">Use "/" for root or "/blog" for subdirectory</p>
              </div>
              <div>
                <label className="label">Site Name</label>
                <input
                  type="text"
                  className="input"
                  placeholder="My Website"
                  value={installData.site_name}
                  onChange={(e) => setInstallData({ ...installData, site_name: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Admin Username</label>
                <input
                  type="text"
                  className="input"
                  placeholder="admin"
                  value={installData.admin_user}
                  onChange={(e) => setInstallData({ ...installData, admin_user: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Admin Email *</label>
                <input
                  type="email"
                  className="input"
                  placeholder="admin@example.com"
                  value={installData.admin_email}
                  onChange={(e) => setInstallData({ ...installData, admin_email: e.target.value })}
                />
              </div>
              <div>
                <label className="label">Admin Password *</label>
                <input
                  type="password"
                  className="input"
                  placeholder="Strong password"
                  value={installData.admin_password}
                  onChange={(e) => setInstallData({ ...installData, admin_password: e.target.value })}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowInstallModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleInstall}
                disabled={actionLoading === selectedApp.id}
              >
                {actionLoading === selectedApp.id ? 'Installing...' : 'Install'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
