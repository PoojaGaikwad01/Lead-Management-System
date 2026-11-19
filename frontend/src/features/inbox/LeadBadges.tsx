import { Badge } from '../../components/ui'
import { duration, initials, useNow } from '../../lib/format'
import { REPLY, SOURCE, STATUS, typeTone } from '../../lib/labels'
import type { Lead } from '../../lib/types'

export function Avatar({ lead, size = 'size-11' }: { lead: Lead; size?: string }) {
  return (
    <span
      aria-hidden="true"
      className={`${size} grid shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700`}
    >
      {initials(lead.contact.name, lead.contact.phone)}
    </span>
  )
}

export function OverdueBadge({ dueAt }: { dueAt: string | null }) {
  const now = useNow()
  if (!dueAt) return null
  const late = now - new Date(dueAt).getTime()
  if (late <= 0) return null
  return <Badge tone="red">{duration(late)} overdue</Badge>
}

export function LeadChips({ lead, showReply = false }: { lead: Lead; showReply?: boolean }) {
  return (
    <div className="flex flex-wrap gap-1">
      <Badge tone="slate">{SOURCE[lead.source]}</Badge>
      {lead.lead_type && <Badge tone={typeTone(lead.lead_type.color)}>{lead.lead_type.name}</Badge>}
      <Badge tone={STATUS[lead.status].tone}>{STATUS[lead.status].label}</Badge>
      {showReply && lead.reply_status && <Badge tone={REPLY[lead.reply_status].tone}>{REPLY[lead.reply_status].label}</Badge>}
      <OverdueBadge dueAt={lead.reply_due_at} />
    </div>
  )
}
