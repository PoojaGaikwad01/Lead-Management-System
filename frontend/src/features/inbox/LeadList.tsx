import { useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { Link } from 'react-router-dom'
import { FilterIcon, SearchIcon } from '../../components/icons'
import { Button, EmptyState, ErrorState, ListSkeleton, SelectField, TextField, focusRing } from '../../components/ui'
import { errorMessage } from '../../lib/api'
import { duration, listTime, useNow } from '../../lib/format'
import { REPLY, SOURCE, STATUS } from '../../lib/labels'
import type { Lead, LeadSummary } from '../../lib/types'
import { useAuth } from '../auth/AuthContext'
import { useBranches } from '../branches/useBranches'
import { EMPTY_FILTERS, useAssignees, useLeads, useLeadTypes, type LeadFilters, type Tab } from './api'
import { Avatar, LeadChips } from './LeadBadges'

const TABS: { key: Tab; label: string }[] = [
  { key: 'new', label: 'New' },
  { key: 'reply_not_sent', label: 'Reply not sent' },
  { key: 'mine', label: 'My leads' },
  { key: 'all', label: 'All' },
]

const FILTER_KEYS = ['lead_type_id', 'source', 'branch_id', 'assigned_to', 'status', 'reply_status', 'from', 'to'] as const

function preview(lead: Lead): string {
  const m = lead.last_message
  if (!m) return 'No messages yet'
  const prefix = m.direction === 'out' ? 'You: ' : ''
  if (m.body) return prefix + m.body
  const media = { image: 'Photo', audio: 'Audio', video: 'Video', document: m.media_name ?? 'Document', text: '' }[m.type]
  return prefix + media
}

function Filters({ filters, onChange }: { filters: LeadFilters; onChange: (f: LeadFilters) => void }) {
  const { user } = useAuth()
  const isAdmin = user?.role !== 'assignee'
  const types = useLeadTypes()
  const branches = useBranches()
  const assignees = useAssignees(isAdmin)
  const set = (key: keyof LeadFilters) => (e: { target: { value: string } }) => onChange({ ...filters, [key]: e.target.value })

  return (
    <div className="grid grid-cols-2 gap-3 border-b border-gray-200 bg-gray-50 p-3">
      <SelectField label="Type" value={filters.lead_type_id} onChange={set('lead_type_id')}>
        <option value="">All types</option>
        {types.data?.map((t) => (
          <option key={t.id} value={t.id}>
            {t.name}
          </option>
        ))}
      </SelectField>
      <SelectField label="Source" value={filters.source} onChange={set('source')}>
        <option value="">All sources</option>
        {Object.entries(SOURCE).map(([value, label]) => (
          <option key={value} value={value}>
            {label}
          </option>
        ))}
      </SelectField>
      <SelectField label="Branch" value={filters.branch_id} onChange={set('branch_id')}>
        <option value="">All branches</option>
        {branches.data?.map((b) => (
          <option key={b.id} value={b.id}>
            {b.name}
          </option>
        ))}
      </SelectField>
      {isAdmin && (
        <SelectField label="Assignee" value={filters.assigned_to} onChange={set('assigned_to')}>
          <option value="">Anyone</option>
          <option value="none">Unassigned</option>
          {assignees.data?.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
            </option>
          ))}
        </SelectField>
      )}
      <SelectField label="Status" value={filters.status} onChange={set('status')}>
        <option value="">All statuses</option>
        {Object.entries(STATUS).map(([value, { label }]) => (
          <option key={value} value={value}>
            {label}
          </option>
        ))}
      </SelectField>
      <SelectField label="Reply status" value={filters.reply_status} onChange={set('reply_status')}>
        <option value="">Any</option>
        {Object.entries(REPLY).map(([value, { label }]) => (
          <option key={value} value={value}>
            {label}
          </option>
        ))}
      </SelectField>
      <TextField label="From" type="date" value={filters.from} onChange={set('from')} />
      <TextField label="To" type="date" value={filters.to} onChange={set('to')} />
      <div className="col-span-2">
        <Button variant="ghost" onClick={() => onChange({ ...EMPTY_FILTERS, tab: filters.tab, q: filters.q })}>
          Clear filters
        </Button>
      </div>
    </div>
  )
}

