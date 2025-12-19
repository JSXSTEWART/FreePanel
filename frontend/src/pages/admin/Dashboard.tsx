import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import StatCard from '../../components/common/StatCard'
import { Card, CardHeader, CardBody } from '../../components/common/Card'

import Badge, { StatusBadge } from '../../components/common/Badge'
import Button from '../../components/common/Button'
import {
  AreaChart,
  Area,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  LineChart,
  Line,
} from 'recharts'
import {
  UsersIcon,
  ServerStackIcon,
  GlobeAltIcon,
  CpuChipIcon,
  ArrowRightIcon,
  ArrowPathIcon,
  PlayIcon,
  StopIcon,
  ClockIcon,
} from '@heroicons/react/24/outline'

// Sample data for charts
const accountGrowthData = [
  { month: 'Jul', accounts: 98 },
  { month: 'Aug', accounts: 105 },
  { month: 'Sep', accounts: 112 },
  { month: 'Oct', accounts: 118 },
  { month: 'Nov', accounts: 122 },
  { month: 'Dec', accounts: 127 },
]

const serverLoadData = [
  { time: '00:00', load: 0.35 },
  { time: '04:00', load: 0.28 },
  { time: '08:00', load: 0.52 },
  { time: '12:00', load: 0.68 },
  { time: '16:00', load: 0.85 },
  { time: '20:00', load: 0.42 },
  { time: 'Now', load: 0.42 },
]

const resourceUsageData = [
  { name: 'CPU', value: 32 },
  { name: 'Memory', value: 58 },
  { name: 'Disk', value: 68 },
  { name: 'Network', value: 24 },
]

const services = [
  { name: 'Apache', status: 'running', port: 80 },
  { name: 'MySQL', status: 'running', port: 3306 },
  { name: 'Dovecot', status: 'running', port: 993 },
  { name: 'Exim', status: 'running', port: 25 },
  { name: 'ProFTPD', status: 'stopped', port: 21 },
  { name: 'BIND', status: 'running', port: 53 },
  { name: 'PHP-FPM', status: 'running', port: 9000 },
  { name: 'Redis', status: 'running', port: 6379 },
]

const recentAccounts = [
  { username: 'johndoe', domain: 'johndoe.com', package: 'Business', status: 'active', created: '2024-01-15' },
  { username: 'janedoe', domain: 'janedoe.com', package: 'Starter', status: 'active', created: '2024-01-14' },
  { username: 'acmecorp', domain: 'acme.com', package: 'Enterprise', status: 'active', created: '2024-01-13' },
  { username: 'testuser', domain: 'test.com', package: 'Starter', status: 'suspended', created: '2024-01-12' },
  { username: 'newsite', domain: 'newsite.io', package: 'Business', status: 'active', created: '2024-01-11' },
]

