import { useState, useEffect } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { backupsApi, Backup } from '../../api'
import toast from 'react-hot-toast'
import {
  ArchiveBoxIcon,
  ArrowDownTrayIcon,
  ArrowPathIcon,
  TrashIcon,
} from '@heroicons/react/24/outline'

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export default function Backups() {
  const [backups, setBackups] = useState<Backup[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showRestoreModal, setShowRestoreModal] = useState(false)
  const [selectedBackup, setSelectedBackup] = useState<Backup | null>(null)
  const [actionLoading, setActionLoading] = useState<number | null>(null)
  const [creating, setCreating] = useState(false)

  // Create backup options
  const [backupOptions, setBackupOptions] = useState({
    include_files: true,
    include_databases: true,
    include_emails: true,
  })

  // Restore options
  const [restoreOptions, setRestoreOptions] = useState({
    restore_files: true,
    restore_databases: true,
    restore_emails: true,
    overwrite: false,
  })

  useEffect(() => {
    loadBackups()
  }, [])

  const loadBackups = async () => {
    try {
      setLoading(true)
      const data = await backupsApi.list()
      setBackups(data)
    } catch (error) {
      toast.error('Failed to load backups')
    } finally {
      setLoading(false)
    }
  }

  const handleCreateBackup = async () => {
    try {
      setCreating(true)
      await backupsApi.create(backupOptions)
      toast.success('Backup creation started. This may take several minutes.')
      setShowCreateModal(false)
      // Refresh after a delay to check progress
      loadBackups()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create backup')
    } finally {
      setCreating(false)
    }
  }

  const handleDownload = async (backup: Backup) => {
    if (backup.status !== 'completed') {
      toast.error('Backup is not ready for download')
      return
    }

    let url: string | null = null
    let a: HTMLAnchorElement | null = null
    try {
      setActionLoading(backup.id)
      const blob = await backupsApi.download(backup.id)
      url = window.URL.createObjectURL(blob)
      a = document.createElement('a')
      a.href = url
      a.download = backup.filename || `backup-${backup.id}.tar.gz`
      document.body.appendChild(a)
      a.click()
      toast.success('Download started')
    } catch (error) {
      toast.error('Failed to download backup')
    } finally {
      // Clean up DOM and object URL
      if (url) window.URL.revokeObjectURL(url)
      if (a && document.body.contains(a)) document.body.removeChild(a)
      setActionLoading(null)
    }
  }

  const handleRestore = async () => {
    if (!selectedBackup) return

    if (!confirm('Are you sure you want to restore this backup? This will overwrite existing data.')) {
      return
    }

    try {
      setActionLoading(selectedBackup.id)
      await backupsApi.restore(selectedBackup.id, restoreOptions)
      toast.success('Restore started. This may take several minutes.')
      setShowRestoreModal(false)
      setSelectedBackup(null)
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to restore backup')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDelete = async (backup: Backup) => {
    if (!confirm(`Are you sure you want to delete this backup?`)) {
      return
    }

    try {
      setActionLoading(backup.id)
      await backupsApi.delete(backup.id)
      toast.success('Backup deleted')
      loadBackups()
    } catch (error) {
      toast.error('Failed to delete backup')
    } finally {
      setActionLoading(null)
    }
  }

  const openRestoreModal = (backup: Backup) => {
    setSelectedBackup(backup)
    setShowRestoreModal(true)
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
        return 'bg-green-100 text-green-800'
      case 'creating':
      case 'restoring':
        return 'bg-blue-100 text-blue-800'
      case 'failed':
        return 'bg-red-100 text-red-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
  }

  const getTypeBadge = (type: string) => {
    switch (type) {
      case 'full':
        return 'bg-blue-100 text-blue-800'
      case 'files':
        return 'bg-green-100 text-green-800'
      case 'databases':
        return 'bg-purple-100 text-purple-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
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
          <h1 className="text-2xl font-bold text-gray-900">Backups</h1>
          <p className="text-gray-500">Create and manage account backups</p>
        </div>
        <Button variant="primary" onClick={() => setShowCreateModal(true)}>
          <ArchiveBoxIcon className="w-5 h-5 mr-2" />
          Create Backup
        </Button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-primary-600">{backups.length}</div>
            <div className="text-sm text-gray-500">Total Backups</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-green-600">
              {backups.filter(b => b.status === 'completed').length}
            </div>
            <div className="text-sm text-gray-500">Completed</div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <div className="text-3xl font-bold text-blue-600">
              {formatBytes(backups.reduce((sum, b) => sum + (b.size || 0), 0))}
            </div>
            <div className="text-sm text-gray-500">Total Size</div>
          </CardBody>
        </Card>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Backup</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {backups.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-6 py-8 text-center text-gray-500">
                    No backups found. Create your first backup to protect your data.
                  </td>
                </tr>
              ) : (
                backups.map((backup) => (
                  <tr key={backup.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <ArchiveBoxIcon className="w-5 h-5 text-gray-400 mr-3" />
                        <span className="font-medium text-gray-900">
                          {backup.filename || `backup-${backup.id}`}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${getTypeBadge(backup.type)}`}>
                        {backup.type.charAt(0).toUpperCase() + backup.type.slice(1)}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {backup.size ? formatBytes(backup.size) : '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusBadge(backup.status)}`}>
                        {backup.status.charAt(0).toUpperCase() + backup.status.slice(1)}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(backup.created_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      <div className="flex space-x-2">
                        {backup.status === 'completed' && (
                          <>
                            <button
                              onClick={() => handleDownload(backup)}
                              className="text-primary-600 hover:text-primary-800 disabled:opacity-50 inline-flex items-center"
                              disabled={actionLoading === backup.id}
                              title="Download"
                            >
                              <ArrowDownTrayIcon className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => openRestoreModal(backup)}
                              className="text-gray-600 hover:text-gray-800"
                              title="Restore"
                            >
                              <ArrowPathIcon className="w-4 h-4" />
                            </button>
                          </>
                        )}
                        <button
                          onClick={() => handleDelete(backup)}
                          className="text-red-600 hover:text-red-800 disabled:opacity-50"
                          disabled={actionLoading === backup.id || backup.status === 'in_progress'}
                          title="Delete"
                        >
                          <TrashIcon className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </CardBody>
      </Card>

      {/* Create Backup Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Create Backup</h3>
            </div>
            <div className="p-6 space-y-4">
              <p className="text-gray-600 text-sm">
                Select what to include in your backup:
              </p>
              <div className="space-y-3">
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    checked={backupOptions.include_files}
                    onChange={(e) => setBackupOptions({ ...backupOptions, include_files: e.target.checked })}
                  />
                  <span className="ml-2 text-gray-700">Files (website files, public_html)</span>
                </label>
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    checked={backupOptions.include_databases}
                    onChange={(e) => setBackupOptions({ ...backupOptions, include_databases: e.target.checked })}
                  />
                  <span className="ml-2 text-gray-700">Databases (MySQL databases)</span>
                </label>
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    checked={backupOptions.include_emails}
                    onChange={(e) => setBackupOptions({ ...backupOptions, include_emails: e.target.checked })}
                  />
                  <span className="ml-2 text-gray-700">Email accounts and messages</span>
                </label>
              </div>
              <p className="text-xs text-gray-500 mt-2">
                Backup creation may take several minutes depending on data size.
              </p>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowCreateModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleCreateBackup}
                disabled={creating || (!backupOptions.include_files && !backupOptions.include_databases && !backupOptions.include_emails)}
              >
                {creating ? 'Creating...' : 'Create Backup'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Restore Backup Modal */}
      {showRestoreModal && selectedBackup && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Restore Backup</h3>
            </div>
            <div className="p-6 space-y-4">
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p className="text-sm text-yellow-800">
                  Warning: This will restore data from <strong>{selectedBackup.filename}</strong>.
                  Existing data may be overwritten.
                </p>
              </div>
              <div className="space-y-3">
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    checked={restoreOptions.restore_files}
                    onChange={(e) => setRestoreOptions({ ...restoreOptions, restore_files: e.target.checked })}
                  />
                  <span className="ml-2 text-gray-700">Restore files</span>
                </label>
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    checked={restoreOptions.restore_databases}
                    onChange={(e) => setRestoreOptions({ ...restoreOptions, restore_databases: e.target.checked })}
                  />
                  <span className="ml-2 text-gray-700">Restore databases</span>
                </label>
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    checked={restoreOptions.restore_emails}
                    onChange={(e) => setRestoreOptions({ ...restoreOptions, restore_emails: e.target.checked })}
                  />
                  <span className="ml-2 text-gray-700">Restore email accounts</span>
                </label>
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => {
                setShowRestoreModal(false)
                setSelectedBackup(null)
              }}>Cancel</Button>
              <Button
                variant="danger"
                onClick={handleRestore}
                disabled={actionLoading === selectedBackup.id}
              >
                {actionLoading === selectedBackup.id ? 'Restoring...' : 'Restore Backup'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
