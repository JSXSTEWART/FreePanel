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
              </div>
            </CardBody>
          </Card>
        ))}
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
    </div>
  );
}
