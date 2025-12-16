import { useState } from 'react'
import { Card, CardHeader, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import Modal, { ModalBody, ModalFooter } from '../../components/common/Modal'
import Input from '../../components/common/Input'
import Badge from '../../components/common/Badge'
import EmptyState from '../../components/common/EmptyState'
import ConfirmDialog from '../../components/common/ConfirmDialog'
import Tooltip from '../../components/common/Tooltip'
import toast from 'react-hot-toast'
import {
  PlusIcon,
  GlobeAltIcon,
  TrashIcon,
  LockClosedIcon,
  ArrowTopRightOnSquareIcon,
  FolderIcon,
  ServerStackIcon,
  Cog6ToothIcon,
} from '@heroicons/react/24/outline'

interface Domain {
  id: number
  name: string
  type: 'main' | 'addon' | 'subdomain' | 'parked'
  documentRoot: string
  ssl: 'active' | 'inactive' | 'pending'
  createdAt: string
}

// Sample data
const sampleDomains: Domain[] = [
  {
    id: 1,
    name: 'example.com',
    type: 'main',
    documentRoot: '/public_html',
    ssl: 'active',
    createdAt: '2024-01-01',
  },
  {
    id: 2,
    name: 'blog.example.com',
    type: 'subdomain',
    documentRoot: '/public_html/blog',
    ssl: 'active',
    createdAt: '2024-01-05',
  },
  {
    id: 3,
    name: 'shop.example.com',
    type: 'subdomain',
    documentRoot: '/public_html/shop',
    ssl: 'pending',
    createdAt: '2024-01-10',
  },
]

const typeConfig = {
  main: { label: 'Main', variant: 'primary' as const },
  addon: { label: 'Addon', variant: 'info' as const },
  subdomain: { label: 'Subdomain', variant: 'default' as const },
  parked: { label: 'Parked', variant: 'warning' as const },
}

const sslConfig = {
  active: { label: 'Active', variant: 'success' as const },
  inactive: { label: 'Inactive', variant: 'danger' as const },
  pending: { label: 'Pending', variant: 'warning' as const },
}

export default function Domains() {
  const [domains] = useState<Domain[]>(sampleDomains)
  const [showAddModal, setShowAddModal] = useState(false)
  const [showDnsModal, setShowDnsModal] = useState<Domain | null>(null)
  const [deleteConfirm, setDeleteConfirm] = useState<Domain | null>(null)
  const [addType, setAddType] = useState<'domain' | 'subdomain'>('domain')
  const [formData, setFormData] = useState({
    domain: '',
    subdomain: '',
    documentRoot: '',
  })
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})

  const mainDomains = domains.filter(d => d.type === 'main' || d.type === 'addon')
  const subdomains = domains.filter(d => d.type === 'subdomain')

  const handleAddDomain = () => {
    const errors: Record<string, string> = {}

    if (addType === 'domain') {
      if (!formData.domain || !/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/.test(formData.domain)) {
        errors.domain = 'Please enter a valid domain name'
      }
    } else {
      if (!formData.subdomain || !/^[a-z0-9]+(-[a-z0-9]+)*$/.test(formData.subdomain)) {
        errors.subdomain = 'Please enter a valid subdomain'
      }
    }

    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    toast.success(`${addType === 'domain' ? 'Domain' : 'Subdomain'} added successfully`)
    setShowAddModal(false)
    setFormData({ domain: '', subdomain: '', documentRoot: '' })
  }

  const handleDelete = () => {
    if (!deleteConfirm) return
    toast.success('Domain deleted successfully')
    setDeleteConfirm(null)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Domains</h1>
          <p className="text-gray-500">Manage your domains and subdomains</p>
        </div>
        <Button variant="primary" onClick={() => setShowAddModal(true)}>
          <PlusIcon className="w-5 h-5 mr-2" />
          Add Domain
        </Button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <Card>
          <CardBody className="flex items-center gap-3 p-4">
            <div className="p-2 bg-blue-50 rounded-lg">
              <GlobeAltIcon className="w-5 h-5 text-blue-600" />
            </div>
            <div>
              <p className="text-xl font-bold text-gray-900">{domains.length}</p>
              <p className="text-xs text-gray-500">Total Domains</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="flex items-center gap-3 p-4">
            <div className="p-2 bg-green-50 rounded-lg">
              <LockClosedIcon className="w-5 h-5 text-green-600" />
            </div>
            <div>
              <p className="text-xl font-bold text-gray-900">
                {domains.filter(d => d.ssl === 'active').length}
              </p>
              <p className="text-xs text-gray-500">SSL Active</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="flex items-center gap-3 p-4">
            <div className="p-2 bg-purple-50 rounded-lg">
              <ServerStackIcon className="w-5 h-5 text-purple-600" />
            </div>
            <div>
              <p className="text-xl font-bold text-gray-900">{subdomains.length}</p>
              <p className="text-xs text-gray-500">Subdomains</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="flex items-center gap-3 p-4">
            <div className="p-2 bg-orange-50 rounded-lg">
              <FolderIcon className="w-5 h-5 text-orange-600" />
            </div>
            <div>
              <p className="text-xl font-bold text-gray-900">{mainDomains.length}</p>
              <p className="text-xs text-gray-500">Addon Domains</p>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Domains Table */}
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-gray-900">All Domains</h2>
        </CardHeader>
        <CardBody className="p-0">
          {domains.length === 0 ? (
            <EmptyState
              title="No domains yet"
              description="Add your first domain to get started with your website."
              action={{
                label: 'Add Domain',
                onClick: () => setShowAddModal(true),
              }}
            />
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="table-header">Domain</th>
                    <th className="table-header">Type</th>
                    <th className="table-header">Document Root</th>
                    <th className="table-header">SSL</th>
                    <th className="table-header text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {domains.map((domain) => (
                    <tr key={domain.id} className="table-row">
                      <td className="table-cell">
                        <div className="flex items-center gap-3">
                          <div className="p-2 bg-gray-100 rounded-lg">
                            <GlobeAltIcon className="w-5 h-5 text-gray-500" />
                          </div>
                          <div>
                            <a
                              href={`https://${domain.name}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="font-medium text-gray-900 hover:text-primary-600 flex items-center gap-1"
                            >
                              {domain.name}
                              <ArrowTopRightOnSquareIcon className="w-4 h-4 text-gray-400" />
                            </a>
                          </div>
                        </div>
                      </td>
                      <td className="table-cell">
                        <Badge variant={typeConfig[domain.type].variant}>
                          {typeConfig[domain.type].label}
                        </Badge>
                      </td>
                      <td className="table-cell">
                        <code className="text-sm text-gray-600 bg-gray-100 px-2 py-1 rounded">
                          {domain.documentRoot}
                        </code>
                      </td>
                      <td className="table-cell">
                        <Badge variant={sslConfig[domain.ssl].variant} dot>
                          {sslConfig[domain.ssl].label}
                        </Badge>
                      </td>
                      <td className="table-cell">
                        <div className="flex items-center justify-end gap-1">
                          <Tooltip content="Manage domain">
                            <button className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                              <Cog6ToothIcon className="w-4 h-4" />
                            </button>
                          </Tooltip>
                          <Tooltip content="DNS settings">
                            <button
                              onClick={() => setShowDnsModal(domain)}
                              className="p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors"
                            >
                              <ServerStackIcon className="w-4 h-4" />
                            </button>
                          </Tooltip>
                          {domain.type !== 'main' && (
                            <Tooltip content="Delete domain">
                              <button
                                onClick={() => setDeleteConfirm(domain)}
                                className="p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                              >
                                <TrashIcon className="w-4 h-4" />
                              </button>
                            </Tooltip>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Add Domain Modal */}
      <Modal
        isOpen={showAddModal}
        onClose={() => setShowAddModal(false)}
        title="Add Domain"
        description="Add a new domain or subdomain to your account"
      >
        <ModalBody className="space-y-4">
          {/* Type selector */}
          <div className="flex gap-2">
            <button
              onClick={() => setAddType('domain')}
              className={`flex-1 p-3 rounded-lg border-2 transition-colors ${
                addType === 'domain'
                  ? 'border-primary-500 bg-primary-50 text-primary-700'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <GlobeAltIcon className="w-6 h-6 mx-auto mb-1" />
              <p className="font-medium">Addon Domain</p>
              <p className="text-xs text-gray-500">Add a new domain</p>
            </button>
            <button
              onClick={() => setAddType('subdomain')}
              className={`flex-1 p-3 rounded-lg border-2 transition-colors ${
                addType === 'subdomain'
                  ? 'border-primary-500 bg-primary-50 text-primary-700'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <ServerStackIcon className="w-6 h-6 mx-auto mb-1" />
              <p className="font-medium">Subdomain</p>
              <p className="text-xs text-gray-500">Create a subdomain</p>
            </button>
          </div>

          {addType === 'domain' ? (
            <Input
              label="Domain Name"
              placeholder="newdomain.com"
              value={formData.domain}
              onChange={(e) => setFormData({ ...formData, domain: e.target.value.toLowerCase() })}
              error={formErrors.domain}
              hint="Enter the domain name without www"
            />
          ) : (
            <div className="flex gap-2 items-end">
              <Input
                label="Subdomain"
                placeholder="blog"
                value={formData.subdomain}
                onChange={(e) => setFormData({ ...formData, subdomain: e.target.value.toLowerCase() })}
                error={formErrors.subdomain}
                className="flex-1"
              />
              <div className="pb-2 text-gray-500">.example.com</div>
            </div>
          )}

          <Input
            label="Document Root (optional)"
            placeholder={addType === 'domain' ? '/public_html/newdomain' : '/public_html/blog'}
            value={formData.documentRoot}
            onChange={(e) => setFormData({ ...formData, documentRoot: e.target.value })}
            hint="Leave empty to use default location"
          />
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowAddModal(false)}>
            Cancel
          </Button>
          <Button variant="primary" onClick={handleAddDomain}>
            Add {addType === 'domain' ? 'Domain' : 'Subdomain'}
          </Button>
        </ModalFooter>
      </Modal>

      {/* DNS Modal */}
      <Modal
        isOpen={!!showDnsModal}
        onClose={() => setShowDnsModal(null)}
        title={`DNS Settings - ${showDnsModal?.name}`}
        description="Manage DNS records for this domain"
        size="lg"
      >
        <ModalBody>
          <div className="space-y-4">
            <div className="bg-gray-50 rounded-lg p-4">
              <h4 className="font-medium text-gray-900 mb-2">Current DNS Records</h4>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between items-center p-2 bg-white rounded border">
                  <div>
                    <span className="font-mono text-gray-600">A</span>
                    <span className="ml-2 text-gray-900">@</span>
                  </div>
                  <span className="text-gray-500">192.168.1.100</span>
                </div>
                <div className="flex justify-between items-center p-2 bg-white rounded border">
                  <div>
                    <span className="font-mono text-gray-600">CNAME</span>
                    <span className="ml-2 text-gray-900">www</span>
                  </div>
                  <span className="text-gray-500">{showDnsModal?.name}</span>
                </div>
                <div className="flex justify-between items-center p-2 bg-white rounded border">
                  <div>
                    <span className="font-mono text-gray-600">MX</span>
                    <span className="ml-2 text-gray-900">@</span>
                  </div>
                  <span className="text-gray-500">mail.{showDnsModal?.name}</span>
                </div>
              </div>
            </div>
            <p className="text-sm text-gray-500">
              For advanced DNS management, please use the DNS Zone Editor from the control panel.
            </p>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowDnsModal(null)}>
            Close
          </Button>
          <Button variant="primary">
            Open DNS Editor
          </Button>
        </ModalFooter>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={!!deleteConfirm}
        onClose={() => setDeleteConfirm(null)}
        onConfirm={handleDelete}
        title="Delete Domain"
        message={`Are you sure you want to delete "${deleteConfirm?.name}"? All files in the document root will remain but the domain will no longer be accessible.`}
        confirmLabel="Delete Domain"
        variant="danger"
      />
    </div>
  )
}
