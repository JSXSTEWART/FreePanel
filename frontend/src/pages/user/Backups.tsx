import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { ArchiveBoxIcon, ArrowDownTrayIcon } from '@heroicons/react/24/outline'

export default function Backups() {
  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Backups</h1>
          <p className="text-gray-500">Create and manage account backups</p>
        </div>
        <Button variant="primary">
          <ArchiveBoxIcon className="w-5 h-5 mr-2" />
          Create Backup
        </Button>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Backup</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              <tr>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center">
                    <ArchiveBoxIcon className="w-5 h-5 text-gray-400 mr-3" />
                    <span className="font-medium text-gray-900">backup_20240115.tar.gz</span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <span className="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Full</span>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">1.2 GB</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Jan 15, 2024</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-primary-600 hover:text-primary-800 mr-3 inline-flex items-center">
                    <ArrowDownTrayIcon className="w-4 h-4 mr-1" />
                    Download
                  </button>
                  <button className="text-gray-600 hover:text-gray-800 mr-3">Restore</button>
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
