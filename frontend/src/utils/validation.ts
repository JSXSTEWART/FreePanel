/**
 * Common validation utilities
 */

export interface ValidationResult {
  valid: boolean
  error?: string
}

/**
 * Validate password strength
 */
export function validatePassword(password: string, minLength = 8): ValidationResult {
  if (!password) {
    return { valid: false, error: 'Password is required' }
  }

  if (password.length < minLength) {
    return { valid: false, error: `Password must be at least ${minLength} characters` }
  }

  return { valid: true }
}

/**
 * Validate password confirmation matches
 */
export function validatePasswordMatch(password: string, confirmation: string): ValidationResult {
  if (password !== confirmation) {
    return { valid: false, error: 'Passwords do not match' }
  }

  return { valid: true }
}

/**
 * Validate email format
 */
export function validateEmail(email: string): ValidationResult {
  if (!email) {
    return { valid: false, error: 'Email is required' }
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(email)) {
    return { valid: false, error: 'Invalid email format' }
  }

  return { valid: true }
}

/**
 * Validate domain format
 */
export function validateDomain(domain: string): ValidationResult {
  if (!domain) {
    return { valid: false, error: 'Domain is required' }
  }

  const domainRegex = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i
  if (!domainRegex.test(domain)) {
    return { valid: false, error: 'Invalid domain format' }
  }

  return { valid: true }
}

/**
 * Validate username format
 */
export function validateUsername(username: string, minLength = 3, maxLength = 32): ValidationResult {
  if (!username) {
    return { valid: false, error: 'Username is required' }
  }

  if (username.length < minLength) {
    return { valid: false, error: `Username must be at least ${minLength} characters` }
  }

  if (username.length > maxLength) {
    return { valid: false, error: `Username must be at most ${maxLength} characters` }
  }

  const usernameRegex = /^[a-z][a-z0-9_]*$/i
  if (!usernameRegex.test(username)) {
    return { valid: false, error: 'Username must start with a letter and contain only letters, numbers, and underscores' }
  }

  return { valid: true }
}

/**
 * Validate required field
 */
export function validateRequired(value: string, fieldName = 'This field'): ValidationResult {
  if (!value || value.trim() === '') {
    return { valid: false, error: `${fieldName} is required` }
  }

  return { valid: true }
}

/**
 * Validate numeric value
 */
export function validateNumber(value: string | number, min?: number, max?: number): ValidationResult {
  const num = typeof value === 'string' ? parseFloat(value) : value

  if (isNaN(num)) {
    return { valid: false, error: 'Must be a valid number' }
  }

  if (min !== undefined && num < min) {
    return { valid: false, error: `Must be at least ${min}` }
  }

  if (max !== undefined && num > max) {
    return { valid: false, error: `Must be at most ${max}` }
  }

  return { valid: true }
}
