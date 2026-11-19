import { useQueryClient } from '@tanstack/react-query'
import { useRef, useState } from 'react'
import { NavLink, Outlet, useMatch } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'
import { useLeadSummary } from '../features/inbox/api'
import { channelsFor, useRealtimeConnected, useRealtimeEvent } from '../lib/realtime'
import { ROLE_LABELS, type Message } from '../lib/types'
import { NotificationBell } from './NotificationBell'
import { Button, focusRing } from './ui'

export function AppLayout() {
  const { user, logout } = useAuth()
  const queryClient = useQueryClient()
  const [loggingOut, setLoggingOut] = useState(false)
  const inboxRoute = useMatch('/')
  const leadRoute = useMatch('/leads/:leadId')
  const fullBleed = !!(inboxRoute || leadRoute)
  const summary = useLeadSummary(!useRealtimeConnected())
  const channels = user ? channelsFor(user) : []

  useRealtimeEvent<{ message: Message }>(channels, 'message.created', ({ message }) => {
    queryClient.invalidateQueries({ queryKey: ['leads'] })
    queryClient.invalidateQueries({ queryKey: ['lead-summary'] })
    if (message.lead_id) {
      queryClient.invalidateQueries({ queryKey: ['messages', message.lead_id] })
      queryClient.invalidateQueries({ queryKey: ['lead', message.lead_id] })
    }
  })
  useRealtimeEvent<{ message: Message }>(channels, 'message.updated', ({ message }) => {
    if (message.lead_id) queryClient.invalidateQueries({ queryKey: ['messages', message.lead_id] })
  })
  const lastLeadEvent = useRef({ id: 0, at: 0 })
  const leadChannels = user ? [...new Set([...channels, `users.${user.id}`])] : []
  useRealtimeEvent<{ lead_id: number }>(leadChannels, 'lead.updated', ({ lead_id }) => {
    const last = lastLeadEvent.current
    if (last.id === lead_id && Date.now() - last.at < 1000) return
    lastLeadEvent.current = { id: lead_id, at: Date.now() }
    queryClient.invalidateQueries({ queryKey: ['leads'] })
    queryClient.invalidateQueries({ queryKey: ['lead-summary'] })
    queryClient.invalidateQueries({ queryKey: ['lead', lead_id] })
    queryClient.invalidateQueries({ queryKey: ['lead-activity', lead_id] })
  })

  const unread = summary.data?.unread ?? 0
  const links = [
    { to: '/', label: 'Inbox', end: false, count: unread },
    ...(user?.role !== 'assignee'
      ? [
          { to: '/imports', label: 'Imports', end: false, count: 0 },
          { to: '/reports', label: 'Reports', end: false, count: 0 },
        ]
      : []),
    ...(user?.role === 'super_admin'
      ? [
          { to: '/settings', label: 'Settings', end: false, count: 0 },
          ...(import.meta.env.DEV ? [{ to: '/simulator', label: 'Simulator', end: false, count: 0 }] : []),
        ]
      : []),
  ]

  return (
    <div className={`flex flex-col ${fullBleed ? 'h-dvh' : 'min-h-dvh'}`}>
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:p-2">
        Skip to content
      </a>
      <header className={`shrink-0 border-b border-gray-200 bg-white ${leadRoute ? 'hidden lg:block' : ''}`}>
        <div className={`mx-auto flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-2 ${fullBleed ? '' : 'max-w-6xl'}`}>
          <span className="text-lg font-extrabold text-brand-600">Funky Monkey Leads</span>
          <nav aria-label="Main" className="order-last flex w-full gap-1 overflow-x-auto sm:order-0 sm:w-auto">
            {links.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                end={link.to === '/' ? !leadRoute : link.end}
                className={({ isActive }) =>
                  `flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold ${focusRing} ${
                    isActive || (link.to === '/' && leadRoute) ? 'bg-brand-50 text-brand-700' : 'text-gray-700 hover:bg-gray-100'
                  }`
                }
              >
                {link.label}
                {link.count > 0 && (
                  <span className="rounded-full bg-brand-600 px-1.5 text-xs font-bold text-white">
                    {link.count}
                    <span className="sr-only"> unread conversations</span>
                  </span>
                )}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-3">
            <NotificationBell />
            <div className="hidden text-right text-sm sm:block">
              <p className="font-semibold text-gray-900">{user?.name}</p>
              <p className="text-xs text-gray-600">
                {user && ROLE_LABELS[user.role]}
                {user?.branch && ` · ${user.branch.name}`}
              </p>
            </div>
            <Button
              variant="secondary"
              loading={loggingOut}
              onClick={async () => {
                setLoggingOut(true)
                await logout().catch(() => setLoggingOut(false))
              }}
            >
              Log out
            </Button>
          </div>
        </div>
      </header>
      <main id="main" className={fullBleed ? 'min-h-0 flex-1' : 'mx-auto w-full max-w-6xl px-4 py-6'}>
        <Outlet />
      </main>
    </div>
  )
}
