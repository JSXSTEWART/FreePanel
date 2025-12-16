/**
 * Admin Dashboard Component
 *
 * TODO: Implement API integration for server monitoring and admin stats
 *
 * Backend API endpoints to integrate:
 *
 * 1. Account Stats - GET /api/v1/admin/accounts/stats
 *    Response: { total, active, suspended, created_today, created_this_month, by_package }
 *    Use: Replace hardcoded StatCard values
 *
 * 2. Server Info - GET /api/v1/admin/server/info
 *    Response: { hostname, os, kernel, uptime, panel_version, ip_addresses, php_version }
 *    Use: Replace hardcoded server information
 *
 * 3. Services Status - GET /api/v1/admin/services
 *    Response: [{ id, service_name, display_name, status, is_running, uptime, memory, cpu }, ...]
 *    Use: Replace hardcoded services array, enable real-time status updates
 *
 * 4. Server Resources - GET /api/v1/admin/server/resources
 *    Response: { load: [1m, 5m, 15m], memory: { used, total }, disk: { used, total }, cpu_percent }
 *    Use: Display real-time server load, memory, and disk usage
 *
 * 5. Recent Accounts - GET /api/v1/admin/accounts?sort_by=created_at&sort_dir=desc&per_page=5
 *    Response: { data: [{ username, domain, package, status, created_at }, ...] }
 *    Use: Replace hardcoded recent accounts table
 *
 * 6. System Alerts - GET /api/v1/admin/alerts
 *    Response: [{ type, message, severity, created_at }, ...]
 *    Use: Display warnings for disk space, failed services, etc.
 *
 * Implementation requirements:
 * - Add auto-refresh every 30 seconds for server stats
 * - Add service control buttons (start/stop/restart) with confirmation
 * - Show loading states and error handling
 * - Add WebSocket support for real-time service status updates
 * - Display alerts/warnings prominently (e.g., disk >90%, services down)
 * - Add click-through to service management page
 */
import StatCard from '../../components/common/StatCard'
import { Card, CardHeader, CardBody } from '../../components/common/Card'
import {
  UsersIcon,
  ServerStackIcon,
  GlobeAltIcon,
  CpuChipIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
} from '@heroicons/react/24/outline'

export default function AdminDashboard() {
  // TODO: Fetch services from API
  // const { data: services, isLoading } = useQuery(
  //   ['admin', 'services'],
  //   () => api.get('/admin/services').then(res => res.data),
  //   { refetchInterval: 30000 } // Auto-refresh every 30 seconds
  // )
  //
  // TODO: Fetch account stats from API
  // const { data: accountStats } = useQuery(['admin', 'accounts', 'stats'], () => api.get('/admin/accounts/stats'))
  //
  // TODO: Fetch server info from API
  // const { data: serverInfo } = useQuery(['admin', 'server', 'info'], () => api.get('/admin/server/info'))
  //
  // TODO: Fetch recent accounts from API
  // const { data: recentAccounts } = useQuery(['admin', 'accounts', 'recent'], () =>
  //   api.get('/admin/accounts?sort_by=created_at&sort_dir=desc&per_page=5')
  // )

  // TODO: Remove hardcoded mock data and use API response
  const services = [
    { name: 'Apache', status: 'running' },
    { name: 'MySQL', status: 'running' },
    { name: 'Dovecot', status: 'running' },
    { name: 'Exim', status: 'running' },
    { name: 'ProFTPD', status: 'stopped' },
    { name: 'BIND', status: 'running' },
  ]

  return (
    <div className="space-y-6">
      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          title="Total Accounts"
          value="127"
          subtitle="5 suspended"
          icon={UsersIcon}
          color="blue"
        />
        <StatCard
          title="Total Domains"
          value="284"
          subtitle="Across all accounts"
          icon={GlobeAltIcon}
          color="green"
        />
        <StatCard
          title="Server Load"
          value="0.42"
          subtitle="1 min average"
          icon={CpuChipIcon}
          color="purple"
        />
        <StatCard
          title="Disk Usage"
          value="68%"
          subtitle="340 GB / 500 GB"
          icon={ServerStackIcon}
          color="yellow"
        />
      </div>

      {/* Server & Services */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Server Info */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Server Information</h2>
          </CardHeader>
          <CardBody>
            <dl className="space-y-3">
              <div className="flex justify-between">
                <dt className="text-gray-500">Hostname</dt>
                <dd className="font-medium text-gray-900">server1.example.com</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-gray-500">Operating System</dt>
                <dd className="font-medium text-gray-900">AlmaLinux 9.3</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-gray-500">Kernel</dt>
                <dd className="font-medium text-gray-900">5.14.0-362.el9</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-gray-500">Uptime</dt>
                <dd className="font-medium text-gray-900">42 days, 7 hours</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-gray-500">FreePanel Version</dt>
                <dd className="font-medium text-gray-900">1.0.0</dd>
              </div>
            </dl>
          </CardBody>
        </Card>

        {/* Services */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Services Status</h2>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-2 gap-4">
              {services.map((service) => (
                <div
                  key={service.name}
                  className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                >
                  <span className="font-medium text-gray-900">{service.name}</span>
                  {service.status === 'running' ? (
                    <span className="flex items-center text-green-600 text-sm">
                      <CheckCircleIcon className="w-5 h-5 mr-1" />
                      Running
                    </span>
                  ) : (
                    <span className="flex items-center text-red-600 text-sm">
                      <ExclamationCircleIcon className="w-5 h-5 mr-1" />
                      Stopped
                    </span>
                  )}
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Recent Accounts */}
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-gray-900">Recent Accounts</h2>
        </CardHeader>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {[
                { username: 'johndoe', domain: 'johndoe.com', package: 'Business', status: 'active', created: '2024-01-15' },
                { username: 'janedoe', domain: 'janedoe.com', package: 'Starter', status: 'active', created: '2024-01-14' },
                { username: 'acmecorp', domain: 'acme.com', package: 'Enterprise', status: 'active', created: '2024-01-13' },
                { username: 'testuser', domain: 'test.com', package: 'Starter', status: 'suspended', created: '2024-01-12' },
              ].map((account) => (
                <tr key={account.username} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {account.username}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {account.domain}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {account.package}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${
                      account.status === 'active'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                    }`}>
                      {account.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {account.created}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardBody>
      </Card>
    </div>
  )
}
