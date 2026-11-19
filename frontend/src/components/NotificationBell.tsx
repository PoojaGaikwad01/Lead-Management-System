import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'
import { api } from '../lib/api'
import { dateTime } from '../lib/format'
import { useRealtimeConnected, useRealtimeEvent } from '../lib/realtime'
import type { AppNotification } from '../lib/types'
import { BellIcon } from './icons'
import { useToast } from './Toast'
import { focusRing } from './ui'

const NOTIFICATION_EVENT = 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated'

export function NotificationBell() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const toast = useToast()
  const [open, setOpen] = useState(false)
  const panel = useRef<HTMLDivElement>(null)
  const connected = useRealtimeConnected()

  const notifications = useQuery({
    queryKey: ['notifications'],
    queryFn: async () => (await api.get<{ data: AppNotification[]; unread: number }>('/notifications')).data,
    refetchInterval: connected ? false : 30_000,
  })

  const markRead = useMutation({
    mutationFn: (ids?: string[]) => api.post('/notifications/read', { ids }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  })

  useRealtimeEvent<{ title: string }>(user ? [`users.${user.id}`] : [], NOTIFICATION_EVENT, (n) => {
    queryClient.invalidateQueries({ queryKey: ['notifications'] })
    toast(n.title)
  })

  useEffect(() => {
    if (!open) return
    const onClick = (e: MouseEvent) => !panel.current?.contains(e.target as Node) && setOpen(false)
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false)
    document.addEventListener('mousedown', onClick)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onClick)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  const unread = notifications.data?.unread ?? 0
  const items = notifications.data?.data ?? []

  const openItem = (item: AppNotification) => {
    if (!item.read_at) markRead.mutate([item.id])
    setOpen(false)
    navigate(`/leads/${item.lead_id}`)
  }

  return (
    <div ref={panel} className="relative">
      <button
        type="button"
        aria-expanded={open}
        aria-label={`Notifications${unread ? `, ${unread} unread` : ''}`}
        onClick={() => setOpen((v) => !v)}
        className={`relative grid size-11 place-items-center rounded-lg text-gray-700 hover:bg-gray-100 ${focusRing}`}
      >
        <BellIcon />
        {unread > 0 && (
          <span className="absolute right-1 top-1 min-w-5 rounded-full bg-red-700 px-1 text-center text-xs font-bold text-white">{unread}</span>
        )}
      </button>
      {open && (
        <div className="absolute right-0 z-40 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
          <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2">
            <h2 className="font-bold">Notifications</h2>
            {unread > 0 && (
              <button type="button" onClick={() => markRead.mutate(undefined)} className={`rounded text-sm font-semibold text-brand-700 ${focusRing}`}>
                Mark all read
              </button>
            )}
          </div>
          {items.length === 0 ? (
            <p className="p-4 text-sm text-gray-600">You're all caught up.</p>
          ) : (
            <ul className="max-h-96 divide-y divide-gray-100 overflow-y-auto">
              {items.map((item) => (
                <li key={item.id}>
                  <button
                    type="button"
                    onClick={() => openItem(item)}
                    className={`flex w-full gap-2 px-4 py-3 text-left hover:bg-gray-50 ${focusRing} ${item.read_at ? '' : 'bg-brand-50'}`}
                  >
                    {!item.read_at && <span className="mt-1.5 size-2 shrink-0 rounded-full bg-brand-600" aria-label="Unread" />}
                    <span className="min-w-0">
                      <span className="block text-sm font-semibold text-gray-900">{item.title}</span>
                      <span className="block text-sm text-gray-700">{item.body}</span>
                      <span className="block text-xs text-gray-600">{dateTime(item.created_at)}</span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
