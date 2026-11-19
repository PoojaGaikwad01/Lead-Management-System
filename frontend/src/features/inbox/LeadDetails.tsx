import { useState, type ReactNode } from 'react'
import { Badge, Button } from '../../components/ui'
import { dateTime, duration, useNow } from '../../lib/format'
import { REPLY, SOURCE, STATUS, typeTone } from '../../lib/labels'
import type { Lead } from '../../lib/types'
import { useLeadTypes } from './api'
import { LeadActionBar } from './LeadActions'
import { OverdueBadge } from './LeadBadges'
import { LeadEditor } from './LeadEditor'
import { LeadTimeline } from './LeadTimeline'

function Section({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="border-b border-gray-100 px-4 py-4">
      <div className="mb-2 flex items-center justify-between gap-2">
        <h3 className="text-xs font-bold uppercase tracking-wide text-gray-600">{title}</h3>
        {action}
      </div>
      {children}
    </section>
  )
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-3 py-1 text-sm">
      <dt className="text-gray-600">{label}</dt>
      <dd className="text-right font-medium text-gray-900">{children}</dd>
    </div>
  )
}

export function LeadDetails({ lead }: { lead: Lead }) {
  const now = useNow()
  const types = useLeadTypes()
  const fields = types.data?.find((t) => t.id === lead.lead_type?.id)?.fields ?? []
  const [editing, setEditing] = useState(false)

  return (
    <div className="flex min-h-full flex-col">
      <div className="flex-1">
        <Section title="Contact">
          <dl>
            <Row label="Name">{lead.contact.name ?? '—'}</Row>
            <Row label="Phone">
              <a href={`tel:${lead.contact.phone}`} className="text-brand-700 underline">
                {lead.contact.phone}
              </a>
            </Row>
            <Row label="Source">
              <Badge tone="slate">{SOURCE[lead.source]}</Badge>
            </Row>
            <Row label="Created">{dateTime(lead.created_at)}</Row>
          </dl>
        </Section>

        <Section
          title="Lead"
          action={
            !editing && (
              <Button variant="ghost" className="min-h-9 px-2 text-brand-700" onClick={() => setEditing(true)}>
                Edit
              </Button>
            )
          }
        >
          {editing ? (
            <LeadEditor key={lead.id} lead={lead} onDone={() => setEditing(false)} />
          ) : (
            <dl>
              <Row label="Type">
                {lead.lead_type ? <Badge tone={typeTone(lead.lead_type.color)}>{lead.lead_type.name}</Badge> : 'Not set'}
              </Row>
              <Row label="Branch">{lead.branch?.name ?? 'Not set'}</Row>
              <Row label="Status">
                <Badge tone={STATUS[lead.status].tone}>{STATUS[lead.status].label}</Badge>
              </Row>
              {lead.lost_reason && <Row label="Lost reason">{lead.lost_reason}</Row>}
              {fields.map((field) => (
                <Row key={field.key} label={field.label}>
                  {String(lead.custom_data[field.key] ?? '—')}
                </Row>
              ))}
            </dl>
          )}
          {lead.source_text && (
            <details className="mt-3 rounded-lg bg-gray-50 p-2 text-sm">
              <summary className="cursor-pointer font-semibold text-gray-700">Original {SOURCE[lead.source].toLowerCase()} data</summary>
              <pre className="mt-2 whitespace-pre-wrap font-sans text-gray-800">{lead.source_text}</pre>
            </details>
          )}
        </Section>

        <Section title="Assignment">
          <dl>
            <Row label="Assignee">{lead.assignee?.name ?? (lead.assigned_at && lead.branch ? `${lead.branch.name} branch` : 'Unassigned')}</Row>
            <Row label="Assigned">
              {dateTime(lead.assigned_at)}
              {lead.reply_status === 'not_sent' && lead.assigned_at && (
                <span className="block text-xs text-gray-600">Waiting {duration(now - new Date(lead.assigned_at).getTime())}</span>
              )}
            </Row>
            <Row label="Reply">
              {lead.reply_status ? (
                <span className="flex flex-wrap justify-end gap-1">
                  <Badge tone={REPLY[lead.reply_status].tone}>{REPLY[lead.reply_status].label}</Badge>
                  <OverdueBadge dueAt={lead.reply_due_at} />
                </span>
              ) : (
                '—'
              )}
              {lead.reply_sent_at && <span className="block text-xs text-gray-600">{dateTime(lead.reply_sent_at)}</span>}
            </Row>
            <Row label="Reminders">
              {lead.reminder_count}
              {lead.last_reminder_at && <span className="block text-xs text-gray-600">Last: {dateTime(lead.last_reminder_at)}</span>}
            </Row>
          </dl>
        </Section>

        <LeadTimeline leadId={lead.id} />
      </div>

      <LeadActionBar lead={lead} />
    </div>
  )
}
