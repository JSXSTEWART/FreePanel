import { Fragment } from 'react'
import { Dialog, Transition } from '@headlessui/react'
import {
  ExclamationTriangleIcon,
  TrashIcon,
  CheckCircleIcon,
  InformationCircleIcon,
} from '@heroicons/react/24/outline'
import Button from './Button'
import { clsx } from 'clsx'

interface ConfirmDialogProps {
  isOpen: boolean
  onClose: () => void
  onConfirm: () => void
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'danger' | 'warning' | 'info' | 'success'
  isLoading?: boolean
}

const variantConfig = {
  danger: {
    icon: TrashIcon,
    iconBg: 'bg-red-100',
    iconColor: 'text-red-600',
    buttonVariant: 'danger' as const,
  },
  warning: {
    icon: ExclamationTriangleIcon,
    iconBg: 'bg-yellow-100',
    iconColor: 'text-yellow-600',
    buttonVariant: 'primary' as const,
  },
  info: {
    icon: InformationCircleIcon,
    iconBg: 'bg-blue-100',
    iconColor: 'text-blue-600',
    buttonVariant: 'primary' as const,
  },
  success: {
    icon: CheckCircleIcon,
    iconBg: 'bg-green-100',
    iconColor: 'text-green-600',
    buttonVariant: 'success' as const,
  },
}

export default function ConfirmDialog({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  variant = 'danger',
  isLoading = false,
}: ConfirmDialogProps) {
  const config = variantConfig[variant]
  const Icon = config.icon

  const handleConfirm = () => {
    onConfirm()
  }

  return (
    <Transition appear show={isOpen} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        {/* Backdrop */}
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" />
        </Transition.Child>

        {/* Dialog */}
        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-md transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all">
                <div className="p-6">
                  <div className="flex items-start gap-4">
                    {/* Icon */}
                    <div className={clsx('flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center', config.iconBg)}>
                      <Icon className={clsx('w-6 h-6', config.iconColor)} />
                    </div>

                    {/* Content */}
                    <div className="flex-1 min-w-0">
                      <Dialog.Title className="text-lg font-semibold text-gray-900">
                        {title}
                      </Dialog.Title>
                      <Dialog.Description className="mt-2 text-sm text-gray-500">
                        {message}
                      </Dialog.Description>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="mt-6 flex justify-end gap-3">
                    <Button
                      variant="secondary"
                      onClick={onClose}
                      disabled={isLoading}
                    >
                      {cancelLabel}
                    </Button>
                    <Button
                      variant={config.buttonVariant}
                      onClick={handleConfirm}
                      isLoading={isLoading}
                    >
                      {confirmLabel}
                    </Button>
                  </div>
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  )
}
