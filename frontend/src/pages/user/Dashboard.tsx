import { useState, useEffect } from 'react'
import { useAuth } from '../../hooks/useAuth'
import StatCard from '../../components/common/StatCard'
import { Card, CardHeader, CardBody } from '../../components/common/Card'
import { statsApi, domainsApi, emailApi, databasesApi, ResourceUsage, Domain } from '../../api'
import toast from 'react-hot-toast'
import {
  GlobeAltIcon,
  EnvelopeIcon,
  CircleStackIcon,
  ServerIcon,
  ArrowUpIcon,
} from '@heroicons/react/24/outline'

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  if (bytes === -1) return 'Unlimited'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

export default function Dashboard() {
  const { user } = useAuth()
  const [loading, setLoading] = useState(true)
  const [resourceUsage, setResourceUsage] = useState<ResourceUsage | null>(null)
  const [domainCount, setDomainCount] = useState(0)
  const [emailCount, setEmailCount] = useState(0)
  const [databaseCount, setDatabaseCount] = useState(0)
  const [domains, setDomains] = useState<Domain[]>([])

  useEffect(() => {
    loadDashboardData()
  }, [])

  const loadDashboardData = async () => {
    try {
      setLoading(true)
      const [usage, domainsData, emailsData, dbsData] = await Promise.all([
        statsApi.getResourceUsage(),
        domainsApi.list(),
        emailApi.listAccounts(),
        databasesApi.list(),
      ])
      setResourceUsage(usage)
      setDomains(domainsData)
      setDomainCount(domainsData.length)
      setEmailCount(emailsData.length)
      setDatabaseCount(dbsData.length)
    } catch (error) {
      toast.error('Failed to load dashboard data')
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  const activeDomains = domains.filter(d => d.status === 'active').length

  return (
    <div className="space-y-6">
      {/* Welcome */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">
          Welcome back, {user?.username}!
        </h1>
        <p className="mt-1 text-gray-500">
          Here's what's happening with your hosting account.
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          title="Domains"
          value={domainCount.toString()}
          subtitle={`${activeDomains} active`}
          icon={GlobeAltIcon}
          color="blue"
        />
        <StatCard
          title="Email Accounts"
          value={emailCount.toString()}
          subtitle={resourceUsage?.quotas?.email_accounts
            ? `of ${resourceUsage.quotas.email_accounts.limit === -1 ? 'unlimited' : resourceUsage.quotas.email_accounts.limit}`
            : 'accounts'}
          icon={EnvelopeIcon}
          color="green"
        />
        <StatCard
          title="Databases"
          value={databaseCount.toString()}
          subtitle={resourceUsage?.quotas?.databases
            ? `of ${resourceUsage.quotas.databases.limit === -1 ? 'unlimited' : resourceUsage.quotas.databases.limit}`
            : 'databases'}
          icon={CircleStackIcon}
          color="purple"
        />
        <StatCard
          title="Disk Usage"
          value={resourceUsage ? formatBytes(resourceUsage.disk.used) : '0 B'}
          subtitle={resourceUsage ? `of ${formatBytes(resourceUsage.disk.limit)}` : ''}
          icon={ServerIcon}
          color="yellow"
        />
      </div>

      {/* Usage & Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Resource Usage */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Resource Usage</h2>
          </CardHeader>
          <CardBody className="space-y-4">
            {/* Disk */}
            <div>
              <div className="flex justify-between text-sm mb-1">
                <span className="text-gray-600">Disk Space</span>
                <span className="font-medium">
                  {resourceUsage ? `${formatBytes(resourceUsage.disk.used)} / ${formatBytes(resourceUsage.disk.limit)}` : 'Loading...'}
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div
                  className={`h-2.5 rounded-full ${resourceUsage && resourceUsage.disk.percent > 90 ? 'bg-red-500' : 'bg-blue-500'}`}
                  style={{ width: `${resourceUsage?.disk.percent || 0}%` }}
                ></div>
              </div>
            </div>

            {/* Bandwidth */}
            <div>
              <div className="flex justify-between text-sm mb-1">
                <span className="text-gray-600">Bandwidth</span>
                <span className="font-medium">
                  {resourceUsage ? `${formatBytes(resourceUsage.bandwidth.used)} / ${formatBytes(resourceUsage.bandwidth.limit)}` : 'Loading...'}
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div
                  className={`h-2.5 rounded-full ${resourceUsage && resourceUsage.bandwidth.percent > 90 ? 'bg-red-500' : 'bg-green-500'}`}
                  style={{ width: `${resourceUsage?.bandwidth.percent || 0}%` }}
                ></div>
              </div>
            </div>

            {/* Inodes */}
            <div>
              <div className="flex justify-between text-sm mb-1">
                <span className="text-gray-600">Inodes</span>
                <span className="font-medium">
                  {resourceUsage ? `${resourceUsage.inodes.used.toLocaleString()} / ${resourceUsage.inodes.limit.toLocaleString()}` : 'Loading...'}
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div
                  className={`h-2.5 rounded-full ${resourceUsage && resourceUsage.inodes.percent > 90 ? 'bg-red-500' : 'bg-purple-500'}`}
                  style={{ width: `${resourceUsage?.inodes.percent || 0}%` }}
                ></div>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Quick Actions */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Quick Actions</h2>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-2 gap-4">
              <a href="/email" className="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <EnvelopeIcon className="w-8 h-8 text-blue-500" />
                <div className="ml-3">
                  <p className="font-medium text-gray-900">Create Email</p>
                  <p className="text-sm text-gray-500">Add new mailbox</p>
                </div>
              </a>
              <a href="/domains" className="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <GlobeAltIcon className="w-8 h-8 text-green-500" />
                <div className="ml-3">
                  <p className="font-medium text-gray-900">Add Domain</p>
                  <p className="text-sm text-gray-500">Setup new domain</p>
                </div>
              </a>
              <a href="/databases" className="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <CircleStackIcon className="w-8 h-8 text-purple-500" />
                <div className="ml-3">
                  <p className="font-medium text-gray-900">New Database</p>
                  <p className="text-sm text-gray-500">Create MySQL DB</p>
                </div>
              </a>
              <a href="/apps" className="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <ArrowUpIcon className="w-8 h-8 text-orange-500" />
                <div className="ml-3">
                  <p className="font-medium text-gray-900">Install App</p>
                  <p className="text-sm text-gray-500">WordPress, etc.</p>
                </div>
              </a>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Domains Overview */}
      {domains.length > 0 && (
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Your Domains</h2>
          </CardHeader>
          <CardBody className="p-0">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SSL</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {domains.slice(0, 5).map((domain) => (
                  <tr key={domain.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <GlobeAltIcon className="w-5 h-5 text-gray-400 mr-3" />
                        <span className="font-medium text-gray-900">{domain.name}</span>
                        {domain.is_main && (
                          <span className="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                            Main
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                        domain.status === 'active'
                          ? 'bg-green-100 text-green-800'
                          : domain.status === 'pending'
                          ? 'bg-yellow-100 text-yellow-800'
                          : 'bg-red-100 text-red-800'
                      }`}>
                        {domain.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {domain.ssl_certificate ? (
                        <span className="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                          Active
                        </span>
                      ) : (
                        <span className="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">
                          None
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}
    </div>
  )
}
