import { Card, CardBody } from "../../components/common/Card";
import Button from "../../components/common/Button";
import { ArchiveBoxIcon, ArrowDownTrayIcon } from "@heroicons/react/24/outline";

export default function Backups() {
  const [backups, setBackups] = useState<Backup[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showRestoreModal, setShowRestoreModal] = useState<Backup | null>(null)
  const [deleteConfirm, setDeleteConfirm] = useState<Backup | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [downloading, setDownloading] = useState<number | null>(null)

  const [createOptions, setCreateOptions] = useState({
    include_files: true,
    include_databases: true,
    include_emails: true,
  })

  const [restoreOptions, setRestoreOptions] = useState({
    restore_files: true,
    restore_databases: true,
    restore_emails: true,
  })

  const fetchData = async () => {
    try {
      setLoading(true)
      const data = await backupsApi.list()
      setBackups(data)
    } catch (error) {
      toast.error('Failed to load backups')
      console.error('Error fetching backups:', error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchData()
  }, [])

  const formatSize = (bytes: number): string => {
    if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(2)} GB`
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(2)} MB`
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(2)} KB`
    return `${bytes} B`
  }

  const handleCreateBackup = async () => {
    try {
      setSubmitting(true)
      await backupsApi.create(createOptions)
      toast.success('Backup creation started. This may take a few minutes.')
      setShowCreateModal(false)
      fetchData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create backup')
    } finally {
      setSubmitting(false)
    }
  }

  const handleRestore = async () => {
    if (!showRestoreModal) return
    try {
      setSubmitting(true)
      await backupsApi.restore(showRestoreModal.id, restoreOptions)
      toast.success('Backup restoration started. This may take a few minutes.')
      setShowRestoreModal(null)
      fetchData()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to restore backup')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDownload = async (backup: Backup) => {
    try {
      setDownloading(backup.id)
      const blob = await backupsApi.download(backup.id)
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = backup.filename
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
      toast.success('Download started')
    } catch {
      toast.error('Failed to download backup')
    } finally {
      setDownloading(null)
    }
  }

  const handleDelete = async () => {
    if (!deleteConfirm) return
    try {
      setSubmitting(true)
      await backupsApi.delete(deleteConfirm.id)
      toast.success('Backup deleted successfully')
      setDeleteConfirm(null)
      fetchData()
    } catch {
      toast.error('Failed to delete backup')
    } finally {
      setSubmitting(false)
    }
  }

  const getStatusBadge = (status: Backup['status']) => {
    const config = {
      pending: { variant: 'warning' as const, icon: ClockIcon, label: 'Pending' },
      in_progress: { variant: 'info' as const, icon: ArrowPathIcon, label: 'In Progress' },
      completed: { variant: 'success' as const, icon: CheckCircleIcon, label: 'Completed' },
      failed: { variant: 'danger' as const, icon: XCircleIcon, label: 'Failed' },
    }
    const cfg = config[status]
    return (
      <Badge variant={cfg.variant}>
        <cfg.icon className="w-4 h-4 mr-1" />
        {cfg.label}
      </Badge>
    )
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Backups</h1>
            <p className="text-gray-500">Create and manage account backups</p>
          </div>
        </div>
        <Card>
          <CardBody className="flex items-center justify-center py-12">
            <ArrowPathIcon className="w-8 h-8 text-gray-400 animate-spin" />
            <span className="ml-3 text-gray-500">Loading backups...</span>
          </CardBody>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Backups</h1>
          <p className="text-gray-500">Create and manage account backups</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={fetchData}>
            <ArrowPathIcon className="w-5 h-5 mr-2" />
            Refresh
          </Button>
          <Button variant="primary" onClick={() => setShowCreateModal(true)}>
            <ArchiveBoxIcon className="w-5 h-5 mr-2" />
            Create Backup
          </Button>
        </div>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Backup
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Type
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Size
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Created
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
                    <ArchiveBoxIcon className="w-5 h-5 text-gray-400 mr-3" />
                    <span className="font-medium text-gray-900">
                      backup_20240115.tar.gz
                    </span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <span className="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                    Full
                  </span>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  1.2 GB
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  Jan 15, 2024
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-primary-600 hover:text-primary-800 mr-3 inline-flex items-center">
                    <ArrowDownTrayIcon className="w-4 h-4 mr-1" />
                    Download
                  </button>
                  <button className="text-gray-600 hover:text-gray-800 mr-3">
                    Restore
                  </button>
                  <button className="text-red-600 hover:text-red-800">
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </CardBody>
      </Card>

      {/* Create Backup Modal */}
      <Modal isOpen={showCreateModal} onClose={() => setShowCreateModal(false)} title="Create Backup">
        <ModalBody className="space-y-4">
          <p className="text-sm text-gray-600">
            Select what you want to include in the backup. Full backups may take several minutes to complete.
          </p>
          <div className="space-y-3">
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="checkbox"
                checked={createOptions.include_files}
                onChange={(e) => setCreateOptions({ ...createOptions, include_files: e.target.checked })}
                className="w-4 h-4 text-primary-600 rounded focus:ring-primary-500"
              />
              <div>
                <p className="font-medium text-gray-900">Files</p>
                <p className="text-sm text-gray-500">All files in your home directory</p>
              </div>
            </label>
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="checkbox"
                checked={createOptions.include_databases}
                onChange={(e) => setCreateOptions({ ...createOptions, include_databases: e.target.checked })}
                className="w-4 h-4 text-primary-600 rounded focus:ring-primary-500"
              />
              <div>
                <p className="font-medium text-gray-900">Databases</p>
                <p className="text-sm text-gray-500">All MySQL databases</p>
              </div>
            </label>
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="checkbox"
                checked={createOptions.include_emails}
                onChange={(e) => setCreateOptions({ ...createOptions, include_emails: e.target.checked })}
                className="w-4 h-4 text-primary-600 rounded focus:ring-primary-500"
              />
              <div>
                <p className="font-medium text-gray-900">Emails</p>
                <p className="text-sm text-gray-500">All email accounts and messages</p>
              </div>
            </label>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowCreateModal(false)} disabled={submitting}>Cancel</Button>
          <Button
            variant="primary"
            onClick={handleCreateBackup}
            disabled={submitting || (!createOptions.include_files && !createOptions.include_databases && !createOptions.include_emails)}
          >
            {submitting ? 'Creating...' : 'Create Backup'}
          </Button>
        </ModalFooter>
      </Modal>

      {/* Restore Backup Modal */}
      <Modal isOpen={!!showRestoreModal} onClose={() => setShowRestoreModal(null)} title="Restore Backup">
        <ModalBody className="space-y-4">
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div className="flex items-start gap-3">
              <ExclamationTriangleIcon className="w-5 h-5 text-amber-600 mt-0.5" />
              <div>
                <p className="font-medium text-amber-900">Warning</p>
                <p className="text-sm text-amber-700">
                  Restoring this backup will overwrite your current data. This action cannot be undone.
                </p>
              </div>
            </div>
          </div>
          <p className="text-sm text-gray-600">
            Select what you want to restore from this backup:
          </p>
          <div className="space-y-3">
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="checkbox"
                checked={restoreOptions.restore_files}
                onChange={(e) => setRestoreOptions({ ...restoreOptions, restore_files: e.target.checked })}
                disabled={!showRestoreModal?.includes.files}
                className="w-4 h-4 text-primary-600 rounded focus:ring-primary-500 disabled:opacity-50"
              />
              <div>
                <p className={`font-medium ${showRestoreModal?.includes.files ? 'text-gray-900' : 'text-gray-400'}`}>Files</p>
                <p className="text-sm text-gray-500">
                  {showRestoreModal?.includes.files ? 'Restore all files' : 'Not included in backup'}
                </p>
              </div>
            </label>
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="checkbox"
                checked={restoreOptions.restore_databases}
                onChange={(e) => setRestoreOptions({ ...restoreOptions, restore_databases: e.target.checked })}
                disabled={!showRestoreModal?.includes.databases}
                className="w-4 h-4 text-primary-600 rounded focus:ring-primary-500 disabled:opacity-50"
              />
              <div>
                <p className={`font-medium ${showRestoreModal?.includes.databases ? 'text-gray-900' : 'text-gray-400'}`}>Databases</p>
                <p className="text-sm text-gray-500">
                  {showRestoreModal?.includes.databases ? 'Restore all databases' : 'Not included in backup'}
                </p>
              </div>
            </label>
            <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="checkbox"
                checked={restoreOptions.restore_emails}
                onChange={(e) => setRestoreOptions({ ...restoreOptions, restore_emails: e.target.checked })}
                disabled={!showRestoreModal?.includes.emails}
                className="w-4 h-4 text-primary-600 rounded focus:ring-primary-500 disabled:opacity-50"
              />
              <div>
                <p className={`font-medium ${showRestoreModal?.includes.emails ? 'text-gray-900' : 'text-gray-400'}`}>Emails</p>
                <p className="text-sm text-gray-500">
                  {showRestoreModal?.includes.emails ? 'Restore all emails' : 'Not included in backup'}
                </p>
              </div>
            </label>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowRestoreModal(null)} disabled={submitting}>Cancel</Button>
          <Button
            variant="warning"
            onClick={handleRestore}
            disabled={submitting || (!restoreOptions.restore_files && !restoreOptions.restore_databases && !restoreOptions.restore_emails)}
          >
            {submitting ? 'Restoring...' : 'Restore Backup'}
          </Button>
        </ModalFooter>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={!!deleteConfirm}
        onClose={() => setDeleteConfirm(null)}
        onConfirm={handleDelete}
        title="Delete Backup"
        message={`Are you sure you want to delete "${deleteConfirm?.filename}"? This action cannot be undone.`}
        confirmLabel={submitting ? 'Deleting...' : 'Delete'}
        variant="danger"
      />
    </div>
  );
}
