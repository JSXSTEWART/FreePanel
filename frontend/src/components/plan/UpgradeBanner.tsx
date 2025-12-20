import { XMarkIcon, SparklesIcon, ArrowRightIcon } from '@heroicons/react/24/outline'
import { useState } from 'react'
import Button from '../common/Button'

interface UpgradeBannerProps {
  title?: string
  message?: string
  dismissible?: boolean
  variant?: 'info' | 'warning' | 'success'
  feature?: string
}

export function UpgradeBanner({
  title = 'Upgrade to Pro',
  message = 'Unlock premium features and remove limits with a Pro plan.',
  dismissible = true,
  variant = 'info',
  feature,
}: UpgradeBannerProps) {
  const [dismissed, setDismissed] = useState(false)

  if (dismissed) return null

  const variants = {
    info: {
      bg: 'bg-gradient-to-r from-primary-500 to-primary-600',
      text: 'text-white',
      button: 'bg-white text-primary-600 hover:bg-primary-50',
    },
    warning: {
      bg: 'bg-gradient-to-r from-amber-500 to-orange-500',
      text: 'text-white',
      button: 'bg-white text-amber-600 hover:bg-amber-50',
    },
    success: {
      bg: 'bg-gradient-to-r from-emerald-500 to-teal-500',
      text: 'text-white',
      button: 'bg-white text-emerald-600 hover:bg-emerald-50',
    },
  }

  const style = variants[variant]

  return (
    <div className={`${style.bg} ${style.text} rounded-lg p-4 relative`}>
      {dismissible && (
        <button
          onClick={() => setDismissed(true)}
          className="absolute top-2 right-2 p-1 rounded-full hover:bg-white/20 transition-colors"
        >
          <XMarkIcon className="w-5 h-5" />
        </button>
      )}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div className="flex items-center gap-3">
          <div className="p-2 bg-white/20 rounded-lg">
            <SparklesIcon className="w-6 h-6" />
          </div>
          <div>
            <h3 className="font-semibold">{title}</h3>
            <p className="text-sm opacity-90">
              {feature ? `Unlock ${feature} and more premium features.` : message}
            </p>
          </div>
        </div>
        <Button
          variant="secondary"
          size="sm"
          className={`${style.button} border-0 shadow-sm`}
        >
          View Plans
          <ArrowRightIcon className="w-4 h-4 ml-2" />
        </Button>
      </div>
    </div>
  )
}

export default UpgradeBanner
