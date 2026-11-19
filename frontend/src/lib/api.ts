import axios, { isAxiosError } from 'axios'

export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export const csrf = () => axios.get('/sanctum/csrf-cookie', { withCredentials: true })

let onUnauthorized: (() => void) | null = null

export function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler
}

api.interceptors.response.use(undefined, (error) => {
  if (isAxiosError(error) && [401, 419].includes(error.response?.status ?? 0)) onUnauthorized?.()
  return Promise.reject(error)
})

export type FieldErrors = Record<string, string | undefined>

export function fieldErrors(error: unknown): FieldErrors {
  if (!isAxiosError(error) || error.response?.status !== 422) return {}
  const errors: Record<string, string[]> = error.response.data?.errors ?? {}
  return Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]]))
}

export function errorMessage(error: unknown): string {
  if (isAxiosError(error)) {
    const status = error.response?.status
    if (!error.response) return 'Network error. Check your connection and try again.'
    if (status === 403) return 'You do not have permission to do that.'
    if (status && status >= 500 && status !== 502) return 'Something went wrong on the server. Please try again.'
    const message = error.response.data?.message
    if (typeof message === 'string' && message) return message
  }
  return 'Something went wrong. Please try again.'
}
