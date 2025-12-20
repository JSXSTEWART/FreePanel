import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import Modal, { ModalBody, ModalFooter } from '../../components/common/Modal'
import Input from '../../components/common/Input'
import Badge from '../../components/common/Badge'
import EmptyState from '../../components/common/EmptyState'
import ConfirmDialog from '../../components/common/ConfirmDialog'
import toast from 'react-hot-toast'
import { emailApi, EmailAccount, EmailForwarder, EmailAutoresponder } from '../../api'
import { QuotaBar } from '../../components/plan'
import {
  PlusIcon,
  EnvelopeIcon,
  ArrowPathIcon,
  TrashIcon,
  ArrowsRightLeftIcon,
  ChatBubbleBottomCenterTextIcon,
  KeyIcon,
} from '@heroicons/react/24/outline'

type TabType = 'accounts' | 'forwarders' | 'autoresponders'

export default function Email() {
  const [activeTab, setActiveTab] = useState<TabType>('accounts')
  const [accounts, setAccounts] = useState<EmailAccount[]>([])
  const [forwarders, setForwarders] = useState<EmailForwarder[]>([])
  const [autoresponders, setAutoresponders] = useState<EmailAutoresponder[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showPasswordModal, setShowPasswordModal] = useState<EmailAccount | null>(null)
  const [deleteConfirm, setDeleteConfirm] = useState<{ type: TabType; item: EmailAccount | EmailForwarder | EmailAutoresponder } | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const [formData, setFormData] = useState({
    email: '',
    password: '',
    confirmPassword: '',
    quota: 1024,
    source: '',
    destination: '',
    subject: '',
    body: '',
  })
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})

  const fetchData = async () => {
    try {
      setLoading(true)
      const [accountsData, forwardersData, autorespondersData] = await Promise.all([
        emailApi.listAccounts(),
        emailApi.listForwarders(),
        emailApi.listAutoresponders(),
      ])
      setAccounts(accountsData)
      setForwarders(forwardersData)
      setAutoresponders(autorespondersData)
    } catch (error) {
      toast.error('Failed to load email data')
      console.error('Error fetching email data:', error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchData()
  }, [])

  const handleCreateAccount = async () => {
    const errors: Record<string, string> = {}
    if (!formData.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      errors.email = 'Please enter a valid email address'
    }
    if (!formData.password || formData.password.length < 8) {
      errors.password = 'Password must be at least 8 characters'
    }
    if (formData.password !== formData.confirmPassword) {
      errors.confirmPassword = 'Passwords do not match'
    }
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    try {
      setSubmitting(true)
      await emailApi.createAccount({
        email: formData.email,
        password: formData.password,
        quota: formData.quota,
      })
      toast.success('Email account created successfully')
      setShowCreateModal(false)
      setFormData({ ...formData, email: '', password: '', confirmPassword: '' })
      fetchData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create email account')
    } finally {
      setSubmitting(false)
    }
  }

  const handleCreateForwarder = async () => {
    const errors: Record<string, string> = {}
    if (!formData.source) errors.source = 'Please enter a source address'
    if (!formData.destination || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.destination)) {
      errors.destination = 'Please enter a valid destination email'
    }
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    try {
      setSubmitting(true)
      await emailApi.createForwarder({ source: formData.source, destination: formData.destination })
      toast.success('Email forwarder created successfully')
      setShowCreateModal(false)
      setFormData({ ...formData, source: '', destination: '' })
      fetchData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create forwarder')
    } finally {
      setSubmitting(false)
    }
  }

  const handleCreateAutoresponder = async () => {
    const errors: Record<string, string> = {}
    if (!formData.email) errors.email = 'Please select an email address'
    if (!formData.subject) errors.subject = 'Please enter a subject'
    if (!formData.body) errors.body = 'Please enter a message body'
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    try {
      setSubmitting(true)
      await emailApi.createAutoresponder({ email: formData.email, subject: formData.subject, body: formData.body })
      toast.success('Autoresponder created successfully')
      setShowCreateModal(false)
      setFormData({ ...formData, email: '', subject: '', body: '' })
      fetchData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create autoresponder')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteConfirm) return
    try {
      setSubmitting(true)
      switch (deleteConfirm.type) {
        case 'accounts':
          await emailApi.deleteAccount((deleteConfirm.item as EmailAccount).id)
          break
        case 'forwarders':
          await emailApi.deleteForwarder((deleteConfirm.item as EmailForwarder).id)
          break
        case 'autoresponders':
          await emailApi.deleteAutoresponder((deleteConfirm.item as EmailAutoresponder).id)
          break
      }
      toast.success('Deleted successfully')
      setDeleteConfirm(null)
      fetchData()
    } catch {
      toast.error('Failed to delete')
    } finally {
      setSubmitting(false)
    }
  }

  const handleChangePassword = async () => {
    if (!showPasswordModal) return
    const errors: Record<string, string> = {}
    if (!formData.password || formData.password.length < 8) errors.password = 'Password must be at least 8 characters'
    if (formData.password !== formData.confirmPassword) errors.confirmPassword = 'Passwords do not match'
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    try {
      setSubmitting(true)
      await emailApi.changePassword(showPasswordModal.id, formData.password)
      toast.success('Password changed successfully')
      setShowPasswordModal(null)
      setFormData({ ...formData, password: '', confirmPassword: '' })
    } catch {
      toast.error('Failed to change password')
    } finally {
      setSubmitting(false)
    }
  }

  const tabs = [
    { key: 'accounts' as TabType, label: 'Email Accounts', icon: EnvelopeIcon, count: accounts.length },
    { key: 'forwarders' as TabType, label: 'Forwarders', icon: ArrowsRightLeftIcon, count: forwarders.length },
    { key: 'autoresponders' as TabType, label: 'Autoresponders', icon: ChatBubbleBottomCenterTextIcon, count: autoresponders.length },
  ]

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Email Accounts</h1>
            <p className="text-gray-500">Manage your email accounts and forwarders</p>
          </div>
        </div>
        <Card>
          <CardBody className="flex items-center justify-center py-12">
            <ArrowPathIcon className="w-8 h-8 text-gray-400 animate-spin" />
            <span className="ml-3 text-gray-500">Loading email data...</span>
          </CardBody>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Email Accounts</h1>
          <p className="text-gray-500">Manage your email accounts and forwarders</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={fetchData}>
            <ArrowPathIcon className="w-5 h-5 mr-2" />
            Refresh
          </Button>
          <Button variant="primary" onClick={() => setShowCreateModal(true)}>
            <PlusIcon className="w-5 h-5 mr-2" />
            Create Email
          </Button>
        </div>
      </div>

      <div className="border-b border-gray-200">
        <nav className="flex space-x-8">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                activeTab === tab.key
                  ? 'border-primary-500 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <tab.icon className="w-5 h-5 mr-2" />
              {tab.label}
              <span className="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">{tab.count}</span>
            </button>
          ))}
        </nav>
      </div>

      <Card>
        <CardBody className="p-0">
          {activeTab === 'accounts' && (
            accounts.length === 0 ? (
              <EmptyState title="No email accounts" description="Create your first email account to get started." action={{ label: 'Create Email Account', onClick: () => setShowCreateModal(true) }} />
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quota Used</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {accounts.map((account) => (
                      <tr key={account.id}>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center">
                            <EnvelopeIcon className="w-5 h-5 text-gray-400 mr-3" />
                            <span className="font-medium text-gray-900">{account.email}</span>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="w-40">
                            <QuotaBar used={account.quota_used} limit={account.quota * 1024 * 1024} size="sm" showValues />
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {new Date(account.created_at).toLocaleDateString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                          <button onClick={() => setShowPasswordModal(account)} className="text-primary-600 hover:text-primary-800 mr-3">
                            <KeyIcon className="w-5 h-5 inline mr-1" />Password
                          </button>
                          <button onClick={() => window.open('/webmail', '_blank')} className="text-gray-600 hover:text-gray-800 mr-3">Webmail</button>
                          <button onClick={() => setDeleteConfirm({ type: 'accounts', item: account })} className="text-red-600 hover:text-red-800">
                            <TrashIcon className="w-5 h-5 inline" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          )}

          {activeTab === 'forwarders' && (
            forwarders.length === 0 ? (
              <EmptyState title="No forwarders" description="Create a forwarder to redirect emails to another address." action={{ label: 'Create Forwarder', onClick: () => setShowCreateModal(true) }} />
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {forwarders.map((forwarder) => (
                      <tr key={forwarder.id}>
                        <td className="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{forwarder.source}</td>
                        <td className="px-6 py-4 whitespace-nowrap text-gray-500">{forwarder.destination}</td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(forwarder.created_at).toLocaleDateString()}</td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                          <button onClick={() => setDeleteConfirm({ type: 'forwarders', item: forwarder })} className="text-red-600 hover:text-red-800">
                            <TrashIcon className="w-5 h-5 inline" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          )}

          {activeTab === 'autoresponders' && (
            autoresponders.length === 0 ? (
              <EmptyState title="No autoresponders" description="Set up automatic replies for your email addresses." action={{ label: 'Create Autoresponder', onClick: () => setShowCreateModal(true) }} />
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {autoresponders.map((ar) => (
                      <tr key={ar.id}>
                        <td className="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{ar.email}</td>
                        <td className="px-6 py-4 whitespace-nowrap text-gray-500">{ar.subject}</td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <Badge variant={ar.is_active ? 'success' : 'default'}>{ar.is_active ? 'Active' : 'Inactive'}</Badge>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                          <button onClick={() => setDeleteConfirm({ type: 'autoresponders', item: ar })} className="text-red-600 hover:text-red-800">
                            <TrashIcon className="w-5 h-5 inline" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          )}
        </CardBody>
      </Card>

      <Modal isOpen={showCreateModal} onClose={() => { setShowCreateModal(false); setFormErrors({}) }} title={`Create ${activeTab === 'accounts' ? 'Email Account' : activeTab === 'forwarders' ? 'Forwarder' : 'Autoresponder'}`}>
        <ModalBody className="space-y-4">
          {activeTab === 'accounts' && (
            <>
              <Input label="Email Address" placeholder="user@yourdomain.com" value={formData.email} onChange={(e) => setFormData({ ...formData, email: e.target.value })} error={formErrors.email} />
              <Input label="Password" type="password" value={formData.password} onChange={(e) => setFormData({ ...formData, password: e.target.value })} error={formErrors.password} />
              <Input label="Confirm Password" type="password" value={formData.confirmPassword} onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })} error={formErrors.confirmPassword} />
              <Input label="Quota (MB)" type="number" value={formData.quota} onChange={(e) => setFormData({ ...formData, quota: parseInt(e.target.value) })} hint="Set to 0 for unlimited" />
            </>
          )}
          {activeTab === 'forwarders' && (
            <>
              <Input label="Forward From" placeholder="alias@yourdomain.com" value={formData.source} onChange={(e) => setFormData({ ...formData, source: e.target.value })} error={formErrors.source} />
              <Input label="Forward To" placeholder="destination@example.com" value={formData.destination} onChange={(e) => setFormData({ ...formData, destination: e.target.value })} error={formErrors.destination} />
            </>
          )}
          {activeTab === 'autoresponders' && (
            <>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <select value={formData.email} onChange={(e) => setFormData({ ...formData, email: e.target.value })} className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                  <option value="">Select an email</option>
                  {accounts.map((account) => (<option key={account.id} value={account.email}>{account.email}</option>))}
                </select>
                {formErrors.email && <p className="mt-1 text-sm text-red-600">{formErrors.email}</p>}
              </div>
              <Input label="Subject" placeholder="Out of Office" value={formData.subject} onChange={(e) => setFormData({ ...formData, subject: e.target.value })} error={formErrors.subject} />
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Message</label>
                <textarea className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" rows={4} value={formData.body} onChange={(e) => setFormData({ ...formData, body: e.target.value })} placeholder="I'm currently out of the office..." />
                {formErrors.body && <p className="mt-1 text-sm text-red-600">{formErrors.body}</p>}
              </div>
            </>
          )}
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowCreateModal(false)} disabled={submitting}>Cancel</Button>
          <Button variant="primary" onClick={activeTab === 'accounts' ? handleCreateAccount : activeTab === 'forwarders' ? handleCreateForwarder : handleCreateAutoresponder} disabled={submitting}>
            {submitting ? 'Creating...' : 'Create'}
          </Button>
        </ModalFooter>
      </Modal>

      <Modal isOpen={!!showPasswordModal} onClose={() => { setShowPasswordModal(null); setFormErrors({}); setFormData({ ...formData, password: '', confirmPassword: '' }) }} title={`Change Password - ${showPasswordModal?.email}`}>
        <ModalBody className="space-y-4">
          <Input label="New Password" type="password" value={formData.password} onChange={(e) => setFormData({ ...formData, password: e.target.value })} error={formErrors.password} />
          <Input label="Confirm New Password" type="password" value={formData.confirmPassword} onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })} error={formErrors.confirmPassword} />
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowPasswordModal(null)} disabled={submitting}>Cancel</Button>
          <Button variant="primary" onClick={handleChangePassword} disabled={submitting}>{submitting ? 'Changing...' : 'Change Password'}</Button>
        </ModalFooter>
      </Modal>

      <ConfirmDialog isOpen={!!deleteConfirm} onClose={() => setDeleteConfirm(null)} onConfirm={handleDelete} title="Delete Confirmation" message={`Are you sure you want to delete this ${deleteConfirm?.type.slice(0, -1)}? This action cannot be undone.`} confirmLabel={submitting ? 'Deleting...' : 'Delete'} variant="danger" />
    </div>
  )
}
