import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { domainsApi, Domain, Subdomain } from '../../api'
import toast from 'react-hot-toast'
import {
  PlusIcon,
  GlobeAltIcon,
  TrashIcon,
  PencilIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  LockClosedIcon,
  FolderIcon,
} from '@heroicons/react/24/outline'

export default function Domains() {
  const [domains, setDomains] = useState<Domain[]>([])
  const [loading, setLoading] = useState(true)
  const [showAddModal, setShowAddModal] = useState(false)
  const [showSubdomainModal, setShowSubdomainModal] = useState(false)
  const [selectedDomain, setSelectedDomain] = useState<Domain | null>(null)
  const [expandedDomains, setExpandedDomains] = useState<Set<number>>(new Set())
  const [actionLoading, setActionLoading] = useState<number | null>(null)

  // Form state
  const [newDomain, setNewDomain] = useState({ name: '', document_root: '' })
  const [newSubdomain, setNewSubdomain] = useState({ name: '', document_root: '' })

  useEffect(() => {
    loadDomains()
  }, [])

  const loadDomains = async () => {
    try {
      setLoading(true)
      const data = await domainsApi.list()
      setDomains(data)
    } catch (error) {
      toast.error('Failed to load domains')
    } finally {
      setLoading(false)
    }
  }

  const handleAddDomain = async () => {
    if (!newDomain.name) {
      toast.error('Please enter a domain name')
      return
    }

    try {
      setActionLoading(-1)
      await domainsApi.create(newDomain)
      toast.success('Domain added successfully')
      setShowAddModal(false)
      setNewDomain({ name: '', document_root: '' })
      loadDomains()
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to add domain')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDeleteDomain = async (domain: Domain) => {
    if (domain.is_main) {
      toast.error('Cannot delete main domain')
      return
    }

    if (!confirm(`Are you sure you want to delete "${domain.name}"? This action cannot be undone.`)) {
      return
    }

    try {
      setActionLoading(domain.id)
      await domainsApi.delete(domain.id)
      toast.success('Domain deleted successfully')
      loadDomains()
    } catch (error) {
      toast.error('Failed to delete domain')
    } finally {
      setActionLoading(null)
    }
  }

  const handleAddSubdomain = async () => {
    if (!selectedDomain || !newSubdomain.name) {
      toast.error('Please enter a subdomain name')
      return
    }

    try {
      setActionLoading(-2)
      await domainsApi.createSubdomain(selectedDomain.id, newSubdomain)
      toast.success('Subdomain created successfully')
      setShowSubdomainModal(false)
      setNewSubdomain({ name: '', document_root: '' })
      loadDomains()
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to create subdomain')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDeleteSubdomain = async (domain: Domain, subdomain: Subdomain) => {
    if (!confirm(`Are you sure you want to delete "${subdomain.name}.${domain.name}"?`)) {
      return
    }

    try {
      setActionLoading(subdomain.id + 10000) // Use offset to avoid collision
      await domainsApi.deleteSubdomain(domain.id, subdomain.id)
      toast.success('Subdomain deleted successfully')
      loadDomains()
    } catch (error) {
      toast.error('Failed to delete subdomain')
    } finally {
      setActionLoading(null)
    }
  }

  const toggleExpanded = (domainId: number) => {
    const newExpanded = new Set(expandedDomains)
    if (newExpanded.has(domainId)) {
      newExpanded.delete(domainId)
    } else {
      newExpanded.add(domainId)
    }
    setExpandedDomains(newExpanded)
  }

  const openSubdomainModal = (domain: Domain) => {
    setSelectedDomain(domain)
    setShowSubdomainModal(true)
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
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
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-primary-600">{domains.length}</div>
            <div className="text-sm text-gray-500">Total Domains</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-green-600">
              {domains.reduce((sum, d) => sum + (d.subdomains?.length || 0), 0)}
            </div>
            <div className="text-sm text-gray-500">Subdomains</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-blue-600">
              {domains.filter(d => d.ssl_certificate).length}
            </div>
            <div className="text-sm text-gray-500">SSL Certificates</div>
          </CardBody>
        </Card>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document Root</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SSL</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {domains.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                    No domains found. Add your first domain to get started.
                  </td>
                </tr>
              ) : (
                domains.map((domain) => (
                  <>
                    <tr key={domain.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          {domain.subdomains && domain.subdomains.length > 0 ? (
                            <button
                              onClick={() => toggleExpanded(domain.id)}
                              className="mr-2 text-gray-400 hover:text-gray-600"
                            >
                              {expandedDomains.has(domain.id) ? (
                                <ChevronDownIcon className="w-4 h-4" />
                              ) : (
                                <ChevronRightIcon className="w-4 h-4" />
                              )}
                            </button>
                          ) : (
                            <span className="w-6" />
                          )}
                          <GlobeAltIcon className="w-5 h-5 text-gray-400 mr-3" />
                          <a
                            href={`https://${domain.name}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-medium text-primary-600 hover:text-primary-800"
                          >
                            {domain.name}
                          </a>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                          domain.is_main
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-gray-100 text-gray-800'
                        }`}>
                          {domain.is_main ? 'Main' : 'Addon'}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center text-sm text-gray-500">
                          <FolderIcon className="w-4 h-4 mr-1" />
                          {domain.document_root}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {domain.ssl_certificate ? (
                          <div className="flex items-center">
                            <LockClosedIcon className="w-4 h-4 text-green-500 mr-1" />
                            <span className="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                              Active
                            </span>
                          </div>
                        ) : (
                          <span className="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">
                            None
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        <div className="flex space-x-2">
                          <button
                            onClick={() => openSubdomainModal(domain)}
                            className="text-primary-600 hover:text-primary-800"
                            title="Add Subdomain"
                          >
                            <PlusIcon className="w-4 h-4" />
                          </button>
                          <button
                            className="text-gray-600 hover:text-gray-800"
                            title="Edit"
                          >
                            <PencilIcon className="w-4 h-4" />
                          </button>
                          {!domain.is_main && (
                            <button
                              onClick={() => handleDeleteDomain(domain)}
                              className="text-red-600 hover:text-red-800 disabled:opacity-50"
                              disabled={actionLoading === domain.id}
                              title="Delete"
                            >
                              <TrashIcon className="w-4 h-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                    {/* Subdomains */}
                    {expandedDomains.has(domain.id) && domain.subdomains?.map((subdomain) => (
                      <tr key={`sub-${subdomain.id}`} className="bg-gray-50 hover:bg-gray-100">
                        <td className="px-6 py-3 whitespace-nowrap">
                          <div className="flex items-center pl-10">
                            <span className="text-gray-400 mr-2">└</span>
                            <GlobeAltIcon className="w-4 h-4 text-gray-400 mr-2" />
                            <a
                              href={`https://${subdomain.name}.${domain.name}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-sm text-primary-600 hover:text-primary-800"
                            >
                              {subdomain.name}.{domain.name}
                            </a>
                          </div>
                        </td>
                        <td className="px-6 py-3 whitespace-nowrap">
                          <span className="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                            Subdomain
                          </span>
                        </td>
                        <td className="px-6 py-3 whitespace-nowrap">
                          <div className="flex items-center text-sm text-gray-500">
                            <FolderIcon className="w-4 h-4 mr-1" />
                            {subdomain.document_root}
                          </div>
                        </td>
                        <td className="px-6 py-3 whitespace-nowrap">
                          <span className="text-sm text-gray-500">—</span>
                        </td>
                        <td className="px-6 py-3 whitespace-nowrap text-sm">
                          <button
                            onClick={() => handleDeleteSubdomain(domain, subdomain)}
                            className="text-red-600 hover:text-red-800 disabled:opacity-50"
                            disabled={actionLoading === subdomain.id + 10000}
                            title="Delete"
                          >
                            <TrashIcon className="w-4 h-4" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </>
                ))
              )}
            </tbody>
          </table>
        </CardBody>
      </Card>

      {/* Add Domain Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Add New Domain</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Domain Name *</label>
                <input
                  type="text"
                  className="input"
                  placeholder="example.com"
                  value={newDomain.name}
                  onChange={(e) => setNewDomain({ ...newDomain, name: e.target.value.toLowerCase() })}
                />
              </div>
              <div>
                <label className="label">Document Root (optional)</label>
                <input
                  type="text"
                  className="input"
                  placeholder="/public_html/example.com"
                  value={newDomain.document_root}
                  onChange={(e) => setNewDomain({ ...newDomain, document_root: e.target.value })}
                />
                <p className="text-xs text-gray-500 mt-1">Leave empty to use default path</p>
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowAddModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleAddDomain}
                disabled={actionLoading === -1}
              >
                {actionLoading === -1 ? 'Adding...' : 'Add Domain'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Add Subdomain Modal */}
      {showSubdomainModal && selectedDomain && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                Add Subdomain to {selectedDomain.name}
              </h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Subdomain Name *</label>
                <div className="flex">
                  <input
                    type="text"
                    className="input rounded-r-none"
                    placeholder="blog"
                    value={newSubdomain.name}
                    onChange={(e) => setNewSubdomain({ ...newSubdomain, name: e.target.value.toLowerCase() })}
                  />
                  <span className="px-3 py-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-gray-600">
                    .{selectedDomain.name}
                  </span>
                </div>
              </div>
              <div>
                <label className="label">Document Root (optional)</label>
                <input
                  type="text"
                  className="input"
                  placeholder={`/public_html/${newSubdomain.name || 'subdomain'}.${selectedDomain.name}`}
                  value={newSubdomain.document_root}
                  onChange={(e) => setNewSubdomain({ ...newSubdomain, document_root: e.target.value })}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => {
                setShowSubdomainModal(false)
                setSelectedDomain(null)
              }}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleAddSubdomain}
                disabled={actionLoading === -2}
              >
                {actionLoading === -2 ? 'Creating...' : 'Create Subdomain'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
