import { useState, useEffect } from 'react'
import { Card, CardBody, CardHeader } from '../../components/common/Card'
import Button from '../../components/common/Button'
import Badge from '../../components/common/Badge'
import Modal, { ModalBody, ModalFooter } from '../../components/common/Modal'
import ConfirmDialog from '../../components/common/ConfirmDialog'
import toast from 'react-hot-toast'
import { zapierApi, ZapierConnection, ZapierTool } from '../../api'
import {
  BoltIcon,
  LinkIcon,
  TrashIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  CommandLineIcon,
} from '@heroicons/react/24/outline'

export default function Integrations() {
  const [connection, setConnection] = useState<ZapierConnection | null>(null)
  const [tools, setTools] = useState<ZapierTool[]>([])
  const [loading, setLoading] = useState(true)
  const [showConnectModal, setShowConnectModal] = useState(false)
  const [showDisconnectConfirm, setShowDisconnectConfirm] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  const fetchConnection = async () => {
    try {
      setLoading(true)
      const conn = await zapierApi.getConnection()
      setConnection(conn)

      if (conn) {
        const availableTools = await zapierApi.listTools()
        setTools(availableTools)
      }
    } catch (error) {
      console.error('Error fetching Zapier connection:', error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchConnection()
  }, [])

  const handleDisconnect = async () => {
    try {
      setSubmitting(true)
      await zapierApi.disconnect()
      setConnection(null)
      setTools([])
      toast.success('Zapier disconnected successfully')
      setShowDisconnectConfirm(false)
    } catch (error) {
      toast.error('Failed to disconnect Zapier')
    } finally {
      setSubmitting(false)
    }
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  // Group tools by category
  const toolsByCategory = tools.reduce((acc, tool) => {
    const category = tool.category || 'Other'
    if (!acc[category]) {
      acc[category] = []
    }
    acc[category].push(tool)
    return acc
  }, {} as Record<string, ZapierTool[]>)

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Integrations</h1>
            <p className="text-gray-500">Connect your hosting to external services</p>
          </div>
        </div>
        <Card>
          <CardBody className="flex items-center justify-center py-12">
            <ArrowPathIcon className="w-8 h-8 text-gray-400 animate-spin" />
            <span className="ml-3 text-gray-500">Loading integrations...</span>
          </CardBody>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Integrations</h1>
          <p className="text-gray-500">Connect your hosting to external services</p>
        </div>
      </div>

      {/* Zapier Integration Card */}
      <Card>
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="p-2 bg-orange-100 rounded-lg">
              <BoltIcon className="w-6 h-6 text-orange-600" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Zapier</h2>
              <p className="text-sm text-gray-500">Automate workflows with 5,000+ apps</p>
            </div>
          </div>
        </CardHeader>
        <CardBody>
          {connection ? (
            <div className="space-y-6">
              {/* Connection Status */}
              <div className="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-lg">
                <div className="flex items-center gap-3">
                  <CheckCircleIcon className="w-6 h-6 text-green-600" />
                  <div>
                    <p className="font-medium text-green-900">Connected</p>
                    <p className="text-sm text-green-700">
                      Connected on {formatDate(connection.connected_at)}
                    </p>
                  </div>
                </div>
                <Button
                  variant="danger"
                  size="sm"
                  onClick={() => setShowDisconnectConfirm(true)}
                >
                  <TrashIcon className="w-4 h-4 mr-2" />
                  Disconnect
                </Button>
              </div>

              {/* Connection Details */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="p-4 bg-gray-50 rounded-lg">
                  <p className="text-sm text-gray-500">Client ID</p>
                  <p className="font-mono text-sm text-gray-900 truncate">{connection.client_id}</p>
                </div>
                <div className="p-4 bg-gray-50 rounded-lg">
                  <p className="text-sm text-gray-500">Last Used</p>
                  <p className="text-sm text-gray-900">
                    {connection.last_used_at
                      ? formatDate(connection.last_used_at)
                      : 'Never'}
                  </p>
                </div>
                <div className="p-4 bg-gray-50 rounded-lg">
                  <p className="text-sm text-gray-500">Tools Used</p>
                  <p className="text-sm text-gray-900">
                    {connection.tools_used?.length || 0} tools
                  </p>
                </div>
              </div>

              {/* Available Tools */}
              {Object.keys(toolsByCategory).length > 0 && (
                <div>
                  <h3 className="text-md font-semibold text-gray-900 mb-4">Available Tools</h3>
                  <div className="space-y-4">
                    {Object.entries(toolsByCategory).map(([category, categoryTools]) => (
                      <div key={category}>
                        <h4 className="text-sm font-medium text-gray-700 mb-2">{category}</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                          {categoryTools.map((tool) => (
                            <div
                              key={tool.name}
                              className="p-3 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors"
                            >
                              <div className="flex items-start gap-3">
                                <div className="p-2 bg-gray-100 rounded-lg">
                                  <CommandLineIcon className="w-4 h-4 text-gray-600" />
                                </div>
                                <div className="flex-1 min-w-0">
                                  <p className="font-medium text-gray-900 text-sm">{tool.name}</p>
                                  <p className="text-xs text-gray-500 line-clamp-2">
                                    {tool.description}
                                  </p>
                                  {tool.parameters.length > 0 && (
                                    <p className="text-xs text-gray-400 mt-1">
                                      {tool.parameters.length} parameter(s)
                                    </p>
                                  )}
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="text-center py-8">
              <div className="p-4 bg-gray-100 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                <LinkIcon className="w-8 h-8 text-gray-400" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">Not Connected</h3>
              <p className="text-gray-500 mb-6 max-w-md mx-auto">
                Connect your FreePanel account to Zapier to automate tasks and integrate with thousands of apps.
              </p>
              <Button variant="primary" onClick={() => setShowConnectModal(true)}>
                <BoltIcon className="w-5 h-5 mr-2" />
                Connect to Zapier
              </Button>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Other Integrations (Future) */}
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-gray-900">More Integrations</h2>
        </CardHeader>
        <CardBody>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {/* GitHub */}
            <div className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center gap-3 mb-3">
                <div className="p-2 bg-gray-100 rounded-lg">
                  <svg className="w-5 h-5 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                  </svg>
                </div>
                <div>
                  <p className="font-medium text-gray-900">GitHub</p>
                  <p className="text-xs text-gray-500">Deploy from repositories</p>
                </div>
              </div>
              <Badge variant="default">Coming Soon</Badge>
            </div>

            {/* Slack */}
            <div className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center gap-3 mb-3">
                <div className="p-2 bg-purple-100 rounded-lg">
                  <svg className="w-5 h-5 text-purple-700" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"/>
                  </svg>
                </div>
                <div>
                  <p className="font-medium text-gray-900">Slack</p>
                  <p className="text-xs text-gray-500">Get notifications</p>
                </div>
              </div>
              <Badge variant="default">Coming Soon</Badge>
            </div>

            {/* Cloudflare */}
            <div className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center gap-3 mb-3">
                <div className="p-2 bg-orange-100 rounded-lg">
                  <svg className="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M16.5088 16.8447c.1475-.5068.0908-.9707-.1553-1.2704-.2246-.2725-.5765-.4123-.9678-.4282l-8.0936-.1123c-.0578 0-.1026-.0244-.1201-.0639-.0205-.0416-.0103-.0906.0264-.131.0682-.0745.1618-.1193.2646-.1254l8.1717-.1133c.942-.0534 1.9624-.8983 2.2853-1.8925l.408-1.2572c.0191-.0588.0257-.1197.019-.1799-.3826-3.0583-2.9975-5.425-6.1854-5.425-3.0062 0-5.5099 2.1206-6.0964 4.9449-.4527-.3328-1.0242-.5127-1.6442-.4656-1.1034.0856-1.9987.9707-2.0893 2.0741-.021.2561.0095.5023.0709.7319C.9763 13.221 0 14.3944 0 15.7987c0 .1818.0134.3571.0372.5276.0205.1587.1519.2779.3118.2779l15.8423-.0019c.1216 0 .2267-.0778.2652-.1891l.0523-.169z"/>
                  </svg>
                </div>
                <div>
                  <p className="font-medium text-gray-900">Cloudflare</p>
                  <p className="text-xs text-gray-500">CDN & Security</p>
                </div>
              </div>
              <Badge variant="default">Coming Soon</Badge>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Connect Modal */}
      <Modal
        isOpen={showConnectModal}
        onClose={() => setShowConnectModal(false)}
        title="Connect to Zapier"
        description="Follow the steps to connect your FreePanel account to Zapier"
      >
        <ModalBody>
          <div className="space-y-4">
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h4 className="font-medium text-blue-900 mb-2">How to Connect</h4>
              <ol className="list-decimal list-inside space-y-2 text-sm text-blue-800">
                <li>Log in to your Zapier account</li>
                <li>Create a new Zap or edit an existing one</li>
                <li>Search for "FreePanel" in the app directory</li>
                <li>Follow the authentication prompts</li>
                <li>Authorize FreePanel to access your hosting account</li>
              </ol>
            </div>
            <p className="text-sm text-gray-500">
              The connection will be established automatically when you authenticate
              through Zapier. This page will update to show your connection status.
            </p>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowConnectModal(false)}>
            Close
          </Button>
          <Button
            variant="primary"
            onClick={() => window.open('https://zapier.com/apps', '_blank')}
          >
            Go to Zapier
          </Button>
        </ModalFooter>
      </Modal>

      {/* Disconnect Confirmation */}
      <ConfirmDialog
        isOpen={showDisconnectConfirm}
        onClose={() => setShowDisconnectConfirm(false)}
        onConfirm={handleDisconnect}
        title="Disconnect Zapier"
        message="Are you sure you want to disconnect your Zapier integration? All existing Zaps using FreePanel will stop working."
        confirmLabel={submitting ? 'Disconnecting...' : 'Disconnect'}
        variant="danger"
      />
    </div>
  )
}
