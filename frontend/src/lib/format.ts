import { useEffect, useState } from 'react'

const LOCALE = 'en-IN'

const sameDay = (a: Date, b: Date) => a.toDateString() === b.toDateString()

function yesterday(): Date {
  const d = new Date()
  d.setDate(d.getDate() - 1)
  return d
}

export function timeOfDay(iso: string): string {
  return new Date(iso).toLocaleTimeString(LOCALE, { hour: '2-digit', minute: '2-digit' })
}

export function listTime(iso: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (sameDay(date, new Date())) return timeOfDay(iso)
  if (sameDay(date, yesterday())) return 'Yesterday'
  if (Date.now() - date.getTime() < 6 * 86_400_000) return date.toLocaleDateString(LOCALE, { weekday: 'short' })
  return date.toLocaleDateString(LOCALE, { day: '2-digit', month: '2-digit', year: '2-digit' })
}

export function dayLabel(iso: string): string {
  const date = new Date(iso)
  if (sameDay(date, new Date())) return 'Today'
  if (sameDay(date, yesterday())) return 'Yesterday'
  return date.toLocaleDateString(LOCALE, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}

export function dateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString(LOCALE, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

export function duration(ms: number): string {
  const minutes = Math.max(1, Math.round(ms / 60_000))
  if (minutes < 60) return `${minutes} min`
  const hours = Math.round(minutes / 60)
  if (hours < 48) return `${hours} ${hours === 1 ? 'hr' : 'hrs'}`
  return `${Math.round(hours / 24)} days`
}

/** Re-renders every `intervalMs` so relative times ("3 hrs overdue") stay current. */
export function useNow(intervalMs = 60_000): number {
  const [now, setNow] = useState(() => Date.now())
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), intervalMs)
    return () => clearInterval(id)
  }, [intervalMs])
  return now
}

export function initials(name: string | null, fallback: string): string {
  const source = (name ?? '').trim()
  if (!source) return fallback.slice(-2)
  return source
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}
