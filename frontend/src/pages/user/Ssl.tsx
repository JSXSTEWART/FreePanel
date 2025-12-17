import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { sslApi, domainsApi, SslCertificate, Domain } from '../../api'
import toast from 'react-hot-toast'
import {
  LockClosedIcon,
  LockOpenIcon,
  PlusIcon,
  TrashIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/react/24/outline'

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function getDaysUntilExpiry(dateStr: string): number {
  const expiry = new Date(dateStr)
  const now = new Date()
  const diffTime = expiry.getTime() - now.getTime()
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
}

export default function SSL() {
  const [certificates, setCertificates] = useState<SslCertificate[]>([])
  const [domains, setDomains] = useState<Domain[]>([])
  const [loading, setLoading] = useState(true)
  const [showInstallModal, setShowInstallModal] = useState(false)
  const [actionLoading, setActionLoading] = useState<number | null>(null)

  // Form state
  const [selectedDomain, setSelectedDomain] = useState('')
  const [sslType, setSslType] = useState<'lets_encrypt' | 'custom'>('lets_encrypt')
  const [customCert, setCustomCert] = useState({ certificate: '', private_key: '', ca_bundle: '' })

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      setLoading(true)
      const [certsData, domainsData] = await Promise.all([
        sslApi.list(),
        domainsApi.list(),
      ])
      setCertificates(certsData)
      setDomains(domainsData)
      if (domainsData.length > 0 && !selectedDomain) {
        setSelectedDomain(domainsData[0].name)
      }
    } catch (error) {
      toast.error('Failed to load SSL certificates')
    } finally {
      setLoading(false)
    }
  }

  const handleInstallLetsEncrypt = async () => {
    if (!selectedDomain) {
      toast.error('Please select a domain')
      return
    }

    try {
      setActionLoading(-1)
      await sslApi.requestLetsEncrypt(selectedDomain)
      toast.success('Let\'s Encrypt certificate installed successfully')
      setShowInstallModal(false)
      loadData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to install certificate')
    } finally {
      setActionLoading(null)
    }
  }

  const handleInstallCustom = async () => {
    if (!selectedDomain) {
      toast.error('Please select a domain')
      return
    }
    if (!customCert.certificate || !customCert.private_key) {
      toast.error('Please provide certificate and private key')
      return
    }

    try {
      setActionLoading(-1)
      await sslApi.install(selectedDomain, {
        certificate: customCert.certificate,
        private_key: customCert.private_key,
        ca_bundle: customCert.ca_bundle || undefined,
      })
      toast.success('SSL certificate installed successfully')
      setShowInstallModal(false)
      setCustomCert({ certificate: '', private_key: '', ca_bundle: '' })
      loadData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to install certificate')
    } finally {
      setActionLoading(null)
    }
  }

  const handleRenew = async (cert: SslCertificate) => {
    try {
      setActionLoading(cert.id)
      // Renew by requesting a new Let's Encrypt cert for the domain
      await sslApi.requestLetsEncrypt(cert.domain)
      toast.success('Certificate renewed successfully')
      loadData()
    } catch (error) {
      toast.error('Failed to renew certificate')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDelete = async (cert: SslCertificate) => {
    if (!confirm(`Are you sure you want to delete the SSL certificate for "${cert.domain}"?`)) {
      return
    }

    try {
      setActionLoading(cert.id)
      await sslApi.delete(cert.id)
      toast.success('Certificate deleted successfully')
      loadData()
    } catch (error) {
      toast.error('Failed to delete certificate')
    } finally {
      setActionLoading(null)
    }
  }

  const getStatusColor = (cert: SslCertificate): string => {
    if (!cert.is_valid) return 'bg-gray-100 text-gray-800'
    const days = getDaysUntilExpiry(cert.valid_to)
    if (days < 0) return 'bg-red-100 text-red-800'
    if (days < 30) return 'bg-yellow-100 text-yellow-800'
    return 'bg-green-100 text-green-800'
  }

  const domainsWithoutSSL = domains.filter(d =>
    !certificates.find(c => c.domain === d.name && c.is_valid)
  )

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
          <h1 className="text-2xl font-bold text-gray-900">SSL Certificates</h1>
          <p className="text-gray-500">Secure your domains with SSL certificates</p>
        </div>
        <Button variant="primary" onClick={() => setShowInstallModal(true)}>
          <PlusIcon className="w-5 h-5 mr-2" />
          Install Certificate
        </Button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-green-600">
              {certificates.filter(c => c.is_valid).length}
            </div>
            <div className="text-sm text-gray-500">Active Certificates</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-yellow-600">
              {certificates.filter(c => c.is_valid && c.is_expiring_soon).length}
            </div>
            <div className="text-sm text-gray-500">Expiring Soon</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-gray-600">
              {domainsWithoutSSL.length}
            </div>
            <div className="text-sm text-gray-500">Domains Without SSL</div>
          </CardBody>
        </Card>
      </div>

      {/* Domains Without SSL Alert */}
      {domainsWithoutSSL.length > 0 && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex">
            <ExclamationTriangleIcon className="w-5 h-5 text-yellow-500 mr-3 flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="text-sm font-medium text-yellow-800">Domains Without SSL</h3>
              <p className="text-sm text-yellow-700 mt-1">
                The following domains don't have SSL certificates:{' '}
                <span className="font-medium">{domainsWithoutSSL.map(d => d.name).join(', ')}</span>
              </p>
            </div>
          </div>
        </div>
      )}

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {certificates.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                    No SSL certificates found. Install one to secure your domain.
                  </td>
                </tr>
              ) : (
                certificates.map((cert) => {
                  const daysLeft = getDaysUntilExpiry(cert.valid_to)
                  return (
                    <tr key={cert.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          {cert.is_valid ? (
                            <LockClosedIcon className="w-5 h-5 text-green-500 mr-3" />
                          ) : (
                            <LockOpenIcon className="w-5 h-5 text-gray-400 mr-3" />
                          )}
                          <span className="font-medium text-gray-900">{cert.domain}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {cert.type === 'lets_encrypt' ? "Let's Encrypt" : cert.type === 'self_signed' ? 'Self-Signed' : 'Custom'}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(cert)}`}>
                          {cert.is_valid
                            ? daysLeft < 0
                              ? 'Expired'
                              : daysLeft < 30
                              ? `Expires in ${daysLeft} days`
                              : 'Active'
                            : 'Invalid'}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {formatDate(cert.valid_to)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm">
                        <div className="flex space-x-2">
                          {cert.type === 'lets_encrypt' && cert.is_valid && (
                            <button
                              onClick={() => handleRenew(cert)}
                              className="text-primary-600 hover:text-primary-800 disabled:opacity-50"
                              disabled={actionLoading === cert.id}
                              title="Renew"
                            >
                              <ArrowPathIcon className="w-4 h-4" />
                            </button>
                          )}
                          <button
                            onClick={() => handleDelete(cert)}
                            className="text-red-600 hover:text-red-800 disabled:opacity-50"
                            disabled={actionLoading === cert.id}
                            title="Delete"
                          >
                            <TrashIcon className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </CardBody>
      </Card>

      {/* Install SSL Modal */}
      {showInstallModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Install SSL Certificate</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Domain *</label>
                <select
                  className="input"
                  value={selectedDomain}
                  onChange={(e) => setSelectedDomain(e.target.value)}
                >
                  <option value="">Select a domain...</option>
                  {domains.map((domain) => (
                    <option key={domain.id} value={domain.name}>{domain.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="label">Certificate Type</label>
                <div className="grid grid-cols-2 gap-4 mt-2">
                  <button
                    type="button"
                    onClick={() => setSslType('lets_encrypt')}
                    className={`p-4 border rounded-lg text-left transition-colors ${
                      sslType === 'lets_encrypt'
                        ? 'border-primary-500 bg-primary-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <div className="flex items-center mb-2">
                      <CheckCircleIcon className="w-5 h-5 text-green-500 mr-2" />
                      <span className="font-medium">Let's Encrypt</span>
                    </div>
                    <p className="text-xs text-gray-500">Free, auto-renewing SSL certificate</p>
                  </button>
                  <button
                    type="button"
                    onClick={() => setSslType('custom')}
                    className={`p-4 border rounded-lg text-left transition-colors ${
                      sslType === 'custom'
                        ? 'border-primary-500 bg-primary-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <div className="flex items-center mb-2">
                      <LockClosedIcon className="w-5 h-5 text-blue-500 mr-2" />
                      <span className="font-medium">Custom Certificate</span>
                    </div>
                    <p className="text-xs text-gray-500">Upload your own SSL certificate</p>
                  </button>
                </div>
              </div>

              {sslType === 'custom' && (
                <>
                  <div>
                    <label className="label">Certificate (PEM) *</label>
                    <textarea
                      className="input font-mono text-xs"
                      rows={6}
                      placeholder="-----BEGIN CERTIFICATE-----"
                      value={customCert.certificate}
                      onChange={(e) => setCustomCert({ ...customCert, certificate: e.target.value })}
                    />
                  </div>
                  <div>
                    <label className="label">Private Key (PEM) *</label>
                    <textarea
                      className="input font-mono text-xs"
                      rows={6}
                      placeholder="-----BEGIN PRIVATE KEY-----"
                      value={customCert.private_key}
                      onChange={(e) => setCustomCert({ ...customCert, private_key: e.target.value })}
                    />
                  </div>
                  <div>
                    <label className="label">CA Bundle (optional)</label>
                    <textarea
                      className="input font-mono text-xs"
                      rows={4}
                      placeholder="-----BEGIN CERTIFICATE-----"
                      value={customCert.ca_bundle}
                      onChange={(e) => setCustomCert({ ...customCert, ca_bundle: e.target.value })}
                    />
                  </div>
                </>
              )}
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowInstallModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={sslType === 'lets_encrypt' ? handleInstallLetsEncrypt : handleInstallCustom}
                disabled={actionLoading === -1 || !selectedDomain}
              >
                {actionLoading === -1 ? 'Installing...' : 'Install Certificate'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
