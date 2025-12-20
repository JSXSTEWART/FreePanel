import { Card, CardBody } from "../../components/common/Card";

const apps = [
  {
    name: "WordPress",
    version: "6.4",
    icon: "🔵",
    description: "Popular blogging and CMS platform",
  },
  {
    name: "Joomla",
    version: "5.0",
    icon: "🟠",
    description: "Flexible content management system",
  },
  {
    name: "Drupal",
    version: "10.2",
    icon: "💧",
    description: "Enterprise-grade CMS",
  },
  {
    name: "PrestaShop",
    version: "8.1",
    icon: "🛒",
    description: "E-commerce platform",
  },
  { name: "phpBB", version: "3.3", icon: "💬", description: "Forum software" },
  {
    name: "Nextcloud",
    version: "28",
    icon: "☁️",
    description: "File sync and share",
  },
];

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
        <h1 className="text-2xl font-bold text-gray-900">
          Application Installer
        </h1>
        <p className="text-gray-500">
          One-click install popular web applications
        </p>
      </div>

      {/* Available Apps */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {apps.map((app) => (
          <Card
            key={app.name}
            className="hover:shadow-md transition-shadow cursor-pointer"
          >
            <CardBody>
              <div className="flex items-start space-x-4">
                <div className="text-4xl">{app.icon}</div>
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-900">{app.name}</h3>
                  <p className="text-sm text-gray-500">{app.description}</p>
                  <div className="mt-3 flex items-center justify-between">
                    <span className="text-xs text-gray-400">
                      v{app.version}
                    </span>
                    <button className="text-sm text-primary-600 hover:text-primary-800 font-medium">
                      Install
                    </button>
                  </div>
                </div>
              </CardBody>
            </Card>
          ))
        )}
      </div>

      {/* Installed Apps */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-4">
          Installed Applications
        </h2>
        <Card>
          <CardBody className="p-0">
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
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                <tr>
                  <td className="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                    WordPress
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    example.com/blog
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    6.4.2
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    <button className="text-primary-600 hover:text-primary-800 mr-3">
                      Manage
                    </button>
                    <button className="text-gray-600 hover:text-gray-800 mr-3">
                      Update
                    </button>
                    <button className="text-red-600 hover:text-red-800">
                      Uninstall
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
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
  );
}
