import { useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { createContext, useContext, useEffect, type ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { ErrorState } from '../../components/ui'
import { api, csrf, errorMessage, setUnauthorizedHandler } from '../../lib/api'
import { echo } from '../../lib/realtime'
import type { Role, User } from '../../lib/types'

interface AuthValue {
  user: User | null
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthValue | null>(null)

async function fetchMe(): Promise<User | null> {
  try {
    return (await api.get<{ data: User }>('/auth/me')).data.data
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 401) return null
    throw error
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const me = useQuery({ queryKey: ['me'], queryFn: fetchMe, retry: false, staleTime: Infinity })

  useEffect(() => {
    setUnauthorizedHandler(() => queryClient.setQueryData(['me'], null))
    return () => setUnauthorizedHandler(null)
  }, [queryClient])

  const login = async (email: string, password: string) => {
    await csrf()
    const { data } = await api.post<{ data: User }>('/auth/login', { email, password })
    queryClient.setQueryData(['me'], data.data)
  }

  const logout = async () => {
    try {
      await api.post('/auth/logout')
    } finally {
      echo?.leaveAllChannels()
      queryClient.clear()
      queryClient.setQueryData(['me'], null)
    }
  }

  if (me.isPending) {
    return (
      <div role="status" className="grid min-h-screen place-items-center text-sm text-gray-600">
        Loading…
      </div>
    )
  }

  if (me.isError) {
    return (
      <div className="mx-auto max-w-md p-6 pt-24">
        <ErrorState message={errorMessage(me.error)} onRetry={() => me.refetch()} />
      </div>
    )
  }

  return <AuthContext.Provider value={{ user: me.data ?? null, login, logout }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const value = useContext(AuthContext)
  if (!value) throw new Error('useAuth must be used inside AuthProvider')
  return value
}

export function RequireAuth() {
  const { user } = useAuth()
  const location = useLocation()
  return user ? <Outlet /> : <Navigate to="/login" replace state={{ from: location.pathname }} />
}

export function RequireRole({ roles }: { roles: Role[] }) {
  const { user } = useAuth()
  return user && roles.includes(user.role) ? <Outlet /> : <Navigate to="/" replace />
}
