import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { useAuth } from "../../hooks/useAuth";
import StatCard from "../../components/common/StatCard";
import { Card, CardHeader, CardBody } from "../../components/common/Card";
import ProgressBar from "../../components/common/ProgressBar";
import Badge from "../../components/common/Badge";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from "recharts";
import {
  GlobeAltIcon,
  EnvelopeIcon,
  CircleStackIcon,
  ServerIcon,
  ArrowRightIcon,
  CubeIcon,
  LockClosedIcon,
  FolderIcon,
  ArchiveBoxIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ShieldCheckIcon,
} from "@heroicons/react/24/outline";

// Sample data for charts
const bandwidthData = [
  { name: "Mon", value: 4 },
  { name: "Tue", value: 7 },
  { name: "Wed", value: 5 },
  { name: "Thu", value: 8 },
  { name: "Fri", value: 12 },
  { name: "Sat", value: 9 },
  { name: "Sun", value: 6 },
];

const diskUsageData = [
  { name: "Used", value: 2.4 },
  { name: "Free", value: 7.6 },
];

const COLORS = ["#3b82f6", "#e5e7eb"];

const quickActions = [
  {
    name: "Create Email",
    description: "Add new mailbox",
    href: "/email",
    icon: EnvelopeIcon,
    color: "text-blue-500",
    bgColor: "bg-blue-50 hover:bg-blue-100",
  },
  {
    name: "Add Domain",
    description: "Setup new domain",
    href: "/domains",
    icon: GlobeAltIcon,
    color: "text-green-500",
    bgColor: "bg-green-50 hover:bg-green-100",
  },
  {
    name: "New Database",
    description: "Create MySQL DB",
    href: "/databases",
    icon: CircleStackIcon,
    color: "text-purple-500",
    bgColor: "bg-purple-50 hover:bg-purple-100",
  },
  {
    name: "Install App",
    description: "WordPress & more",
    href: "/apps",
    icon: CubeIcon,
    color: "text-orange-500",
    bgColor: "bg-orange-50 hover:bg-orange-100",
  },
  {
    name: "File Manager",
    description: "Browse files",
    href: "/files",
    icon: FolderIcon,
    color: "text-cyan-500",
    bgColor: "bg-cyan-50 hover:bg-cyan-100",
  },
  {
    name: "SSL Certificates",
    description: "Manage SSL",
    href: "/ssl",
    icon: LockClosedIcon,
    color: "text-emerald-500",
    bgColor: "bg-emerald-50 hover:bg-emerald-100",
  },
];

const recentActivity = [
  {
    action: "SSL certificate renewed",
    target: "example.com",
    time: "2 hours ago",
    status: "success",
    icon: ShieldCheckIcon,
  },
  {
    action: "Email account created",
    target: "info@example.com",
    time: "1 day ago",
    status: "success",
    icon: EnvelopeIcon,
  },
  {
    action: "WordPress installed",
    target: "example.com/blog",
    time: "3 days ago",
    status: "success",
    icon: CubeIcon,
  },
  {
    action: "Backup completed",
    target: "wp_database",
    time: "1 week ago",
    status: "success",
    icon: ArchiveBoxIcon,
  },
];

