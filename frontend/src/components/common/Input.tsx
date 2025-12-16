import { forwardRef, useState } from 'react'
import { clsx } from 'clsx'
import { EyeIcon, EyeSlashIcon, CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/react/24/outline'

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  hint?: string
  leftIcon?: React.ComponentType<{ className?: string }>
  rightIcon?: React.ComponentType<{ className?: string }>
  showPasswordToggle?: boolean
  success?: boolean
}

const Input = forwardRef<HTMLInputElement, InputProps>(
  (
    {
      label,
      error,
      hint,
      leftIcon: LeftIcon,
      rightIcon: RightIcon,
      showPasswordToggle = false,
      success = false,
      className,
      type = 'text',
      id,
      ...props
    },
    ref
  ) => {
    const [showPassword, setShowPassword] = useState(false)

    const inputId = id || label?.toLowerCase().replace(/\s+/g, '-')
    const isPassword = type === 'password'
    const inputType = isPassword && showPassword ? 'text' : type

    const hasRightElement = showPasswordToggle || RightIcon || success || error

    return (
      <div className={className}>
        {label && (
          <label htmlFor={inputId} className="label">
            {label}
          </label>
        )}
        <div className="relative">
          {LeftIcon && (
            <div className="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
              <LeftIcon className="w-5 h-5 text-gray-400" />
            </div>
          )}
          <input
            ref={ref}
            id={inputId}
            type={inputType}
            className={clsx(
              'input transition-colors duration-200',
              LeftIcon && 'pl-10',
              hasRightElement && 'pr-10',
              error && 'input-error',
              success && !error && 'border-green-500 focus:border-green-500 focus:ring-green-500'
            )}
            aria-invalid={error ? 'true' : 'false'}
            aria-describedby={error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined}
            {...props}
          />
          {hasRightElement && (
            <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1">
              {isPassword && showPasswordToggle && (
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="p-1 text-gray-400 hover:text-gray-600 transition-colors"
                  tabIndex={-1}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? (
                    <EyeSlashIcon className="w-5 h-5" />
                  ) : (
                    <EyeIcon className="w-5 h-5" />
                  )}
                </button>
              )}
              {success && !error && !showPasswordToggle && (
                <CheckCircleIcon className="w-5 h-5 text-green-500" />
              )}
              {error && !showPasswordToggle && (
                <ExclamationCircleIcon className="w-5 h-5 text-red-500" />
              )}
              {RightIcon && !showPasswordToggle && !error && !success && (
                <RightIcon className="w-5 h-5 text-gray-400" />
              )}
            </div>
          )}
        </div>
        {hint && !error && (
          <p id={`${inputId}-hint`} className="mt-1.5 text-sm text-gray-500">
            {hint}
          </p>
        )}
        {error && (
          <p id={`${inputId}-error`} className="error-text" role="alert">
            {error}
          </p>
        )}
      </div>
    )
  }
)

Input.displayName = 'Input'

export default Input

// Password strength indicator
interface PasswordStrengthProps {
  password: string
  className?: string
}

export function PasswordStrength({ password, className }: PasswordStrengthProps) {
  const getStrength = (pwd: string): { score: number; label: string; color: string } => {
    let score = 0

    if (pwd.length >= 8) score++
    if (pwd.length >= 12) score++
    if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score++
    if (/\d/.test(pwd)) score++
    if (/[^a-zA-Z0-9]/.test(pwd)) score++

    if (score <= 1) return { score: 1, label: 'Weak', color: 'bg-red-500' }
    if (score <= 2) return { score: 2, label: 'Fair', color: 'bg-yellow-500' }
    if (score <= 3) return { score: 3, label: 'Good', color: 'bg-blue-500' }
    return { score: 4, label: 'Strong', color: 'bg-green-500' }
  }

  if (!password) return null

  const strength = getStrength(password)

  return (
    <div className={clsx('mt-2', className)}>
      <div className="flex gap-1 mb-1">
        {[1, 2, 3, 4].map((level) => (
          <div
            key={level}
            className={clsx(
              'h-1 flex-1 rounded-full transition-colors duration-200',
              level <= strength.score ? strength.color : 'bg-gray-200'
            )}
          />
        ))}
      </div>
      <p className={clsx('text-xs', {
        'text-red-600': strength.score === 1,
        'text-yellow-600': strength.score === 2,
        'text-blue-600': strength.score === 3,
        'text-green-600': strength.score === 4,
      })}>
        Password strength: {strength.label}
      </p>
    </div>
  )
}
