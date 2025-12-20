import { clsx } from 'clsx'
import { SparklesIcon, StarIcon } from '@heroicons/react/24/solid'

interface PlanBadgeProps {
  plan: 'free' | 'pro' | 'business' | 'enterprise' | string
  size?: 'sm' | 'md' | 'lg'
  showIcon?: boolean
}

export function PlanBadge({ plan, size = 'md', showIcon = true }: PlanBadgeProps) {
  const planConfig: Record<string, { label: string; color: string; icon: typeof SparklesIcon }> = {
    free: {
      label: 'Free',
      color: 'bg-gray-100 text-gray-700',
      icon: StarIcon,
    },
    pro: {
      label: 'Pro',
      color: 'bg-primary-100 text-primary-700',
      icon: SparklesIcon,
    },
    business: {
      label: 'Business',
      color: 'bg-purple-100 text-purple-700',
      icon: SparklesIcon,
    },
    enterprise: {
      label: 'Enterprise',
      color: 'bg-amber-100 text-amber-700',
      icon: SparklesIcon,
    },
  }

  const config = planConfig[plan.toLowerCase()] || planConfig.free

  const sizes = {
    sm: 'text-xs px-2 py-0.5',
    md: 'text-sm px-2.5 py-1',
    lg: 'text-base px-3 py-1.5',
  }

  const iconSizes = {
    sm: 'w-3 h-3',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
  }

  const Icon = config.icon

  return (
    <span
      className={clsx(
        'inline-flex items-center font-medium rounded-full',
        config.color,
        sizes[size]
      )}
    >
      {showIcon && <Icon className={clsx(iconSizes[size], 'mr-1')} />}
      {config.label}
    </span>
  )
}

export default PlanBadge
