import { useState, useEffect, useRef } from 'react'
import { Card, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { filesApi, FileItem, QuotaInfo } from '../../api'
import toast from 'react-hot-toast'
import {
  FolderIcon,
  DocumentIcon,
  ArrowUpTrayIcon,
  FolderPlusIcon,
  TrashIcon,
  PencilIcon,
  ArrowDownTrayIcon,
  HomeIcon,
  ChevronRightIcon,
} from '@heroicons/react/24/outline'

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  if (bytes === -1) return 'Unlimited'
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

export default function Files() {
  const [files, setFiles] = useState<FileItem[]>([])
  const [quota, setQuota] = useState<QuotaInfo | null>(null)
  const [loading, setLoading] = useState(true)
  const [currentPath, setCurrentPath] = useState('/public_html')
  const [showNewFolderModal, setShowNewFolderModal] = useState(false)
  const [showRenameModal, setShowRenameModal] = useState(false)
  const [showUploadModal, setShowUploadModal] = useState(false)
  const [selectedFile, setSelectedFile] = useState<FileItem | null>(null)
  const [actionLoading, setActionLoading] = useState<string | null>(null)
  const [uploadProgress, setUploadProgress] = useState(0)
  const fileInputRef = useRef<HTMLInputElement>(null)

  // Form state
  const [newFolderName, setNewFolderName] = useState('')
  const [newName, setNewName] = useState('')

  useEffect(() => {
    loadFiles()
    loadQuota()
  }, [currentPath])

  const loadFiles = async () => {
    try {
      setLoading(true)
      const data = await filesApi.list(currentPath)
      // Sort: directories first, then files, alphabetically
      const sorted = data.sort((a, b) => {
        if (a.type === 'directory' && b.type !== 'directory') return -1
        if (a.type !== 'directory' && b.type === 'directory') return 1
        return a.name.localeCompare(b.name)
      })
      setFiles(sorted)
    } catch (error) {
      toast.error('Failed to load files')
    } finally {
      setLoading(false)
    }
  }

  const loadQuota = async () => {
    try {
      const data = await filesApi.quota()
      setQuota(data)
    } catch (error) {
      // Ignore quota errors
    }
  }

  const handleNavigate = (path: string) => {
    setCurrentPath(path)
  }

  const handleFileClick = (file: FileItem) => {
    if (file.type === 'directory') {
      setCurrentPath(file.path)
    }
  }

  const handleCreateFolder = async () => {
    if (!newFolderName) {
      toast.error('Please enter a folder name')
      return
    }

    try {
      setActionLoading('create')
      const path = currentPath === '/' ? `/${newFolderName}` : `${currentPath}/${newFolderName}`
      await filesApi.mkdir(path)
      toast.success('Folder created successfully')
      setShowNewFolderModal(false)
      setNewFolderName('')
      loadFiles()
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      toast.error(err.response?.data?.message || 'Failed to create folder')
    } finally {
      setActionLoading(null)
    }
  }

  const handleRename = async () => {
    if (!selectedFile || !newName) {
      toast.error('Please enter a new name')
      return
    }

    try {
      setActionLoading('rename')
      const parentPath = selectedFile.path.substring(0, selectedFile.path.lastIndexOf('/'))
      const destination = parentPath === '' ? `/${newName}` : `${parentPath}/${newName}`
      await filesApi.move(selectedFile.path, destination)
      toast.success('Renamed successfully')
      setShowRenameModal(false)
      setSelectedFile(null)
      setNewName('')
      loadFiles()
    } catch (error) {
      toast.error('Failed to rename')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDelete = async (file: FileItem) => {
    if (!confirm(`Are you sure you want to delete "${file.name}"?${file.type === 'directory' ? ' All contents will be deleted.' : ''}`)) {
      return
    }

    try {
      setActionLoading(file.path)
      await filesApi.delete(file.path)
      toast.success('Deleted successfully')
      loadFiles()
    } catch (error) {
      toast.error('Failed to delete')
    } finally {
      setActionLoading(null)
    }
  }

  const handleDownload = async (file: FileItem) => {
    try {
      setActionLoading(file.path)
      const blob = await filesApi.download(file.path)
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = file.name
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
    } catch (error) {
      toast.error('Failed to download')
    } finally {
      setActionLoading(null)
    }
  }

  const handleUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (!file) return

    try {
      setShowUploadModal(true)
      setUploadProgress(0)
      await filesApi.upload(currentPath, file, (percent) => {
        setUploadProgress(percent)
      })
      toast.success('File uploaded successfully')
      setShowUploadModal(false)
      loadFiles()
      loadQuota()
    } catch (error) {
      toast.error('Failed to upload file')
      setShowUploadModal(false)
    }

    // Reset file input
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const openRenameModal = (file: FileItem) => {
    setSelectedFile(file)
    setNewName(file.name)
    setShowRenameModal(true)
  }

  // Parse breadcrumb from path
  const pathParts = currentPath.split('/').filter(Boolean)
  const breadcrumbs = pathParts.map((part, index) => ({
    name: part,
    path: '/' + pathParts.slice(0, index + 1).join('/'),
  }))

  if (loading && files.length === 0) {
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
          <h1 className="text-2xl font-bold text-gray-900">File Manager</h1>
          <p className="text-gray-500">Browse and manage your files</p>
        </div>
        <div className="flex space-x-3">
          <Button variant="secondary" onClick={() => setShowNewFolderModal(true)}>
            <FolderPlusIcon className="w-5 h-5 mr-2" />
            New Folder
          </Button>
          <Button variant="primary" onClick={() => fileInputRef.current?.click()}>
            <ArrowUpTrayIcon className="w-5 h-5 mr-2" />
            Upload
          </Button>
          <input
            ref={fileInputRef}
            type="file"
            className="hidden"
            onChange={handleUpload}
          />
        </div>
      </div>

      {/* Quota */}
      {quota && (
        <Card>
          <CardBody>
            <div className="flex justify-between items-center mb-2">
              <span className="text-sm text-gray-600">Disk Usage</span>
              <span className="text-sm font-medium">
                {formatBytes(quota.used)} / {formatBytes(quota.limit)}
              </span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div
                className={`h-2 rounded-full ${quota.used / quota.limit > 0.9 ? 'bg-red-500' : 'bg-primary-500'}`}
                style={{ width: `${Math.min(100, (quota.used / quota.limit) * 100)}%` }}
              ></div>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Breadcrumb */}
      <div className="flex items-center space-x-2 text-sm bg-white p-3 rounded-lg shadow-sm">
        <button
          onClick={() => handleNavigate('/')}
          className="text-primary-600 hover:text-primary-800 flex items-center"
        >
          <HomeIcon className="w-4 h-4" />
        </button>
        {breadcrumbs.map((crumb, index) => (
          <span key={crumb.path} className="flex items-center">
            <ChevronRightIcon className="w-4 h-4 text-gray-400 mx-1" />
            {index === breadcrumbs.length - 1 ? (
              <span className="text-gray-600 font-medium">{crumb.name}</span>
            ) : (
              <button
                onClick={() => handleNavigate(crumb.path)}
                className="text-primary-600 hover:text-primary-800"
              >
                {crumb.name}
              </button>
            )}
          </span>
        ))}
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
              {files.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                    This folder is empty
                  </td>
                </tr>
              ) : (
                files.map((file) => (
                  <tr
                    key={file.path}
                    className="hover:bg-gray-50"
                  >
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div
                        className={`flex items-center ${file.type === 'directory' ? 'cursor-pointer' : ''}`}
                        onClick={() => handleFileClick(file)}
                      >
                        {file.type === 'directory' ? (
                          <FolderIcon className="w-5 h-5 text-yellow-500 mr-3" />
                        ) : (
                          <DocumentIcon className="w-5 h-5 text-gray-400 mr-3" />
                        )}
                        <span className={`font-medium ${file.type === 'directory' ? 'text-primary-600 hover:text-primary-800' : 'text-gray-900'}`}>
                          {file.name}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {file.type === 'directory' ? '-' : formatBytes(file.size)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(file.modified_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                      {file.permissions}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      <div className="flex space-x-2">
                        <button
                          onClick={() => openRenameModal(file)}
                          className="text-gray-600 hover:text-gray-800"
                          title="Rename"
                        >
                          <PencilIcon className="w-4 h-4" />
                        </button>
                        {file.type === 'file' && (
                          <button
                            onClick={() => handleDownload(file)}
                            className="text-primary-600 hover:text-primary-800 disabled:opacity-50"
                            disabled={actionLoading === file.path}
                            title="Download"
                          >
                            <ArrowDownTrayIcon className="w-4 h-4" />
                          </button>
                        )}
                        <button
                          onClick={() => handleDelete(file)}
                          className="text-red-600 hover:text-red-800 disabled:opacity-50"
                          disabled={actionLoading === file.path}
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

      {/* New Folder Modal */}
      {showNewFolderModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Create New Folder</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Folder Name *</label>
                <input
                  type="text"
                  className="input"
                  placeholder="new-folder"
                  value={newFolderName}
                  onChange={(e) => setNewFolderName(e.target.value)}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowNewFolderModal(false)}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleCreateFolder}
                disabled={actionLoading === 'create'}
              >
                {actionLoading === 'create' ? 'Creating...' : 'Create Folder'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Rename Modal */}
      {showRenameModal && selectedFile && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">Rename {selectedFile.type === 'directory' ? 'Folder' : 'File'}</h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">New Name *</label>
                <input
                  type="text"
                  className="input"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => {
                setShowRenameModal(false)
                setSelectedFile(null)
              }}>Cancel</Button>
              <Button
                variant="primary"
                onClick={handleRename}
                disabled={actionLoading === 'rename'}
              >
                {actionLoading === 'rename' ? 'Renaming...' : 'Rename'}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Upload Progress Modal */}
      {showUploadModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Uploading File</h3>
            <div className="w-full bg-gray-200 rounded-full h-4 mb-2">
              <div
                className="bg-primary-500 h-4 rounded-full transition-all duration-300"
                style={{ width: `${uploadProgress}%` }}
              ></div>
            </div>
            <p className="text-center text-gray-600">{uploadProgress}%</p>
          </div>
        </div>
      )}
    </div>
  )
}
