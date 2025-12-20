/**
 * Error handling utilities with proper TypeScript types
 */

import { AxiosError } from 'axios'

export interface ApiErrorResponse {
  message?: string
  errors?: Record<string, string[]>
}

export type ApiError = AxiosError<ApiErrorResponse>

/**
 * Extract error message from various error types
 */
export function getErrorMessage(error: unknown, fallback = 'An error occurred'): string {
  if (error instanceof AxiosError) {
    return error.response?.data?.message || error.message || fallback
  }

  if (error instanceof Error) {
    return error.message || fallback
  }

  if (typeof error === 'string') {
    return error
  }

  return fallback
}

/**
 * Extract validation errors from API response
 */
export function getValidationErrors(error: unknown): Record<string, string[]> | null {
  if (error instanceof AxiosError && error.response?.data?.errors) {
    return error.response.data.errors
  }
  return null
}

/**
 * Check if error is a specific HTTP status
 */
export function isHttpError(error: unknown, status: number): boolean {
  return error instanceof AxiosError && error.response?.status === status
}

/**
 * Check if error is a validation error (422)
 */
export function isValidationError(error: unknown): boolean {
  return isHttpError(error, 422)
}

/**
 * Check if error is an authentication error (401)
 */
export function isAuthError(error: unknown): boolean {
  return isHttpError(error, 401)
}

/**
 * Check if error is a permission error (403)
 */
export function isPermissionError(error: unknown): boolean {
  return isHttpError(error, 403)
}
