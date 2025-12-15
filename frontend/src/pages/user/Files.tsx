import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { FolderIcon, DocumentIcon, ArrowUpTrayIcon } from '@heroicons/react/24/outline'

export default function Files() {
  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">File Manager</h1>
          <p className="text-gray-500">Browse and manage your files</p>
        </div>
        <div className="flex space-x-3">
          <Button variant="secondary">New Folder</Button>
          <Button variant="primary">
            <ArrowUpTrayIcon className="w-5 h-5 mr-2" />
            Upload
          </Button>
        </div>
      </div>

      {/* Breadcrumb */}
      <div className="flex items-center space-x-2 text-sm">
        <span className="text-primary-600 cursor-pointer hover:underline">Home</span>
        <span className="text-gray-400">/</span>
        <span className="text-gray-600">public_html</span>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modified</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permissions</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              <tr className="hover:bg-gray-50 cursor-pointer">
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center">
                    <FolderIcon className="w-5 h-5 text-yellow-500 mr-3" />
                    <span className="font-medium text-gray-900">wp-content</span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Jan 15, 2024</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">drwxr-xr-x</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-gray-600 hover:text-gray-800 mr-3">Rename</button>
                  <button className="text-red-600 hover:text-red-800">Delete</button>
                </td>
              </tr>
              <tr className="hover:bg-gray-50 cursor-pointer">
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center">
                    <DocumentIcon className="w-5 h-5 text-gray-400 mr-3" />
                    <span className="font-medium text-gray-900">index.php</span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">418 B</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Jan 15, 2024</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-rw-r--r--</td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-primary-600 hover:text-primary-800 mr-3">Edit</button>
                  <button className="text-gray-600 hover:text-gray-800 mr-3">Download</button>
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
