import { Card, CardBody } from "../../components/common/Card";
import Button from "../../components/common/Button";
import { LockClosedIcon, ShieldCheckIcon } from "@heroicons/react/24/outline";

export default function Ssl() {
  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            SSL/TLS Certificates
          </h1>
          <p className="text-gray-500">
            Manage SSL certificates for your domains
          </p>
        </div>
        <div className="flex space-x-3">
          <Button variant="secondary">Install Certificate</Button>
          <Button variant="primary">
            <ShieldCheckIcon className="w-5 h-5 mr-2" />
            Get Let's Encrypt
          </Button>
        </div>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Domain
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Type
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Expires
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              <tr>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center">
                    <LockClosedIcon className="w-5 h-5 text-green-500 mr-3" />
                    <span className="font-medium text-gray-900">
                      example.com
                    </span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <span className="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                    Let's Encrypt
                  </span>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  Mar 15, 2024 (60 days)
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <span className="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                    Active
                  </span>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-primary-600 hover:text-primary-800 mr-3">
                    Renew
                  </button>
                  <button className="text-gray-600 hover:text-gray-800">
                    View
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </CardBody>
      </Card>
    </div>
  );
}
