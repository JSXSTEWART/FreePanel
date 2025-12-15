import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './hooks/useAuth'

// Layouts
import AdminLayout from './components/layout/AdminLayout'
import UserLayout from './components/layout/UserLayout'

// Auth Pages
import Login from './pages/auth/Login'

// Admin Pages
import AdminDashboard from './pages/admin/Dashboard'
import AdminAccounts from './pages/admin/Accounts'
import AdminPackages from './pages/admin/Packages'
import AdminServices from './pages/admin/Services'

// User Pages
import UserDashboard from './pages/user/Dashboard'
import Domains from './pages/user/Domains'
import Email from './pages/user/Email'
import Databases from './pages/user/Databases'
import Files from './pages/user/Files'
import Ssl from './pages/user/Ssl'
import Apps from './pages/user/Apps'
import Backups from './pages/user/Backups'
import Settings from './pages/user/Settings'

// Protected Route Component
import ProtectedRoute from './routes/ProtectedRoute'

function App() {
  const { isAuthenticated, user, isLoading } = useAuth()

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="animate-spin rounded-full h-12 w-12 border-4 border-primary-600 border-t-transparent"></div>
      </div>
    )
  }

  return (
    <Routes>
      {/* Public Routes */}
      <Route
        path="/login"
        element={isAuthenticated ? <Navigate to="/" replace /> : <Login />}
      />

      {/* Admin Routes */}
      <Route
        path="/admin"
        element={
          <ProtectedRoute allowedRoles={['admin']}>
            <AdminLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<AdminDashboard />} />
        <Route path="accounts" element={<AdminAccounts />} />
        <Route path="packages" element={<AdminPackages />} />
        <Route path="services" element={<AdminServices />} />
      </Route>

      {/* User Routes */}
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <UserLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={user?.role === 'admin' ? <Navigate to="/admin" replace /> : <UserDashboard />} />
        <Route path="domains" element={<Domains />} />
        <Route path="email" element={<Email />} />
        <Route path="databases" element={<Databases />} />
        <Route path="files" element={<Files />} />
        <Route path="ssl" element={<Ssl />} />
        <Route path="apps" element={<Apps />} />
        <Route path="backups" element={<Backups />} />
        <Route path="settings" element={<Settings />} />
      </Route>

      {/* Catch all */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
