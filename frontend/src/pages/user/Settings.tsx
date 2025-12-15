import { Card, CardHeader, CardBody } from '../../components/common/Card'
import Button from '../../components/common/Button'
import { useAuth } from '../../hooks/useAuth'

export default function Settings() {
  const { user } = useAuth()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Account Settings</h1>
        <p className="text-gray-500">Manage your account preferences and security</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Profile */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Profile</h2>
          </CardHeader>
          <CardBody>
            <form className="space-y-4">
              <div>
                <label className="label">Username</label>
                <input type="text" className="input" value={user?.username} disabled />
              </div>
              <div>
                <label className="label">Email</label>
                <input type="email" className="input" defaultValue={user?.email} />
              </div>
              <Button variant="primary">Save Changes</Button>
            </form>
          </CardBody>
        </Card>

        {/* Change Password */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Change Password</h2>
          </CardHeader>
          <CardBody>
            <form className="space-y-4">
              <div>
                <label className="label">Current Password</label>
                <input type="password" className="input" />
              </div>
              <div>
                <label className="label">New Password</label>
                <input type="password" className="input" />
              </div>
              <div>
                <label className="label">Confirm New Password</label>
                <input type="password" className="input" />
              </div>
              <Button variant="primary">Update Password</Button>
            </form>
          </CardBody>
        </Card>

        {/* Two-Factor Auth */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">Two-Factor Authentication</h2>
          </CardHeader>
          <CardBody>
            <p className="text-gray-600 mb-4">
              {user?.two_factor_enabled
                ? 'Two-factor authentication is enabled for your account.'
                : 'Add an extra layer of security to your account.'}
            </p>
            <Button variant={user?.two_factor_enabled ? 'danger' : 'success'}>
              {user?.two_factor_enabled ? 'Disable 2FA' : 'Enable 2FA'}
            </Button>
          </CardBody>
        </Card>

        {/* API Tokens */}
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-gray-900">API Tokens</h2>
          </CardHeader>
          <CardBody>
            <p className="text-gray-600 mb-4">
              Generate API tokens to access your account programmatically.
            </p>
            <Button variant="secondary">Generate New Token</Button>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
