import { Card, CardBody } from "../../components/common/Card";
import Button from "../../components/common/Button";
import {
  FolderIcon,
  DocumentIcon,
  ArrowUpTrayIcon,
} from "@heroicons/react/24/outline";

export default function Files() {
  const [files, setFiles] = useState<FileItem[]>([])
  const [currentPath, setCurrentPath] = useState('/public_html')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Modals
  const [showNewFolderModal, setShowNewFolderModal] = useState(false)
  const [showUploadModal, setShowUploadModal] = useState(false)
  const [showRenameModal, setShowRenameModal] = useState(false)
  const [showDeleteModal, setShowDeleteModal] = useState(false)
  const [showEditModal, setShowEditModal] = useState(false)
  const [selectedFile, setSelectedFile] = useState<FileItem | null>(null)

  // Form states
  const [newFolderName, setNewFolderName] = useState('')
  const [newName, setNewName] = useState('')
  const [fileContent, setFileContent] = useState('')
  const [uploadFile, setUploadFile] = useState<File | null>(null)
  const [uploadProgress, setUploadProgress] = useState(0)
  const [submitting, setSubmitting] = useState(false)

  const fetchFiles = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      const data = await filesApi.list(currentPath)
      setFiles(data)
    } catch (err) {
      setError('Failed to load files')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }, [currentPath])

  useEffect(() => {
    fetchFiles()
  }, [fetchFiles])

  const navigateTo = (path: string) => {
    setCurrentPath(path)
  }

  const navigateToFile = (file: FileItem) => {
    if (file.type === 'directory') {
      navigateTo(file.path)
    }
  }

  const handleCreateFolder = async (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    try {
      await filesApi.mkdir(`${currentPath}/${newFolderName}`)
      setShowNewFolderModal(false)
      setNewFolderName('')
      fetchFiles()
    } catch (err) {
      console.error('Failed to create folder:', err)
      alert('Failed to create folder')
    } finally {
      setSubmitting(false)
    }
  }

  const handleUpload = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!uploadFile) return

    setSubmitting(true)
    try {
      await filesApi.upload(currentPath, uploadFile, (progress) => {
        setUploadProgress(progress)
      })
      setShowUploadModal(false)
      setUploadFile(null)
      setUploadProgress(0)
      fetchFiles()
    } catch (err) {
      console.error('Failed to upload file:', err)
      alert('Failed to upload file')
    } finally {
      setSubmitting(false)
    }
  }

  const handleRename = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedFile) return

    setSubmitting(true)
    try {
      const newPath = currentPath + '/' + newName
      await filesApi.move(selectedFile.path, newPath)
      setShowRenameModal(false)
      setSelectedFile(null)
      setNewName('')
      fetchFiles()
    } catch (err) {
      console.error('Failed to rename:', err)
      alert('Failed to rename')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!selectedFile) return

    setSubmitting(true)
    try {
      await filesApi.delete(selectedFile.path)
      setShowDeleteModal(false)
      setSelectedFile(null)
      fetchFiles()
    } catch (err) {
      console.error('Failed to delete:', err)
      alert('Failed to delete')
    } finally {
      setSubmitting(false)
    }
  }

  const handleEdit = async (file: FileItem) => {
    setSelectedFile(file)
    try {
      const result = await filesApi.read(file.path)
      setFileContent(result.content)
      setShowEditModal(true)
    } catch (err) {
      console.error('Failed to read file:', err)
      alert('Failed to read file')
    }
  }

  const handleSaveFile = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedFile) return

    setSubmitting(true)
    try {
      await filesApi.write(selectedFile.path, fileContent)
      setShowEditModal(false)
      setSelectedFile(null)
      setFileContent('')
      fetchFiles()
    } catch (err) {
      console.error('Failed to save file:', err)
      alert('Failed to save file')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDownload = async (file: FileItem) => {
    try {
      const blob = await filesApi.download(file.path)
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = file.name
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
    } catch (err) {
      console.error('Failed to download file:', err)
      alert('Failed to download file')
    }
  }

  const formatSize = (bytes: number) => {
    if (bytes === 0) return '-'
    if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(2)} GB`
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(2)} MB`
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(2)} KB`
    return `${bytes} B`
  }

  const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  }

  const getBreadcrumbs = () => {
    const parts = currentPath.split('/').filter(Boolean)
    const breadcrumbs = [{ name: 'Home', path: '/' }]
    let path = ''
    for (const part of parts) {
      path += '/' + part
      breadcrumbs.push({ name: part, path })
    }
    return breadcrumbs
  }

  const isEditable = (file: FileItem) => {
    const editableExtensions = [
      '.txt',
      '.html',
      '.htm',
      '.css',
      '.js',
      '.json',
      '.xml',
      '.php',
      '.py',
      '.rb',
      '.md',
      '.htaccess',
      '.conf',
    ]
    return editableExtensions.some((ext) => file.name.toLowerCase().endsWith(ext))
  }

  if (loading && files.length === 0) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
      </div>
    )
  }

  if (error && files.length === 0) {
    return (
      <div className="text-center py-12">
        <ExclamationTriangleIcon className="w-12 h-12 text-red-500 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-gray-900 mb-2">Error loading files</h3>
        <p className="text-gray-500 mb-4">{error}</p>
        <Button variant="primary" onClick={fetchFiles}>
          Try Again
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">File Manager</h1>
          <p className="text-gray-500">Browse and manage your files</p>
        </div>
        <div className="flex space-x-3">
          <Button variant="secondary" onClick={() => setShowNewFolderModal(true)}>
            <FolderPlusIcon className="w-5 h-5 mr-2" />
            New Folder
          </Button>
          <Button variant="primary" onClick={() => setShowUploadModal(true)}>
            <ArrowUpTrayIcon className="w-5 h-5 mr-2" />
            Upload
          </Button>
        </div>
      </div>

      {/* Breadcrumb */}
      <div className="flex items-center space-x-2 text-sm">
        <span className="text-primary-600 cursor-pointer hover:underline">
          Home
        </span>
        <span className="text-gray-400">/</span>
        <span className="text-gray-600">public_html</span>
      </div>

      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Name
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Size
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Modified
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Permissions
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              <tr className="hover:bg-gray-50 cursor-pointer">
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center">
                    <FolderIcon className="w-5 h-5 text-yellow-500 mr-3" />
                    <span className="font-medium text-gray-900">
                      wp-content
                    </span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  -
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  Jan 15, 2024
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  drwxr-xr-x
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-gray-600 hover:text-gray-800 mr-3">
                    Rename
                  </button>
                  <button className="text-red-600 hover:text-red-800">
                    Delete
                  </button>
                </td>
              </tr>
              <tr className="hover:bg-gray-50 cursor-pointer">
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex items-center">
                    <DocumentIcon className="w-5 h-5 text-gray-400 mr-3" />
                    <span className="font-medium text-gray-900">index.php</span>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  418 B
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  Jan 15, 2024
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  -rw-r--r--
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  <button className="text-primary-600 hover:text-primary-800 mr-3">
                    Edit
                  </button>
                  <button className="text-gray-600 hover:text-gray-800 mr-3">
                    Download
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

      {/* New Folder Modal */}
      <Modal
        isOpen={showNewFolderModal}
        onClose={() => setShowNewFolderModal(false)}
        title="Create New Folder"
      >
        <form onSubmit={handleCreateFolder} className="space-y-4">
          <div>
            <label className="label">Folder Name</label>
            <input
              type="text"
              className="input"
              placeholder="new-folder"
              value={newFolderName}
              onChange={(e) => setNewFolderName(e.target.value)}
              required
            />
          </div>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowNewFolderModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" disabled={submitting}>
              {submitting ? 'Creating...' : 'Create Folder'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Upload Modal */}
      <Modal
        isOpen={showUploadModal}
        onClose={() => setShowUploadModal(false)}
        title="Upload File"
      >
        <form onSubmit={handleUpload} className="space-y-4">
          <div>
            <label className="label">Select File</label>
            <input
              type="file"
              className="input"
              onChange={(e) => setUploadFile(e.target.files?.[0] || null)}
              required
            />
          </div>
          {uploadProgress > 0 && uploadProgress < 100 && (
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div
                className="bg-primary-600 h-2 rounded-full transition-all"
                style={{ width: `${uploadProgress}%` }}
              />
            </div>
          )}
          <p className="text-sm text-gray-500">
            Uploading to: <code className="bg-gray-100 px-1 rounded">{currentPath}</code>
          </p>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowUploadModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" disabled={submitting || !uploadFile}>
              {submitting ? 'Uploading...' : 'Upload'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Rename Modal */}
      <Modal
        isOpen={showRenameModal}
        onClose={() => setShowRenameModal(false)}
        title="Rename"
      >
        <form onSubmit={handleRename} className="space-y-4">
          <div>
            <label className="label">New Name</label>
            <input
              type="text"
              className="input"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              required
            />
          </div>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowRenameModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" disabled={submitting}>
              {submitting ? 'Renaming...' : 'Rename'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={showDeleteModal}
        onClose={() => setShowDeleteModal(false)}
        title="Delete"
      >
        <div className="space-y-4">
          <p className="text-gray-600">
            Are you sure you want to delete <strong>{selectedFile?.name}</strong>?
            {selectedFile?.type === 'directory' && ' This will delete all contents inside.'}
          </p>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowDeleteModal(false)}>
              Cancel
            </Button>
            <Button type="button" variant="danger" onClick={handleDelete} disabled={submitting}>
              {submitting ? 'Deleting...' : 'Delete'}
            </Button>
          </div>
        </div>
      </Modal>

      {/* Edit File Modal */}
      <Modal
        isOpen={showEditModal}
        onClose={() => setShowEditModal(false)}
        title={`Edit: ${selectedFile?.name}`}
        size="lg"
      >
        <form onSubmit={handleSaveFile} className="space-y-4">
          <div>
            <textarea
              className="input font-mono text-sm"
              rows={20}
              value={fileContent}
              onChange={(e) => setFileContent(e.target.value)}
            />
          </div>
          <div className="flex justify-end space-x-3">
            <Button type="button" variant="secondary" onClick={() => setShowEditModal(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="primary" disabled={submitting}>
              {submitting ? 'Saving...' : 'Save'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
