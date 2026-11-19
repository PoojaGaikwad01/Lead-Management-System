import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Modal } from '../../components/Modal'
import { EmptyState, ErrorState } from '../../components/ui'
import { errorMessage } from '../../lib/api'
import { useRealtimeConnected } from '../../lib/realtime'
import { EMPTY_FILTERS, useLead, useLeadSummary, useMarkRead, type LeadFilters } from './api'
import { Conversation } from './Conversation'
import { LeadDetails } from './LeadDetails'
import { LeadList } from './LeadList'
import { SummaryCards } from './SummaryCards'

export function InboxPage() {
  const params = useParams()
  const selectedId = params.leadId ? Number(params.leadId) : null
  const poll = !useRealtimeConnected()
  const [filters, setFilters] = useState<LeadFilters>(EMPTY_FILTERS)
  const [detailsFor, setDetailsFor] = useState<number | null>(null)
  const summary = useLeadSummary(poll)
  const lead = useLead(selectedId, poll)
  const markRead = useMarkRead()
  const { mutate: markLeadRead } = markRead

  const unread = lead.data?.unread_count ?? 0
  useEffect(() => {
    if (selectedId && unread > 0) markLeadRead(selectedId)
  }, [selectedId, unread, markLeadRead])

  return (
    <div className="flex h-full min-h-0 flex-col">
      <h1 className="sr-only">Inbox</h1>
      <SummaryCards cards={summary.data?.cards} className={`shrink-0 ${selectedId ? 'hidden lg:grid' : ''}`} />

      <div className="flex min-h-0 flex-1 border-t border-gray-200">
        <aside
          aria-label="Leads"
          className={`${selectedId ? 'hidden lg:flex' : 'flex'} w-full min-w-0 flex-col border-r border-gray-200 lg:w-96 lg:shrink-0`}
        >
          <LeadList filters={filters} onFiltersChange={setFilters} counts={summary.data?.tabs} selectedId={selectedId} poll={poll} />
        </aside>

        <section aria-label="Conversation" className={`${selectedId ? 'flex' : 'hidden lg:flex'} min-w-0 flex-1 flex-col`}>
          {!selectedId ? (
            <div className="m-auto max-w-sm p-6">
              <EmptyState title="Select a conversation" description="Choose a lead on the left to read and reply to their messages." />
            </div>
          ) : lead.isPending ? (
            <p role="status" className="m-auto text-sm text-gray-600">
              Loading conversation…
            </p>
          ) : lead.isError ? (
            <div className="m-auto max-w-sm p-6">
              <ErrorState message={errorMessage(lead.error)} onRetry={() => lead.refetch()} />
            </div>
          ) : (
            <Conversation lead={lead.data} poll={poll} onShowDetails={() => setDetailsFor(selectedId)} />
          )}
        </section>

        {lead.data && (
          <aside aria-label="Lead details" className="hidden w-80 shrink-0 overflow-y-auto border-l border-gray-200 bg-white xl:block">
            <LeadDetails key={lead.data.id} lead={lead.data} />
          </aside>
        )}
      </div>

      {lead.data && (
        <Modal open={detailsFor !== null && detailsFor === selectedId} title="Lead details" onClose={() => setDetailsFor(null)} variant="drawer">
          <LeadDetails key={lead.data.id} lead={lead.data} />
        </Modal>
      )}
    </div>
  )
}
