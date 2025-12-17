import { useState, useEffect } from 'react'
import StatCard from '../../components/common/StatCard'
import { Card, CardHeader, CardBody } from '../../components/common/Card'
import { accountsApi, servicesApi, serverApi, Service } from '../../api'
import toast from 'react-hot-toast'
import {
  UsersIcon,
  ServerStackIcon,
  CpuChipIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ArrowPathIcon,
  PlayIcon,
  StopIcon,
} from '@heroicons/react/24/outline'

interface ServerInfo {
  hostname: string
  os: string
  kernel: string
  uptime: string
  load: number[]
}

interface ServerLoad {
  cpu: number
  memory: { used: number; total: number; percent: number }
  load: number[]
}

interface DiskInfo {
  filesystem: string
  mount: string
  size: number
  used: number
  available: number
  percent: number
}

export default function AdminDashboard() {
  const [loading, setLoading] = useState(true)
  const [accountCount, setAccountCount] = useState(0)
  const [suspendedCount, setSuspendedCount] = useState(0)
  const [services, setServices] = useState<Service[]>([])
  const [serverInfo, setServerInfo] = useState<ServerInfo | null>(null)
  const [serverLoad, setServerLoad] = useState<ServerLoad | null>(null)
  const [diskInfo, setDiskInfo] = useState<DiskInfo | null>(null)
  const [actionLoading, setActionLoading] = useState<string | null>(null)

  useEffect(() => {
    loadData()
    // Refresh every 30 seconds
    const interval = setInterval(loadData, 30000)
    return () => clearInterval(interval)
  }, [])

  const loadData = async () => {
    try {
      setLoading(true)
      const [accountsData, servicesData, infoData, loadData, diskData] = await Promise.all([
        accountsApi.list().catch(() => ({ data: [], total: 0 })),
        servicesApi.list().catch(() => []),
        serverApi.getInfo().catch(() => null),
        serverApi.getLoad().catch(() => null),
        serverApi.getDisk().catch(() => []),
      ])

      const accounts = accountsData.data || []
      setAccountCount(accounts.length)
      setSuspendedCount(accounts.filter(a => a.status === 'suspended').length)
      setServices(servicesData || [])
      setServerInfo(infoData)
      setServerLoad(loadData)
      // Get root disk info
      const rootDisk = diskData.find((d: DiskInfo) => d.mount === '/') || diskData[0] || null
      setDiskInfo(rootDisk)
    } catch (error) {
      toast.error('Failed to load dashboard data')
    } finally {
      setLoading(false)
    }
  }

  const handleServiceAction = async (service: Service, action: 'start' | 'stop' | 'restart') => {
    try {
      setActionLoading(`${service.service_name}-${action}`)
      await servicesApi[action](service.service_name)
      toast.success(`${service.display_name} ${action}ed successfully`)
      loadData()
    } catch (error) {
      toast.error(`Failed to ${action} ${service.display_name}`)
    } finally {
      setActionLoading(null)
    }
  }

  const formatUptime = (uptime: string): string => {
    return uptime || 'Unknown'
  }

  const formatBytes = (bytes: number): string => {
    if (bytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i]
  }

  if (loading && accountCount === 0) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          title="Total Accounts"
          value={accountCount.toString()}
          subtitle={`${suspendedCount} suspended`}
          icon={UsersIcon}
          color="blue"
        />
        <StatCard
          title="Services Running"
          value={services.filter(s => s.is_running).length.toString()}
          subtitle={`${services.length} total services`}
          icon={ServerStackIcon}
          color="green"
        />
        <StatCard
          title="Server Load"
          value={serverInfo?.load?.[0]?.toFixed(2) || serverLoad?.load?.[0]?.toFixed(2) || '0.00'}
          subtitle="1 min average"
          icon={CpuChipIcon}
          color="purple"
        />
        <StatCard
          title="Disk Usage"
          value={diskInfo ? `${diskInfo.percent}%` : '0%'}
          subtitle={diskInfo ? `${formatBytes(diskInfo.used)} / ${formatBytes(diskInfo.size)}` : ''}
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
                <dd className="font-medium text-gray-900">{serverInfo?.hostname || 'N/A'}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-gray-500">Operating System</dt>
                <dd className="font-medium text-gray-900">{serverInfo?.os || 'N/A'}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-gray-500">Kernel</dt>
                <dd className="font-medium text-gray-900">{serverInfo?.kernel || 'N/A'}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-gray-500">Uptime</dt>
                <dd className="font-medium text-gray-900">{formatUptime(serverInfo?.uptime || '')}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-gray-500">FreePanel Version</dt>
                <dd className="font-medium text-gray-900">1.0.0</dd>
              </div>
            </dl>

            {/* Resource Bars */}
            <div className="mt-6 space-y-4">
              <div>
                <div className="flex justify-between text-sm mb-1">
                  <span className="text-gray-600">CPU Usage</span>
                  <span className="font-medium">{serverLoad?.cpu || 0}%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div
                    className={`h-2 rounded-full ${(serverLoad?.cpu || 0) > 80 ? 'bg-red-500' : 'bg-blue-500'}`}
                    style={{ width: `${serverLoad?.cpu || 0}%` }}
                  ></div>
                </div>
              </div>
              <div>
                <div className="flex justify-between text-sm mb-1">
                  <span className="text-gray-600">Memory Usage</span>
                  <span className="font-medium">{serverLoad?.memory?.percent || 0}%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div
                    className={`h-2 rounded-full ${(serverLoad?.memory?.percent || 0) > 80 ? 'bg-red-500' : 'bg-green-500'}`}
                    style={{ width: `${serverLoad?.memory?.percent || 0}%` }}
                  ></div>
                </div>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Services */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-gray-900">Services Status</h2>
              <button
                onClick={loadData}
                className="text-gray-400 hover:text-gray-600"
                title="Refresh"
              >
                <ArrowPathIcon className="w-5 h-5" />
              </button>
            </div>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-1 gap-3">
              {services.length === 0 ? (
                <p className="text-gray-500 text-center py-4">No services found</p>
              ) : (
                services.map((service) => (
                  <div
                    key={service.service_name}
                    className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                  >
                    <div className="flex items-center">
                      {service.is_running ? (
                        <CheckCircleIcon className="w-5 h-5 text-green-500 mr-2" />
                      ) : (
                        <ExclamationCircleIcon className="w-5 h-5 text-red-500 mr-2" />
                      )}
                      <span className="font-medium text-gray-900">{service.display_name}</span>
                    </div>
                    <div className="flex items-center space-x-2">
                      <span className={`text-sm ${service.is_running ? 'text-green-600' : 'text-red-600'}`}>
                        {service.is_running ? 'Running' : 'Stopped'}
                      </span>
                      <div className="flex space-x-1">
                        {!service.is_running && (
                          <button
                            onClick={() => handleServiceAction(service, 'start')}
                            className="p-1 text-green-600 hover:bg-green-100 rounded"
                            disabled={actionLoading === `${service.service_name}-start`}
                            title="Start"
                          >
                            <PlayIcon className="w-4 h-4" />
                          </button>
                        )}
                        {service.is_running && (
                          <>
                            <button
                              onClick={() => handleServiceAction(service, 'restart')}
                              className="p-1 text-blue-600 hover:bg-blue-100 rounded"
                              disabled={actionLoading === `${service.service_name}-restart`}
                              title="Restart"
                            >
                              <ArrowPathIcon className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleServiceAction(service, 'stop')}
                              className="p-1 text-red-600 hover:bg-red-100 rounded"
                              disabled={actionLoading === `${service.service_name}-stop`}
                              title="Stop"
                            >
                              <StopIcon className="w-4 h-4" />
                            </button>
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