export default function Dashboard() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Simulate loading
    const timer = setTimeout(() => setLoading(false), 500);
    return () => clearTimeout(timer);
  }, []);

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return "Good morning";
    if (hour < 18) return "Good afternoon";
    return "Good evening";
  };

  const formatBytes = (bytes: number) => {
    if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(1)} GB`
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${bytes} B`
  }

  const formatNumber = (num: number) => {
    return new Intl.NumberFormat().format(num)
  }

  // Prepare bandwidth chart data
  const bandwidthChartData = bandwidthStats?.history
    ? Object.entries(bandwidthStats.history).map(([date, value]) => ({
        name: new Date(date).toLocaleDateString('en-US', { weekday: 'short' }),
        value: value / 1073741824, // Convert to GB
      }))
    : []

  // Prepare disk usage pie data
  const diskUsedGB = resourceUsage ? resourceUsage.disk.used / 1073741824 : 0
  const diskLimitGB = resourceUsage ? resourceUsage.disk.limit / 1073741824 : 10
  const diskFreeGB = diskLimitGB - diskUsedGB
  const diskUsageData = [
    { name: 'Used', value: diskUsedGB },
    { name: 'Free', value: diskFreeGB > 0 ? diskFreeGB : 0 },
  ]

  return (
    <div className="space-y-6">
      {/* Welcome Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {getGreeting()}, {user?.username}!
          </h1>
          <p className="mt-1 text-gray-500">
            Here's an overview of your hosting account.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant="success" dot>
            All systems operational
          </Badge>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        <StatCard
          title="Domains"
          value={resourceUsage?.quotas.domains.used.toString() || '0'}
          subtitle={`of ${resourceUsage?.quotas.domains.limit || 0} available`}
          icon={GlobeAltIcon}
          color="blue"
          loading={loading}
        />
        <StatCard
          title="Email Accounts"
          value={resourceUsage?.quotas.email_accounts.used.toString() || '0'}
          subtitle={`of ${resourceUsage?.quotas.email_accounts.limit || 0} available`}
          icon={EnvelopeIcon}
          color="green"
          loading={loading}
        />
        <StatCard
          title="Databases"
          value={resourceUsage?.quotas.databases.used.toString() || '0'}
          subtitle={`of ${resourceUsage?.quotas.databases.limit || 0} available`}
          icon={CircleStackIcon}
          color="purple"
          loading={loading}
        />
        <StatCard
          title="Disk Usage"
          value={resourceUsage ? formatBytes(resourceUsage.disk.used) : '0 B'}
          subtitle={`of ${resourceUsage ? formatBytes(resourceUsage.disk.limit) : '0 B'} (${
            resourceUsage?.disk.percent || 0
          }%)`}
          icon={ServerIcon}
          color="yellow"
          loading={loading}
        />
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Bandwidth Chart */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-gray-900">
                Bandwidth Usage
              </h2>
              <span className="text-sm text-gray-500">Last 7 days</span>
            </div>
          </CardHeader>
          <CardBody>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={bandwidthData}>
                  <defs>
                    <linearGradient
                      id="colorBandwidth"
                      x1="0"
                      y1="0"
                      x2="0"
                      y2="1"
                    >
                      <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid
                    strokeDasharray="3 3"
                    vertical={false}
                    stroke="#e5e7eb"
                  />
                  <XAxis
                    dataKey="name"
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: "#6b7280", fontSize: 12 }}
                  />
                  <YAxis
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: "#6b7280", fontSize: 12 }}
                    tickFormatter={(value) => `${value} GB`}
                  />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "#fff",
                      border: "1px solid #e5e7eb",
                      borderRadius: "8px",
                      boxShadow: "0 4px 6px -1px rgba(0, 0, 0, 0.1)",
                    }}
                    formatter={(value: number) => [`${value} GB`, "Bandwidth"]}
                  />
                  <Area
                    type="monotone"
                    dataKey="value"
                    stroke="#3b82f6"
                    strokeWidth={2}
                    fillOpacity={1}
                    fill="url(#colorBandwidth)"
                  />
                </AreaChart>
              </ResponsiveContainer>
            </div>
            <div className="mt-4 flex items-center justify-between text-sm">
              <span className="text-gray-500">
                Total this month:{" "}
                <span className="font-medium text-gray-900">45 GB</span>
              </span>
              <span className="text-gray-500">
                Limit: <span className="font-medium text-gray-900">100 GB</span>
              </span>
            </div>
          </CardBody>
        </Card>

        {/* Disk Usage Pie Chart */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Disk Space</h2>
          </CardHeader>
          <CardBody>
            <div className="h-48">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={diskUsageData}
                    cx="50%"
                    cy="50%"
                    innerRadius={50}
                    outerRadius={70}
                    paddingAngle={2}
                    dataKey="value"
                  >
                    {diskUsageData.map((_, index) => (
                      <Cell
                        key={`cell-${index}`}
                        fill={COLORS[index % COLORS.length]}
                      />
                    ))}
                  </Pie>
                  <Tooltip
                    formatter={(value: number) => [`${value} GB`, ""]}
                    contentStyle={{
                      backgroundColor: "#fff",
                      border: "1px solid #e5e7eb",
                      borderRadius: "8px",
                    }}
                  />
                </PieChart>
              </ResponsiveContainer>
            </div>
            <div className="text-center mt-2">
              <p className="text-2xl font-bold text-gray-900">
                {resourceUsage ? formatBytes(resourceUsage.disk.used) : '0 B'}
              </p>
              <p className="text-sm text-gray-500">
                of {resourceUsage ? formatBytes(resourceUsage.disk.limit) : '0 B'} used
              </p>
            </div>
            <div className="mt-4 flex items-center justify-center gap-4 text-sm">
              <span className="flex items-center gap-2">
                <span className="w-3 h-3 rounded-full bg-blue-500" />
                Used
              </span>
              <span className="flex items-center gap-2">
                <span className="w-3 h-3 rounded-full bg-gray-200" />
                Free
              </span>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Resource Usage & Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Resource Usage */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">
              Resource Limits
            </h2>
          </CardHeader>
          <CardBody className="space-y-5">
            {loading ? (
              <div className="flex items-center justify-center py-8">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600" />
              </div>
            ) : resourceUsage ? (
              <>
                <ProgressBar
                  value={resourceUsage.disk.used}
                  max={resourceUsage.disk.limit}
                  label="Disk Space"
                  showValue
                  valueLabel={`${formatBytes(resourceUsage.disk.used)} / ${formatBytes(
                    resourceUsage.disk.limit
                  )}`}
                />
                <ProgressBar
                  value={resourceUsage.bandwidth.used}
                  max={resourceUsage.bandwidth.limit}
                  label="Bandwidth"
                  showValue
                  valueLabel={`${formatBytes(resourceUsage.bandwidth.used)} / ${formatBytes(
                    resourceUsage.bandwidth.limit
                  )}`}
                />
                <ProgressBar
                  value={resourceUsage.inodes.used}
                  max={resourceUsage.inodes.limit}
                  label="Inodes"
                  showValue
                  valueLabel={`${formatNumber(resourceUsage.inodes.used)} / ${formatNumber(
                    resourceUsage.inodes.limit
                  )}`}
                />
                <ProgressBar
                  value={resourceUsage.quotas.email_accounts.used}
                  max={resourceUsage.quotas.email_accounts.limit}
                  label="Email Accounts"
                  showValue
                  valueLabel={`${resourceUsage.quotas.email_accounts.used} / ${resourceUsage.quotas.email_accounts.limit}`}
                />
                <ProgressBar
                  value={resourceUsage.quotas.databases.used}
                  max={resourceUsage.quotas.databases.limit}
                  label="MySQL Databases"
                  showValue
                  valueLabel={`${resourceUsage.quotas.databases.used} / ${resourceUsage.quotas.databases.limit}`}
                />
              </>
            ) : (
              <div className="text-center py-8 text-gray-500">
                Unable to load resource usage
              </div>
            )}
          </CardBody>
        </Card>

        {/* Quick Actions */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">
              Quick Actions
            </h2>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-2 gap-3">
              {quickActions.map((action) => (
                <Link
                  key={action.name}
                  to={action.href}
                  className={`flex items-center p-4 rounded-xl transition-all duration-200 group ${action.bgColor}`}
                >
                  <action.icon
                    className={`w-8 h-8 ${action.color} flex-shrink-0`}
                  />
                  <div className="ml-3 min-w-0">
                    <p className="font-medium text-gray-900 group-hover:text-gray-700 truncate">
                      {action.name}
                    </p>
                    <p className="text-xs text-gray-500 truncate">
                      {action.description}
                    </p>
                  </div>
                </Link>
              ))}
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Recent Activity */}
      <Card>
        <CardHeader className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">
            Recent Activity
          </h2>
          <Link
            to="/settings"
            className="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1"
          >
            View all
            <ArrowRightIcon className="w-4 h-4" />
          </Link>
        </CardHeader>
        <CardBody className="p-0">
          <div className="divide-y divide-gray-100">
            {recentActivity.map((item, index) => (
              <div
                key={index}
                className="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 transition-colors"
              >
                <div
                  className={`p-2 rounded-lg ${
                    item.status === "success" ? "bg-green-50" : "bg-yellow-50"
                  }`}
                >
                  <item.icon
                    className={`w-5 h-5 ${
                      item.status === "success"
                        ? "text-green-600"
                        : "text-yellow-600"
                    }`}
                  />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900">
                    {item.action}
                  </p>
                  <p className="text-sm text-gray-500 truncate">
                    {item.target}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  {item.status === "success" ? (
                    <CheckCircleIcon className="w-5 h-5 text-green-500" />
                  ) : (
                    <ExclamationTriangleIcon className="w-5 h-5 text-yellow-500" />
                  )}
                  <span className="text-sm text-gray-400 whitespace-nowrap">{item.time}</span>
                </div>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