export function LeadList({
  filters,
  onFiltersChange,
  counts,
  selectedId,
  poll,
}: {
  filters: LeadFilters
  onFiltersChange: (f: LeadFilters) => void
  counts: LeadSummary['tabs'] | undefined
  selectedId: number | null
  poll: boolean
}) {
  const [showFilters, setShowFilters] = useState(false)
  const [search, setSearch] = useState(filters.q)
  const leads = useLeads(filters, poll)
  const now = useNow()
  const tabRefs = useRef<(HTMLButtonElement | null)[]>([])
  const activeFilters = FILTER_KEYS.filter((key) => filters[key] !== '').length

  useEffect(() => {
    const id = setTimeout(() => {
      if (search !== filters.q) onFiltersChange({ ...filters, q: search })
    }, 300)
    return () => clearTimeout(id)
  }, [search, filters, onFiltersChange])

  const onTabKey = (event: KeyboardEvent, index: number) => {
    const delta = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0
    if (!delta) return
    const next = (index + delta + TABS.length) % TABS.length
    tabRefs.current[next]?.focus()
    onFiltersChange({ ...filters, tab: TABS[next].key })
  }

  const items = leads.data?.pages.flatMap((page) => page.data) ?? []

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div role="tablist" aria-label="Lead views" className="flex shrink-0 overflow-x-auto border-b border-gray-200 bg-white">
        {TABS.map((tab, i) => {
          const selected = filters.tab === tab.key
          return (
            <button
              key={tab.key}
              ref={(el) => {
                tabRefs.current[i] = el
              }}
              role="tab"
              type="button"
              aria-selected={selected}
              tabIndex={selected ? 0 : -1}
              onKeyDown={(e) => onTabKey(e, i)}
              onClick={() => onFiltersChange({ ...filters, tab: tab.key })}
              className={`flex min-h-12 shrink-0 items-center gap-1.5 border-b-2 px-3 text-sm font-semibold ${focusRing} ${
                selected ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-600 hover:text-gray-900'
              }`}
            >
              {tab.label}
              {counts && (
                <span className={`rounded-full px-1.5 text-xs ${selected ? 'bg-brand-100' : 'bg-gray-100'}`}>{counts[tab.key]}</span>
              )}
            </button>
          )
        })}
      </div>

      <div className="flex shrink-0 gap-2 border-b border-gray-200 bg-white p-3">
        <label className="relative flex-1">
          <span className="sr-only">Search by name or phone</span>
          <SearchIcon className="pointer-events-none absolute left-3 top-3 size-5 text-gray-500" />
          <input
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search name or phone"
            className="min-h-11 w-full rounded-lg border border-gray-300 pl-10 pr-3 text-sm focus-visible:outline-2 focus-visible:outline-brand-600"
          />
        </label>
        <Button
          variant={activeFilters ? 'primary' : 'secondary'}
          className="px-3"
          aria-expanded={showFilters}
          onClick={() => setShowFilters((v) => !v)}
        >
          <FilterIcon />
          <span className="sr-only sm:not-sr-only">Filters</span>
          {activeFilters > 0 && <span aria-label={`${activeFilters} active`}>{activeFilters}</span>}
        </Button>
      </div>

      {showFilters && <Filters filters={filters} onChange={onFiltersChange} />}

      <div className="min-h-0 flex-1 overflow-y-auto bg-white" role="tabpanel" aria-label="Leads">
        {leads.isPending ? (
          <div className="p-3">
            <ListSkeleton rows={6} />
          </div>
        ) : leads.isError ? (
          <div className="p-3">
            <ErrorState message={errorMessage(leads.error)} onRetry={() => leads.refetch()} />
          </div>
        ) : items.length === 0 ? (
          <div className="p-3">
            <EmptyState title="No leads here" description="New WhatsApp enquiries will appear in this list." />
          </div>
        ) : (
          <ul className="divide-y divide-gray-100">
            {items.map((lead) => (
              <li key={lead.id}>
                <Link
                  to={`/leads/${lead.id}`}
                  aria-current={lead.id === selectedId ? 'true' : undefined}
                  className={`flex gap-3 px-3 py-3 hover:bg-gray-50 aria-[current]:bg-brand-50 ${focusRing} focus-visible:-outline-offset-2`}
                >
                  <Avatar lead={lead} />
                  <div className="min-w-0 flex-1">
                    <div className="flex items-baseline justify-between gap-2">
                      <p className={`truncate ${lead.unread_count ? 'font-bold' : 'font-semibold'} text-gray-900`}>
                        {lead.contact.name ?? lead.contact.phone}
                      </p>
                      <time className="shrink-0 text-xs text-gray-600" dateTime={lead.last_message_at ?? lead.created_at}>
                        {listTime(lead.last_message_at ?? lead.created_at)}
                      </time>
                    </div>
                    {lead.contact.name && <p className="text-xs text-gray-600">{lead.contact.phone}</p>}
                    <div className="flex items-center justify-between gap-2">
                      <p className="truncate text-sm text-gray-700">{preview(lead)}</p>
                      {lead.unread_count > 0 && (
                        <span className="shrink-0 rounded-full bg-brand-600 px-2 py-0.5 text-xs font-bold text-white">
                          {lead.unread_count}
                          <span className="sr-only"> unread</span>
                        </span>
                      )}
                    </div>
                    {lead.reply_status === 'not_sent' && lead.assigned_at && (
                      <p className="text-xs text-gray-700">
                        {lead.assignee?.name ?? lead.branch?.name ?? 'Assigned'} · waiting {duration(now - new Date(lead.assigned_at).getTime())}
                      </p>
                    )}
                    <div className="mt-1">
                      <LeadChips lead={lead} />
                    </div>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
        {leads.hasNextPage && (
          <div className="p-3 text-center">
            <Button variant="secondary" loading={leads.isFetchingNextPage} onClick={() => leads.fetchNextPage()}>
              Load more
            </Button>
          </div>
        )}
      </div>
    </div>
  )
}
