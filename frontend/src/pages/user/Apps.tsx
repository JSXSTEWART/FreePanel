/**
 * Application Installer Component
 *
 * TODO: Implement full API integration for one-click app installation
 *
 * Backend API endpoints to integrate:
 *
 * 1. Available Apps - GET /api/v1/user/apps/available
 *    Response: [{ id, name, version, description, category, icon, requirements }, ...]
 *    Use: Replace hardcoded `apps` array, show requirements before install
 *
 * 2. Installed Apps - GET /api/v1/user/apps/installed
 *    Response: [{ id, app_type, domain, path, version, has_update, installed_at }, ...]
 *    Use: Replace hardcoded installed apps table
 *
 * 3. Install App - POST /api/v1/user/apps/install
 *    Body: { app: 'wordpress', domain_id, path, admin_username, admin_password, admin_email, site_name }
 *    Use: Handle install button click, show installation wizard/modal
 *
 * 4. Update App - POST /api/v1/user/apps/{id}/update
 *    Use: Handle update button click
 *
 * 5. Uninstall App - DELETE /api/v1/user/apps/{id}
 *    Body: { delete_files: true, delete_database: true }
 *    Use: Handle uninstall with confirmation dialog
 *
 * 6. App Details - GET /api/v1/user/apps/{id}
 *    Response: { id, app_type, version, current_version, url, admin_url, has_update, ... }
 *    Use: Show app management panel
 *
 * Implementation requirements:
 * - Add InstallAppModal component with form for admin credentials and settings
 * - Show domain selector dropdown (fetch from /api/v1/user/domains)
 * - Display installation progress (could use polling or WebSocket)
 * - Add confirmation dialogs for destructive actions (uninstall)
 * - Show update available badges on apps that can be updated
 * - Handle staging environment creation (POST /api/v1/user/apps/{id}/staging)
 *
 * Note: Only WordPress installer is currently fully implemented on backend.
 * Other installers (Joomla, Drupal, etc.) have stub implementations with TODOs.
 */
import { Card, CardBody } from '../../components/common/Card'

// TODO: Fetch from API instead of hardcoding
// const { data: availableApps } = useQuery(['available-apps'], () => api.get('/user/apps/available'))
const apps = [
  { name: 'WordPress', version: '6.4', icon: '🔵', description: 'Popular blogging and CMS platform' },
  { name: 'Joomla', version: '5.0', icon: '🟠', description: 'Flexible content management system' },
  { name: 'Drupal', version: '10.2', icon: '💧', description: 'Enterprise-grade CMS' },
  { name: 'PrestaShop', version: '8.1', icon: '🛒', description: 'E-commerce platform' },
  { name: 'phpBB', version: '3.3', icon: '💬', description: 'Forum software' },
  { name: 'Nextcloud', version: '28', icon: '☁️', description: 'File sync and share' },
]

export default function Apps() {
  // TODO: Add state for install modal
  // const [installModalOpen, setInstallModalOpen] = useState(false)
  // const [selectedApp, setSelectedApp] = useState(null)
  //
  // TODO: Fetch installed apps from API
  // const { data: installedApps } = useQuery(['installed-apps'], () => api.get('/user/apps/installed'))
  //
  // TODO: Add install mutation
  // const installMutation = useMutation(
  //   (data) => api.post('/user/apps/install', data),
  //   { onSuccess: () => queryClient.invalidateQueries(['installed-apps']) }
  // )
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Application Installer</h1>
        <p className="text-gray-500">One-click install popular web applications</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {apps.map((app) => (
          <Card key={app.name} className="hover:shadow-md transition-shadow cursor-pointer">
            <CardBody>
              <div className="flex items-start space-x-4">
                <div className="text-4xl">{app.icon}</div>
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-900">{app.name}</h3>
                  <p className="text-sm text-gray-500">{app.description}</p>
                  <div className="mt-3 flex items-center justify-between">
                    <span className="text-xs text-gray-400">v{app.version}</span>
                    <button className="text-sm text-primary-600 hover:text-primary-800 font-medium">
                      Install
                    </button>
                  </div>
                </div>
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Installed Apps */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Installed Applications</h2>
        <Card>
          <CardBody className="p-0">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                <tr>
                  <td className="px-6 py-4 whitespace-nowrap font-medium text-gray-900">WordPress</td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">example.com/blog</td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">6.4.2</td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    <button className="text-primary-600 hover:text-primary-800 mr-3">Manage</button>
                    <button className="text-gray-600 hover:text-gray-800 mr-3">Update</button>
                    <button className="text-red-600 hover:text-red-800">Uninstall</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
