import { clsx } from 'clsx'
import {
  FolderIcon,
  InboxIcon,
  MagnifyingGlassIcon,
  ExclamationTriangleIcon,
  PlusCircleIcon,
} from '@heroicons/react/24/outline'
import Button from './Button'

interface EmptyStateProps {
  icon?: React.ComponentType<{ className?: string }>
  title: string
  description?: string
  action?: {
    label: string
    onClick: () => void
    icon?: React.ComponentType<{ className?: string }>
  }
  variant?: 'default' | 'search' | 'error' | 'folder'
  className?: string
}

const defaultIcons = {
  default: InboxIcon,
  search: MagnifyingGlassIcon,
  error: ExclamationTriangleIcon,
  folder: FolderIcon,
}

export default function EmptyState({
  icon,
  title,
  description,
  action,
  variant = 'default',
  className,
}: EmptyStateProps) {
  const Icon = icon || defaultIcons[variant]
  const ActionIcon = action?.icon || PlusCircleIcon

  return (
    <div className={clsx('text-center py-12 px-4', className)}>
      <div className="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
        <Icon className="w-8 h-8 text-gray-400" />
      </div>
      <h3 className="text-lg font-medium text-gray-900 mb-1">{title}</h3>
      {description && (
        <p className="text-sm text-gray-500 max-w-sm mx-auto mb-6">{description}</p>
      )}
      {action && (
        <Button variant="primary" onClick={action.onClick}>
          <ActionIcon className="w-5 h-5 mr-2" />
          {action.label}
        </Button>
      )}
    </div>
  )
}

// Pre-built empty states for common scenarios
export function NoDataEmptyState({
  resourceName,
  onAdd
}: {
  resourceName: string
  onAdd?: () => void
}) {
  return (
    <EmptyState
      title={`No ${resourceName} found`}
      description={`Get started by creating your first ${resourceName.toLowerCase()}.`}
      action={onAdd ? { label: `Add ${resourceName}`, onClick: onAdd } : undefined}
    />
  )
}

export function SearchEmptyState({ query }: { query: string }) {
  return (
    <EmptyState
      variant="search"
      title="No results found"
      description={`We couldn't find anything matching "${query}". Try adjusting your search.`}
    />
  )
}

export function ErrorEmptyState({
  onRetry
}: {
  onRetry?: () => void
}) {
  return (
    <EmptyState
      variant="error"
      title="Something went wrong"
      description="We encountered an error while loading this data. Please try again."
      action={onRetry ? { label: 'Try again', onClick: onRetry } : undefined}
    />
  )
}
