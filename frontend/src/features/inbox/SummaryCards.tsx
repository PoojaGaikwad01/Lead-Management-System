import type { LeadSummary } from '../../lib/types'

const CARDS: { key: keyof LeadSummary['cards']; label: string; alert?: boolean }[] = [
  { key: 'new_today', label: 'New today' },
  { key: 'unassigned', label: 'Unassigned' },
  { key: 'reply_not_sent', label: 'Reply not sent', alert: true },
  { key: 'converted_this_week', label: 'Converted this week' },
]

export function SummaryCards({ cards, className = '' }: { cards: LeadSummary['cards'] | undefined; className?: string }) {
  return (
    <dl className={`grid grid-cols-2 gap-2 p-3 lg:grid-cols-4 ${className}`}>
      {CARDS.map(({ key, label, alert }) => {
        const value = cards?.[key]
        const highlight = alert && !!value
        return (
          <div
            key={key}
            className={`rounded-xl border px-3 py-2 ${highlight ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white'}`}
          >
            <dt className={`text-xs font-semibold ${highlight ? 'text-red-800' : 'text-gray-600'}`}>{label}</dt>
            <dd className={`text-2xl font-extrabold ${highlight ? 'text-red-800' : 'text-gray-900'}`}>
              {value ?? <span className="inline-block h-7 w-8 animate-pulse rounded bg-gray-200" aria-label="Loading" />}
            </dd>
          </div>
        )
      })}
    </dl>
  )
}
