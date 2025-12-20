import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import Modal from '../../components/common/Modal'
import {
  LockClosedIcon,
  ShieldCheckIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  ArrowPathIcon,
  TrashIcon,
} from '@heroicons/react/24/outline'
import { sslApi, SslCertificate, domainsApi } from '../../api'

export default function Ssl() {
  const [certificates, setCertificates] = useState<SslCertificate[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Modals
  const [showInstallModal, setShowInstallModal] = useState(false)
  const [showLetsEncryptModal, setShowLetsEncryptModal] = useState(false)
  const [showDeleteModal, setShowDeleteModal] = useState(false)
  const [selectedCert, setSelectedCert] = useState<SslCertificate | null>(null)

  // Form states
  const [domains, setDomains] = useState<string[]>([])
  const [installForm, setInstallForm] = useState({
    domain: '',
    certificate: '',
    private_key: '',
    ca_bundle: '',
  })
  const [letsEncryptDomain, setLetsEncryptDomain] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const fetchCertificates = async () => {
    try {
      setLoading(true)
      setError(null)
      const data = await sslApi.list()
      setCertificates(data)
    } catch (err) {
      setError('Failed to load SSL certificates')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }

  const fetchDomains = async () => {
    try {
      const data = await domainsApi.list()
      setDomains(data.map((d) => d.domain))
    } catch (err) {
      console.error('Failed to load domains:', err)
    }
  }

  useEffect(() => {
    fetchCertificates()
    fetchDomains()
  }, [])

  const handleInstall = async (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    try {
      await sslApi.install(installForm.domain, {
        certificate: installForm.certificate,
        private_key: installForm.private_key,
        ca_bundle: installForm.ca_bundle || undefined,
      })
      setShowInstallModal(false)
      setInstallForm({ domain: '', certificate: '', private_key: '', ca_bundle: '' })
      fetchCertificates()
    } catch (err) {
      console.error('Failed to install certificate:', err)
      alert('Failed to install certificate')
    } finally {
      setSubmitting(false)
    }
  }

  const handleLetsEncrypt = async (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    try {
      await sslApi.requestLetsEncrypt(letsEncryptDomain)
      setShowLetsEncryptModal(false)
      setLetsEncryptDomain('')
      fetchCertificates()
    } catch (err) {
      console.error('Failed to request Let\'s Encrypt certificate:', err)
      alert('Failed to request Let\'s Encrypt certificate')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!selectedCert) return
    setSubmitting(true)
    try {
      await sslApi.delete(selectedCert.id)
      setShowDeleteModal(false)
      setSelectedCert(null)
      fetchCertificates()
    } catch (err) {
      console.error('Failed to delete certificate:', err)
      alert('Failed to delete certificate')
    } finally {
      setSubmitting(false)
    }
  }

  const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  }

  const getDaysUntilExpiry = (dateStr: string) => {
    const now = new Date()
    const expiry = new Date(dateStr)
    const diff = expiry.getTime() - now.getTime()
    return Math.ceil(diff / (1000 * 60 * 60 * 24))
  }

  const getStatusBadge = (cert: SslCertificate) => {
    if (!cert.is_valid) {
      return (
        <span className="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
          Invalid
        </span>
      )
    }
    if (cert.is_expiring_soon) {
      return (
        <span className="px-2 py-1 text-xs font-medium bg-amber-100 text-amber-800 rounded-full">
          Expiring Soon
        </span>
      )
    }
    return (
      <span className="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
        Active
      </span>
    )
  }

  const getTypeBadge = (type: string) => {
    const badges: Record<string, string> = {
      lets_encrypt: 'bg-blue-100 text-blue-800',
      custom: 'bg-purple-100 text-purple-800',
      self_signed: 'bg-gray-100 text-gray-800',
    }
    const labels: Record<string, string> = {
      lets_encrypt: "Let's Encrypt",
      custom: 'Custom',
      self_signed: 'Self-Signed',
    }
    return (
      <span className={`px-2 py-1 text-xs font-medium rounded-full ${badges[type] || badges.custom}`}>
        {labels[type] || type}
      </span>
    )
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <ExclamationTriangleIcon className="w-12 h-12 text-red-500 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-gray-900 mb-2">Error loading certificates</h3>
        <p className="text-gray-500 mb-4">{error}</p>
        <Button variant="primary" onClick={fetchCertificates}>
          Try Again
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">SSL/TLS Certificates</h1>
          <p className="text-gray-500">Manage SSL certificates for your domains</p>
        </div>
        <div className="flex space-x-3">
          <Button variant="secondary" onClick={() => setShowInstallModal(true)}>
            <PlusIcon className="w-5 h-5 mr-2" />
            Install Certificate
          </Button>
          <Button variant="primary" onClick={() => setShowLetsEncryptModal(true)}>
            <ShieldCheckIcon className="w-5 h-5 mr-2" />
            Get Let's Encrypt
          </Button>
        </div>
      </div>

      <Card>
        <CardBody className="p-0">
          {certificates.length === 0 ? (
            <div className="text-center py-12">
              <LockClosedIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">No SSL certificates</h3>
              <p className="text-gray-500 mb-4">
                Secure your domains with SSL certificates
              </p>
              <Button variant="primary" onClick={() => setShowLetsEncryptModal(true)}>
                <ShieldCheckIcon className="w-5 h-5 mr-2" />
                Get Free SSL with Let's Encrypt
              </Button>
            </div>
          ) : (
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
                {certificates.map((cert) => (
                  <tr key={cert.id}>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <LockClosedIcon
                          className={`w-5 h-5 mr-3 ${
                            cert.is_valid ? 'text-green-500' : 'text-red-500'
                          }`}
                        />
                        <span className="font-medium text-gray-900">{cert.domain}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {getTypeBadge(cert.type)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(cert.valid_to)} ({getDaysUntilExpiry(cert.valid_to)} days)
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {getStatusBadge(cert)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      {cert.type === 'lets_encrypt' && (
                        <button
                          onClick={async () => {
                            try {
                              await sslApi.requestLetsEncrypt(cert.domain)
                              fetchCertificates()
                            } catch {
                              alert('Failed to renew certificate')
                            }
                          }}
                          className="text-primary-600 hover:text-primary-800 mr-3"
                        >
                          <ArrowPathIcon className="w-4 h-4 inline mr-1" />
                          Renew
                        </button>
                      )}
                      <button
                        onClick={() => {
                          setSelectedCert(cert)
                          setShowDeleteModal(true)
                        }}
                        className="text-red-600 hover:text-red-800"
                      >
                        <TrashIcon className="w-4 h-4 inline mr-1" />
                        Delete
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardBody>
      </Card>

      {/* Install Certificate Modal */}
      <Modal
        isOpen={showInstallModal}
        onClose={() => setShowInstallModal(false)}
        title="Install SSL Certificate"
      >
        <form onSubmit={handleInstall} className="space-y-4">
          <div>
            <label className="label">Domain</label>
            <select
              className="input"
              value={installForm.domain}
              onChange={(e) => setInstallForm({ ...installForm, domain: e.target.value })}
              required
            >
              <option value="">Select domain</option>
              {domains.map((domain) => (
                <option key={domain} value={domain}>
                  {domain}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="label">Certificate (PEM)</label>
            <textarea
              className="input font-mono text-sm"
              rows={4}
              placeholder="-----BEGIN CERTIFICATE-----"
              value={installForm.certificate}
              onChange={(e) => setInstallForm({ ...installForm, certificate: e.target.value })}
              required
            />
          </div>
          <div>
            <label className="label">Private Key (PEM)</label>
            <textarea
              className="input font-mono text-sm"
              rows={4}
              placeholder="-----BEGIN PRIVATE KEY-----"
              value={installForm.private_key}
              onChange={(e) => setInstallForm({ ...installForm, private_key: e.target.value })}
              required
            />
          </div>
          <div>
            <label className="label">CA Bundle (Optional)</label>
            <textarea
              className="input font-mono text-sm"
              rows={4}
              placeholder="-----BEGIN CERTIFICATE-----"
              value={installForm.ca_bundle}
              onChange={(e) => setInstallForm({ ...installForm, ca_bundle: e.target.value })}
            />
          </div>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowInstallModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" disabled={submitting}>
              {submitting ? 'Installing...' : 'Install Certificate'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Let's Encrypt Modal */}
      <Modal
        isOpen={showLetsEncryptModal}
        onClose={() => setShowLetsEncryptModal(false)}
        title="Get Free SSL with Let's Encrypt"
      >
        <form onSubmit={handleLetsEncrypt} className="space-y-4">
          <p className="text-gray-600 text-sm">
            Let's Encrypt provides free SSL certificates that auto-renew every 90 days.
          </p>
          <div>
            <label className="label">Domain</label>
            <select
              className="input"
              value={letsEncryptDomain}
              onChange={(e) => setLetsEncryptDomain(e.target.value)}
              required
            >
              <option value="">Select domain</option>
              {domains.map((domain) => (
                <option key={domain} value={domain}>
                  {domain}
                </option>
              ))}
            </select>
          </div>
          <div className="flex justify-end space-x-3">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setShowLetsEncryptModal(false)}
            >
              Cancel
            </Button>
            <Button type="submit" variant="primary" disabled={submitting}>
              {submitting ? 'Requesting...' : 'Request Certificate'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteModal}
        onClose={() => setShowDeleteModal(false)}
        title="Delete Certificate"
      >
        <div className="space-y-4">
          <p className="text-gray-600">
            Are you sure you want to delete the SSL certificate for{' '}
            <strong>{selectedCert?.domain}</strong>? This action cannot be undone.
          </p>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowDeleteModal(false)}>
              Cancel
            </Button>
            <Button type="button" variant="danger" onClick={handleDelete} disabled={submitting}>
              {submitting ? 'Deleting...' : 'Delete Certificate'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
