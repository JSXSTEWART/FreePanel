import { clsx } from 'clsx'

interface QuotaBarProps {
  used: number
  limit: number
  label?: string
  showValues?: boolean
  formatValue?: (value: number) => string
  size?: 'sm' | 'md' | 'lg'
  showWarning?: boolean
  warningThreshold?: number
  dangerThreshold?: number
}

export function QuotaBar({
  used,
  limit,
  label,
  showValues = true,
  formatValue,
  size = 'md',
  showWarning = true,
  warningThreshold = 75,
  dangerThreshold = 90,
}: QuotaBarProps) {
  // Calculate percentage (handle unlimited case where limit is 0)
  const percentage = limit === 0 ? 0 : Math.min(100, (used / limit) * 100)
  const isUnlimited = limit === 0

  // Determine color based on usage
  const getColor = () => {
    if (isUnlimited) return 'bg-green-500'
    if (percentage >= dangerThreshold) return 'bg-red-500'
    if (percentage >= warningThreshold) return 'bg-amber-500'
    return 'bg-primary-500'
  }

  const formatDefault = (value: number) => {
    if (value === 0 && limit === 0) return 'Unlimited'
    if (value >= 1073741824) return `${(value / 1073741824).toFixed(2)} GB`
    if (value >= 1048576) return `${(value / 1048576).toFixed(2)} MB`
    if (value >= 1024) return `${(value / 1024).toFixed(2)} KB`
    return `${value}`
  }

  const format = formatValue || formatDefault

  const heights = {
    sm: 'h-1.5',
    md: 'h-2',
    lg: 'h-3',
  }

  return (
    <div className="w-full">
      {(label || showValues) && (
        <div className="flex justify-between items-center mb-1">
          {label && (
            <span className="text-sm font-medium text-gray-700">{label}</span>
          )}
          {showValues && (
            <span className="text-sm text-gray-500">
              {format(used)} / {isUnlimited ? 'Unlimited' : format(limit)}
            </span>
          )}
        </div>
      )}
      <div
        className={clsx(
          'w-full bg-gray-200 rounded-full overflow-hidden',
          heights[size]
        )}
      >
        <div
          className={clsx(
            'h-full rounded-full transition-all duration-300',
            getColor()
          )}
          style={{ width: isUnlimited ? '0%' : `${percentage}%` }}
        />
      </div>
      {showWarning && percentage >= warningThreshold && !isUnlimited && (
        <p
          className={clsx(
            'text-xs mt-1',
            percentage >= dangerThreshold ? 'text-red-600' : 'text-amber-600'
          )}
        >
          {percentage >= dangerThreshold
            ? 'Quota almost exhausted!'
            : 'Approaching quota limit'}
        </p>
      )}
    </div>
  )
}

export default QuotaBar
