import { clsx } from 'clsx'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/react/24/outline'

interface Column<T> {
  key: string
  header: string
  render?: (item: T) => React.ReactNode
  className?: string
  headerClassName?: string
}

interface TableProps<T> {
  data: T[]
  columns: Column<T>[]
  keyExtractor: (item: T) => string | number
  isLoading?: boolean
  emptyState?: React.ReactNode
  onRowClick?: (item: T) => void
  className?: string
  stickyHeader?: boolean
}

export default function Table<T>({
  data,
  columns,
  keyExtractor,
  isLoading,
  emptyState,
  onRowClick,
  className,
  stickyHeader = false,
}: TableProps<T>) {
  if (isLoading) {
    return (
      <div className={clsx('card overflow-hidden', className)}>
        <div className="p-8 text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto" />
          <p className="mt-2 text-sm text-gray-500">Loading...</p>
        </div>
      </div>
    )
  }

  if (data.length === 0 && emptyState) {
    return (
      <div className={clsx('card', className)}>
        {emptyState}
      </div>
    )
  }

  return (
    <div className={clsx('card overflow-hidden', className)}>
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className={clsx('bg-gray-50', stickyHeader && 'sticky top-0')}>
            <tr>
              {columns.map((column) => (
                <th
                  key={column.key}
                  className={clsx(
                    'px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider',
                    column.headerClassName
                  )}
                >
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {data.map((item) => (
              <tr
                key={keyExtractor(item)}
                className={clsx(
                  'hover:bg-gray-50 transition-colors duration-150',
                  onRowClick && 'cursor-pointer'
                )}
                onClick={() => onRowClick?.(item)}
              >
                {columns.map((column) => (
                  <td
                    key={column.key}
                    className={clsx('px-6 py-4 whitespace-nowrap text-sm', column.className)}
                  >
                    {column.render
                      ? column.render(item)
                      : (item as Record<string, unknown>)[column.key]?.toString()}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

// Pagination component
interface PaginationProps {
  currentPage: number
  totalPages: number
  onPageChange: (page: number) => void
  totalItems?: number
  itemsPerPage?: number
  showItemCount?: boolean
  className?: string
}

export function Pagination({
  currentPage,
  totalPages,
  onPageChange,
  totalItems,
  itemsPerPage,
  showItemCount = true,
  className,
}: PaginationProps) {
  const startItem = totalItems && itemsPerPage ? (currentPage - 1) * itemsPerPage + 1 : 0
  const endItem = totalItems && itemsPerPage ? Math.min(currentPage * itemsPerPage, totalItems) : 0

  const getPageNumbers = () => {
    const pages: (number | 'ellipsis')[] = []
    const showPages = 5

    if (totalPages <= showPages + 2) {
      return Array.from({ length: totalPages }, (_, i) => i + 1)
    }

    pages.push(1)

    if (currentPage > 3) {
      pages.push('ellipsis')
    }

    const start = Math.max(2, currentPage - 1)
    const end = Math.min(totalPages - 1, currentPage + 1)

    for (let i = start; i <= end; i++) {
      pages.push(i)
    }

    if (currentPage < totalPages - 2) {
      pages.push('ellipsis')
    }

    pages.push(totalPages)

    return pages
  }

  if (totalPages <= 1) return null

  return (
    <div className={clsx('flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6', className)}>
      {showItemCount && totalItems !== undefined && (
        <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
          <p className="text-sm text-gray-700">
            Showing <span className="font-medium">{startItem}</span> to{' '}
            <span className="font-medium">{endItem}</span> of{' '}
            <span className="font-medium">{totalItems}</span> results
          </p>
        </div>
      )}

      <nav className="flex items-center gap-1" aria-label="Pagination">
        <button
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage === 1}
          className={clsx(
            'relative inline-flex items-center px-2 py-2 text-sm font-medium rounded-lg transition-colors',
            currentPage === 1
              ? 'text-gray-300 cursor-not-allowed'
              : 'text-gray-500 hover:bg-gray-100'
          )}
          aria-label="Previous page"
        >
          <ChevronLeftIcon className="w-5 h-5" />
        </button>

        {getPageNumbers().map((page, index) =>
          page === 'ellipsis' ? (
            <span key={`ellipsis-${index}`} className="px-3 py-2 text-gray-500">
              ...
            </span>
          ) : (
            <button
              key={page}
              onClick={() => onPageChange(page)}
              className={clsx(
                'relative inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                currentPage === page
                  ? 'bg-primary-600 text-white'
                  : 'text-gray-700 hover:bg-gray-100'
              )}
              aria-current={currentPage === page ? 'page' : undefined}
            >
              {page}
            </button>
          )
        )}

        <button
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
          className={clsx(
            'relative inline-flex items-center px-2 py-2 text-sm font-medium rounded-lg transition-colors',
            currentPage === totalPages
              ? 'text-gray-300 cursor-not-allowed'
              : 'text-gray-500 hover:bg-gray-100'
          )}
          aria-label="Next page"
        >
          <ChevronRightIcon className="w-5 h-5" />
        </button>
      </nav>
    </div>
  )
}
