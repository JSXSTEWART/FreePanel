import { useAuth } from '../../hooks/useAuth'
import StatCard from '../../components/common/StatCard'
import { Card, CardHeader, CardBody } from '../../components/common/Card'
import {
  GlobeAltIcon,
  EnvelopeIcon,
  CircleStackIcon,
  ServerIcon,
  ArrowUpIcon,
  ClockIcon,
} from '@heroicons/react/24/outline'

export default function Dashboard() {
  const { user } = useAuth()

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
          value="3"
          subtitle="2 active, 1 parked"
          icon={GlobeAltIcon}
          color="blue"
        />
        <StatCard
          title="Email Accounts"
          value="12"
          subtitle="of 100 available"
          icon={EnvelopeIcon}
          color="green"
        />
        <StatCard
          title="Databases"
          value="5"
          subtitle="of 10 available"
          icon={CircleStackIcon}
          color="purple"
        />
        <StatCard
          title="Disk Usage"
          value="2.4 GB"
          subtitle="of 10 GB"
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
                <span className="font-medium">2.4 GB / 10 GB</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div className="bg-blue-500 h-2.5 rounded-full" style={{ width: '24%' }}></div>
              </div>
            </div>

            {/* Bandwidth */}
            <div>
              <div className="flex justify-between text-sm mb-1">
                <span className="text-gray-600">Bandwidth</span>
                <span className="font-medium">45 GB / 100 GB</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div className="bg-green-500 h-2.5 rounded-full" style={{ width: '45%' }}></div>
              </div>
            </div>

            {/* Inodes */}
            <div>
              <div className="flex justify-between text-sm mb-1">
                <span className="text-gray-600">Inodes</span>
                <span className="font-medium">25,420 / 100,000</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div className="bg-purple-500 h-2.5 rounded-full" style={{ width: '25%' }}></div>
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

      {/* Recent Activity */}
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
        </CardHeader>
        <CardBody>
          <div className="space-y-4">
            {[
              { action: 'SSL certificate renewed', domain: 'example.com', time: '2 hours ago' },
              { action: 'Email account created', domain: 'info@example.com', time: '1 day ago' },
              { action: 'WordPress installed', domain: 'example.com/blog', time: '3 days ago' },
              { action: 'Database backup completed', domain: 'wp_database', time: '1 week ago' },
            ].map((item, index) => (
              <div key={index} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                <div className="flex items-center">
                  <ClockIcon className="w-5 h-5 text-gray-400 mr-3" />
                  <div>
                    <p className="text-sm font-medium text-gray-900">{item.action}</p>
                    <p className="text-sm text-gray-500">{item.domain}</p>
                  </div>
                </div>
                <span className="text-sm text-gray-400">{item.time}</span>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>
    </div>
  )
}
