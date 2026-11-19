import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useEffect, useRef, useState } from 'react'
import { api } from './api'
import type { User } from './types'

const env = import.meta.env
const key = env.VITE_REVERB_APP_KEY as string | undefined

export const echo = key
  ? new Echo<'reverb'>({
      broadcaster: 'reverb',
      key,
      wsHost: env.VITE_REVERB_HOST || window.location.hostname,
      wsPort: Number(env.VITE_REVERB_PORT || 8080),
      wssPort: Number(env.VITE_REVERB_PORT || 443),
      forceTLS: env.VITE_REVERB_SCHEME === 'https',
      enabledTransports: ['ws', 'wss'],
      Pusher,
      authorizer: (channel: { name: string }) => ({
        authorize: (socketId: string, callback: (error: Error | null, data: { auth: string } | null) => void) => {
          api
            .post<{ auth: string }>('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
            .then((response) => callback(null, response.data))
            .catch((error) => callback(error, null))
        },
      }),
    })
  : null

// Lets the server skip echoing an update back to the browser that caused it (broadcast()->toOthers()).
api.interceptors.request.use((config) => {
  const socketId = echo?.socketId()
  if (socketId) config.headers.set('X-Socket-ID', socketId)
  return config
})

export function channelsFor(user: User): string[] {
  if (user.role !== 'assignee') return ['admins']
  return [`users.${user.id}`, ...(user.branch_id ? [`branches.${user.branch_id}`] : [])]
}

/** True while the WebSocket is connected; callers fall back to polling otherwise. */
export function useRealtimeConnected(): boolean {
  const [connected, setConnected] = useState(() => echo?.connector.pusher.connection.state === 'connected')

  useEffect(() => {
    if (!echo) return
    const connection = echo.connector.pusher.connection
    const onChange = ({ current }: { current: string }) => setConnected(current === 'connected')
    connection.bind('state_change', onChange)
    return () => {
      connection.unbind('state_change', onChange)
    }
  }, [])

  return connected
}

export function useRealtimeEvent<T>(channels: string[], event: string, handler: (payload: T) => void) {
  const handlerRef = useRef(handler)
  useEffect(() => {
    handlerRef.current = handler
  })

  const channelKey = channels.join(',')
  useEffect(() => {
    if (!echo || !channelKey) return
    const names = channelKey.split(',')
    const listener = (payload: T) => handlerRef.current(payload)
    names.forEach((name) => echo.private(name).listen(`.${event}`, listener))
    return () => names.forEach((name) => echo.private(name).stopListening(`.${event}`, listener))
  }, [channelKey, event])
}
