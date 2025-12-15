import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { PlusIcon, CircleStackIcon } from '@heroicons/react/24/outline'

export default function Databases() {
  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Databases</h1>
          <p className="text-gray-500">Manage MySQL databases and users</p>
        </div>
        <div className="flex space-x-3">
          <Button variant="secondary">Add User</Button>
          <Button variant="primary">
            <PlusIcon className="w-5 h-5 mr-2" />
            Create Database
          </Button>
        </div>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Database</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Users</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              <tr>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center">
                    <CircleStackIcon className="w-5 h-5 text-gray-400 mr-3" />
                    <span className="font-medium text-gray-900">user_wordpress</span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">24.5 MB</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">1 user</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-primary-600 hover:text-primary-800 mr-3">phpMyAdmin</button>
                  <button className="text-gray-600 hover:text-gray-800 mr-3">Users</button>
                  <button className="text-red-600 hover:text-red-800">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </CardBody>
      </Card>
    </div>
  )
}
