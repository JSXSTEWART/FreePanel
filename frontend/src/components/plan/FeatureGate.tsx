import { ReactNode } from 'react'
import { usePlan } from '../../hooks/usePlan'
import { LockClosedIcon, SparklesIcon } from '@heroicons/react/24/outline'
import Button from '../common/Button'

interface FeatureGateProps {
  feature: string
  children: ReactNode
  fallback?: ReactNode
  showUpgrade?: boolean
  upgradeMessage?: string
}

export function FeatureGate({
  feature,
  children,
  fallback,
  showUpgrade = true,
  upgradeMessage,
}: FeatureGateProps) {
  const { hasFeature, loading, plan } = usePlan()

  if (loading) {
    return (
      <div className="animate-pulse bg-gray-100 rounded-lg p-4 h-32" />
    )
  }

  if (hasFeature(feature)) {
    return <>{children}</>
  }

  if (fallback) {
    return <>{fallback}</>
  }

  if (!showUpgrade) {
    return null
  }

  return (
    <div className="relative">
      {/* Blurred content overlay */}
      <div className="absolute inset-0 bg-white/80 backdrop-blur-sm z-10 rounded-lg" />

      {/* Upgrade prompt */}
      <div className="absolute inset-0 z-20 flex items-center justify-center">
        <div className="text-center p-6 max-w-sm">
          <div className="mx-auto w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center mb-4">
            <LockClosedIcon className="w-6 h-6 text-primary-600" />
          </div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            Pro Feature
          </h3>
          <p className="text-sm text-gray-600 mb-4">
            {upgradeMessage || `This feature requires a Pro plan or higher.`}
          </p>
          <Button variant="primary" size="sm">
            <SparklesIcon className="w-4 h-4 mr-2" />
            Upgrade Plan
          </Button>
          {plan && (
            <p className="text-xs text-gray-500 mt-2">
              Current plan: {plan.name}
            </p>
          )}
        </div>
      </div>

      {/* Placeholder content */}
      <div className="opacity-30 pointer-events-none">
        {children}
      </div>
    </div>
  )
}

export default FeatureGate