export default function AdminDashboard() {
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  useEffect(() => {
    const timer = setTimeout(() => setLoading(false), 500)
    return () => clearTimeout(timer)
  }, [])

  const handleRefresh = () => {
    setRefreshing(true)
    setTimeout(() => setRefreshing(false), 1000)
  }

  const runningServices = services.filter(s => s.status === 'running').length
  const totalServices = services.length

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Server Dashboard</h1>
          <p className="mt-1 text-gray-500">Monitor and manage your hosting server</p>
        </div>
        <div className="flex items-center gap-3">
          <Badge variant={runningServices === totalServices ? 'success' : 'warning'} dot>
            {runningServices}/{totalServices} Services Online
          </Badge>
          <Button
            variant="secondary"
            size="sm"
            onClick={handleRefresh}
            disabled={refreshing}
          >
            <ArrowPathIcon className={`w-4 h-4 mr-2 ${refreshing ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        <StatCard
          title="Total Accounts"
          value="127"
          subtitle="5 suspended"
          icon={UsersIcon}
          color="blue"
          loading={loading}
          trend={{ value: 4.2, isPositive: true, label: 'this month' }}
        />
        <StatCard
          title="Total Domains"
          value="284"
          subtitle="Across all accounts"
          icon={GlobeAltIcon}
          color="green"
          loading={loading}
          trend={{ value: 8, isPositive: true, label: 'this month' }}
        />
        <StatCard
          title="Server Load"
          value="0.42"
          subtitle="1 min average"
          icon={CpuChipIcon}
          color="purple"
          loading={loading}
        />
        <StatCard
          title="Disk Usage"
          value="68%"
          subtitle="340 GB / 500 GB"
          icon={ServerStackIcon}
          color="yellow"
          loading={loading}
        />
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Account Growth Chart */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-gray-900">Account Growth</h2>
              <span className="text-sm text-gray-500">Last 6 months</span>
            </div>
          </CardHeader>
          <CardBody>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={accountGrowthData}>
                  <defs>
                    <linearGradient id="colorAccounts" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e5e7eb" />
                  <XAxis
                    dataKey="month"
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: '#6b7280', fontSize: 12 }}
                  />
                  <YAxis
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: '#6b7280', fontSize: 12 }}
                  />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: '#fff',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px',
                      boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                    }}
                    formatter={(value: number) => [value, 'Accounts']}
                  />
                  <Area
                    type="monotone"
                    dataKey="accounts"
                    stroke="#3b82f6"
                    strokeWidth={2}
                    fillOpacity={1}
                    fill="url(#colorAccounts)"
                  />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </CardBody>
        </Card>

        {/* Server Load Chart */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-gray-900">Server Load</h2>
              <span className="text-sm text-gray-500">Today</span>
            </div>
          </CardHeader>
          <CardBody>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={serverLoadData}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e5e7eb" />
                  <XAxis
                    dataKey="time"
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: '#6b7280', fontSize: 12 }}
                  />
                  <YAxis
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: '#6b7280', fontSize: 12 }}
                    domain={[0, 1]}
                  />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: '#fff',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px',
                      boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                    }}
                    formatter={(value: number) => [value.toFixed(2), 'Load']}
                  />
                  <Line
                    type="monotone"
                    dataKey="load"
                    stroke="#8b5cf6"
                    strokeWidth={2}
                    dot={{ fill: '#8b5cf6', r: 4 }}
                    activeDot={{ r: 6 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Resource Usage & Services */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Resource Usage */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Resource Usage</h2>
          </CardHeader>
          <CardBody>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={resourceUsageData} layout="vertical">
                  <CartesianGrid strokeDasharray="3 3" horizontal={true} vertical={false} stroke="#e5e7eb" />
                  <XAxis
                    type="number"
                    domain={[0, 100]}
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: '#6b7280', fontSize: 12 }}
                    tickFormatter={(value) => `${value}%`}
                  />
                  <YAxis
                    type="category"
                    dataKey="name"
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: '#6b7280', fontSize: 12 }}
                    width={60}
                  />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: '#fff',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px',
                    }}
                    formatter={(value: number) => [`${value}%`, 'Usage']}
                  />
                  <Bar
                    dataKey="value"
                    fill="#3b82f6"
                    radius={[0, 4, 4, 0]}
                    barSize={24}
                  />
                </BarChart>
              </ResponsiveContainer>
            </div>
            <div className="mt-4 grid grid-cols-2 gap-4">
              <div className="text-center p-3 bg-gray-50 rounded-lg">
                <p className="text-2xl font-bold text-gray-900">16 GB</p>
                <p className="text-sm text-gray-500">Total RAM</p>
              </div>
              <div className="text-center p-3 bg-gray-50 rounded-lg">
                <p className="text-2xl font-bold text-gray-900">8 vCPU</p>
                <p className="text-sm text-gray-500">CPU Cores</p>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Services Status */}
        <Card>
          <CardHeader className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">Services Status</h2>
            <Link
              to="/admin/services"
              className="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1"
            >
              Manage
              <ArrowRightIcon className="w-4 h-4" />
            </Link>
          </CardHeader>
          <CardBody className="p-0">
            <div className="divide-y divide-gray-100">
              {services.map((service) => (
                <div
                  key={service.name}
                  className="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <div className={`w-2 h-2 rounded-full ${
                      service.status === 'running' ? 'bg-green-500' : 'bg-red-500'
                    }`} />
                    <div>
                      <span className="font-medium text-gray-900">{service.name}</span>
                      <span className="text-gray-400 text-sm ml-2">:{service.port}</span>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <StatusBadge status={service.status as 'running' | 'stopped'} />
                    {service.status === 'stopped' && (
                      <button
                        className="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                        title="Start service"
                      >
                        <PlayIcon className="w-4 h-4" />
                      </button>
                    )}
                    {service.status === 'running' && (
                      <button
                        className="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        title="Stop service"
                      >
                        <StopIcon className="w-4 h-4" />
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Server Info & Recent Accounts */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Server Info */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Server Information</h2>
          </CardHeader>
          <CardBody>
            <dl className="space-y-4">
              <div className="flex justify-between items-center">
                <dt className="text-gray-500 text-sm">Hostname</dt>
                <dd className="font-medium text-gray-900 text-sm">server1.example.com</dd>
              </div>
              <div className="flex justify-between items-center">
                <dt className="text-gray-500 text-sm">Operating System</dt>
                <dd className="font-medium text-gray-900 text-sm">AlmaLinux 9.3</dd>
              </div>
              <div className="flex justify-between items-center">
                <dt className="text-gray-500 text-sm">Kernel</dt>
                <dd className="font-medium text-gray-900 text-sm">5.14.0-362.el9</dd>
              </div>
              <div className="flex justify-between items-center">
                <dt className="text-gray-500 text-sm">Uptime</dt>
                <dd className="font-medium text-gray-900 text-sm flex items-center gap-1">
                  <ClockIcon className="w-4 h-4 text-gray-400" />
                  42 days, 7 hours
                </dd>
              </div>
              <div className="flex justify-between items-center">
                <dt className="text-gray-500 text-sm">IP Address</dt>
                <dd className="font-medium text-gray-900 text-sm">192.168.1.100</dd>
              </div>
              <div className="flex justify-between items-center">
                <dt className="text-gray-500 text-sm">FreePanel Version</dt>
                <dd>
                  <Badge variant="primary">v1.0.0</Badge>
                </dd>
              </div>
            </dl>
          </CardBody>
        </Card>

        {/* Recent Accounts */}
        <Card className="lg:col-span-2">
          <CardHeader className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">Recent Accounts</h2>
            <Link
              to="/admin/accounts"
              className="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1"
            >
              View all
              <ArrowRightIcon className="w-4 h-4" />
            </Link>
          </CardHeader>
          <CardBody className="p-0">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="table-header">Account</th>
                    <th className="table-header">Domain</th>
                    <th className="table-header">Package</th>
                    <th className="table-header">Status</th>
                    <th className="table-header">Created</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {recentAccounts.map((account) => (
                    <tr key={account.username} className="table-row">
                      <td className="table-cell">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                            <span className="text-primary-600 font-medium text-sm">
                              {account.username.charAt(0).toUpperCase()}
                            </span>
                          </div>
                          <span className="font-medium text-gray-900">{account.username}</span>
                        </div>
                      </td>
                      <td className="table-cell text-gray-500">
                        <a
                          href={`https://${account.domain}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-primary-600 hover:text-primary-700"
                        >
                          {account.domain}
                        </a>
                      </td>
                      <td className="table-cell">
                        <Badge variant="info">{account.package}</Badge>
                      </td>
                      <td className="table-cell">
                        <StatusBadge status={account.status as 'active' | 'suspended'} />
                      </td>
                      <td className="table-cell text-gray-500">
                        {new Date(account.created).toLocaleDateString('en-US', {
                          month: 'short',
                          day: 'numeric',
                          year: 'numeric',
                        })}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
