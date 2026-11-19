import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useToast } from '../../components/Toast'
import { Badge, Button, ErrorState, SelectField, TextAreaField } from '../../components/ui'
import { api, errorMessage, fieldErrors, type FieldErrors } from '../../lib/api'
import { dateTime } from '../../lib/format'
import { CALL_OUTCOMES } from '../../lib/labels'
import type { LeadTimeline as Timeline } from '../../lib/types'
import { useTimeline } from './api'

function NoteForm({ leadId, kind, onDone }: { leadId: number; kind: 'note' | 'call'; onDone: () => void }) {
  const queryClient = useQueryClient()
  const toast = useToast()
  const [body, setBody] = useState('')
  const [outcome, setOutcome] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})

  const save = useMutation({
    mutationFn: () =>
      api.post<{ data: Timeline }>(`/leads/${leadId}/notes`, { kind, body: body.trim() || null, call_outcome: kind === 'call' ? outcome || null : null }),
    onSuccess: ({ data }) => {
      queryClient.setQueryData(['lead-activity', leadId], data.data)
      toast(kind === 'call' ? 'Call logged' : 'Note added')
      onDone()
    },
    onError: (error) => {
      const errs = fieldErrors(error)
      setErrors(errs)
      if (!Object.keys(errs).length) toast(errorMessage(error), 'error')
    },
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    setErrors({})
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="mb-3 flex flex-col gap-3 rounded-lg bg-gray-50 p-3" noValidate>
      {kind === 'call' && (
        <SelectField label="Call outcome *" value={outcome} error={errors.call_outcome} onChange={(e) => setOutcome(e.target.value)}>
          <option value="">Choose…</option>
          {Object.entries(CALL_OUTCOMES).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </SelectField>
      )}
      <TextAreaField
        label={kind === 'call' ? 'Notes (optional)' : 'Note *'}
        value={body}
        error={errors.body}
        onChange={(e) => setBody(e.target.value)}
      />
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={save.isPending}>
          {kind === 'call' ? 'Log call' : 'Add note'}
        </Button>
      </div>
    </form>
  )
}

function Block({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
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

export function LeadTimeline({ leadId }: { leadId: number }) {
  const timeline = useTimeline(leadId)
  const [form, setForm] = useState<'note' | 'call' | null>(null)

  if (timeline.isError) {
    return (
      <div className="p-4">
        <ErrorState message={errorMessage(timeline.error)} onRetry={() => timeline.refetch()} />
      </div>
    )
  }

  const data = timeline.data
  const loading = <p className="text-sm text-gray-600">Loading…</p>

  return (
    <>
      <Block
        title="Notes & calls"
        action={
          !form && (
            <div className="flex gap-1">
              <Button variant="ghost" className="min-h-9 px-2 text-brand-700" onClick={() => setForm('note')}>
                Add note
              </Button>
              <Button variant="ghost" className="min-h-9 px-2 text-brand-700" onClick={() => setForm('call')}>
                Log call
              </Button>
            </div>
          )
        }
      >
        {form && <NoteForm key={form} leadId={leadId} kind={form} onDone={() => setForm(null)} />}
        {!data ? (
          loading
        ) : data.notes.length === 0 ? (
          <p className="text-sm text-gray-600">No notes yet.</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {data.notes.map((note) => (
              <li key={note.id} className="rounded-lg bg-gray-50 p-2 text-sm">
                <div className="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                  {note.kind === 'call' && <Badge tone="blue">Call · {CALL_OUTCOMES[note.call_outcome ?? ''] ?? '—'}</Badge>}
                  <span>
                    {note.user ?? 'System'} · {dateTime(note.created_at)}
                  </span>
                </div>
                {note.body && <p className="mt-1 whitespace-pre-wrap text-gray-900">{note.body}</p>}
              </li>
            ))}
          </ul>
        )}
      </Block>

      <Block title="Reminder history">
        {!data ? (
          loading
        ) : data.reminders.length === 0 ? (
          <p className="text-sm text-gray-600">No reminders sent.</p>
        ) : (
          <ul className="flex flex-col gap-1 text-sm">
            {data.reminders.map((r) => (
              <li key={r.id} className="text-gray-800">
                {r.auto ? 'Automatic' : r.sender} → {r.recipient ?? 'branch staff'}
                {r.channel.includes('whatsapp') && ' (in-app + WhatsApp)'}
                <span className="block text-xs text-gray-600">{dateTime(r.created_at)}</span>
              </li>
            ))}
          </ul>
        )}
      </Block>

      <Block title="Activity">
        {!data ? (
          loading
        ) : data.activity.length === 0 ? (
          <p className="text-sm text-gray-600">No activity yet.</p>
        ) : (
          <ol className="flex flex-col gap-2 border-l-2 border-gray-200 pl-3 text-sm">
            {data.activity.map((a) => (
              <li key={a.id}>
                <p className="text-gray-900">{a.text}</p>
                <p className="text-xs text-gray-600">{dateTime(a.created_at)}</p>
              </li>
            ))}
          </ol>
        )}
      </Block>
    </>
  )
}
