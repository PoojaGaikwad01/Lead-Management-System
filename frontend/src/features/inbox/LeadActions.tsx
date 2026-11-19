import { useState, type FormEvent } from 'react'
import { PhoneIcon } from '../../components/icons'
import { Modal } from '../../components/Modal'
import { useToast } from '../../components/Toast'
import { Button, SelectField, TextAreaField, TextField, focusRing } from '../../components/ui'
import { errorMessage, fieldErrors, type FieldErrors } from '../../lib/api'
import { LOST_REASONS, STATUS } from '../../lib/labels'
import type { Lead, LeadStatus } from '../../lib/types'
import { useAuth } from '../auth/AuthContext'
import { useBranches } from '../branches/useBranches'
import { useAssignees, useLeadAction } from './api'

type Dialog = 'assign' | 'reply' | 'status' | null

function useSubmit(leadId: number, onDone: () => void, success: string) {
  const action = useLeadAction(leadId)
  const toast = useToast()
  const [errors, setErrors] = useState<FieldErrors>({})

  const submit = (name: string, body?: object) => {
    setErrors({})
    action.mutate(
      { action: name, body },
      {
        onSuccess: () => {
          toast(success)
          onDone()
        },
        onError: (error) => {
          const errs = fieldErrors(error)
          setErrors(errs)
          toast(Object.values(errs)[0] ?? errorMessage(error), 'error')
        },
      },
    )
  }

  return { submit, errors, pending: action.isPending }
}

function AssignForm({ lead, onDone }: { lead: Lead; onDone: () => void }) {
  const { user } = useAuth()
  const users = useAssignees(true)
  const branches = useBranches()
  const [userId, setUserId] = useState(lead.assignee ? String(lead.assignee.id) : '')
  const [branchId, setBranchId] = useState(lead.branch ? String(lead.branch.id) : '')
  const { submit, errors, pending } = useSubmit(lead.id, onDone, lead.assignee ? 'Lead reassigned' : 'Lead assigned')

  const pickUser = (id: string) => {
    setUserId(id)
    const picked = users.data?.find((u) => String(u.id) === id)
    if (!branchId && picked?.branch_id) setBranchId(String(picked.branch_id))
  }

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    submit('assign', { assigned_to: userId ? Number(userId) : null, branch_id: branchId ? Number(branchId) : null })
  }

  const others = (users.data ?? []).filter((u) => u.active && u.id !== user?.id)

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <SelectField label="Assign to" value={userId} error={errors.assigned_to} onChange={(e) => pickUser(e.target.value)}>
        <option value="">Branch only (anyone at the branch)</option>
        {user && <option value={user.id}>Me – I'll handle it myself</option>}
        {others.map((u) => (
          <option key={u.id} value={u.id}>
            {u.name}
            {u.branch ? ` (${u.branch.name})` : ''}
          </option>
        ))}
      </SelectField>
      <SelectField label="Branch" value={branchId} error={errors.branch_id} onChange={(e) => setBranchId(e.target.value)}>
        <option value="">No branch</option>
        {branches.data
          ?.filter((b) => b.active || String(b.id) === branchId)
          .map((b) => (
            <option key={b.id} value={b.id}>
              {b.name}
            </option>
          ))}
      </SelectField>
      <p className="text-sm text-gray-600">The assignee is notified and the reply status is set to “Reply not sent”.</p>
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          {lead.assignee || lead.assigned_at ? 'Reassign' : 'Assign'}
        </Button>
      </div>
    </form>
  )
}

function ReplySentForm({ lead, onDone }: { lead: Lead; onDone: () => void }) {
  const [note, setNote] = useState('')
  const { submit, errors, pending } = useSubmit(lead.id, onDone, 'Reply marked as sent')

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault()
        submit('reply-sent', { note: note.trim() || null })
      }}
      className="flex flex-col gap-4"
    >
      <TextAreaField
        label="Note (optional)"
        value={note}
        error={errors.note ?? errors.reply}
        placeholder="e.g. Shared birthday packages on WhatsApp"
        onChange={(e) => setNote(e.target.value)}
      />
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          Mark reply sent
        </Button>
      </div>
    </form>
  )
}

function StatusForm({ lead, onDone }: { lead: Lead; onDone: () => void }) {
  const [status, setStatus] = useState<LeadStatus>(lead.status)
  const [reason, setReason] = useState(lead.lost_reason && LOST_REASONS.includes(lead.lost_reason) ? lead.lost_reason : lead.lost_reason ? 'Other' : '')
  const [otherReason, setOtherReason] = useState(lead.lost_reason && !LOST_REASONS.includes(lead.lost_reason) ? lead.lost_reason : '')
  const [note, setNote] = useState('')
  const { submit, errors, pending } = useSubmit(lead.id, onDone, 'Status updated')

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    const lostReason = status === 'lost' ? (reason === 'Other' ? otherReason.trim() : reason) : null
    submit('status', { status, lost_reason: lostReason || null, note: note.trim() || null })
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <SelectField label="Status" value={status} error={errors.status} onChange={(e) => setStatus(e.target.value as LeadStatus)}>
        {Object.entries(STATUS).map(([value, { label }]) => (
          <option key={value} value={value}>
            {label}
          </option>
        ))}
      </SelectField>
      {status === 'lost' && (
        <>
          <SelectField label="Lost reason *" value={reason} error={reason !== 'Other' ? errors.lost_reason : undefined} onChange={(e) => setReason(e.target.value)}>
            <option value="">Choose a reason</option>
            {[...LOST_REASONS, 'Other'].map((r) => (
              <option key={r} value={r}>
                {r}
              </option>
            ))}
          </SelectField>
          {reason === 'Other' && (
            <TextField label="Describe the reason *" value={otherReason} error={errors.lost_reason} onChange={(e) => setOtherReason(e.target.value)} />
          )}
        </>
      )}
      <TextAreaField label="Note (optional)" value={note} error={errors.note} onChange={(e) => setNote(e.target.value)} />
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          Save status
        </Button>
      </div>
    </form>
  )
}

/** Sticky action bar for the details panel / mobile drawer. */
export function LeadActionBar({ lead }: { lead: Lead }) {
  const { user } = useAuth()
  const isAdmin = user?.role !== 'assignee'
  const toast = useToast()
  const [dialog, setDialog] = useState<Dialog>(null)
  const remind = useLeadAction(lead.id)
  const close = () => setDialog(null)
  const pending = lead.reply_status === 'not_sent'

  const sendReminder = () =>
    remind.mutate(
      { action: 'remind' },
      {
        onSuccess: () => toast('Reminder sent'),
        onError: (error) => toast(Object.values(fieldErrors(error))[0] ?? errorMessage(error), 'error'),
      },
    )

  return (
    <>
      <div className="sticky bottom-0 grid grid-cols-2 gap-2 border-t border-gray-200 bg-white p-3">
        <a
          href={`tel:${lead.contact.phone}`}
          className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-800 hover:bg-gray-50 ${focusRing}`}
        >
          <PhoneIcon className="size-4" /> Call
        </a>
        <Button variant={pending ? 'primary' : 'secondary'} disabled={!pending} onClick={() => setDialog('reply')}>
          Mark reply sent
        </Button>
        <Button variant="secondary" onClick={() => setDialog('status')}>
          Change status
        </Button>
        {isAdmin && (
          <>
            <Button variant="secondary" onClick={() => setDialog('assign')}>
              {lead.assigned_at ? 'Reassign' : 'Assign'}
            </Button>
            <Button variant="secondary" className="col-span-2" disabled={!pending} loading={remind.isPending} onClick={sendReminder}>
              Send reminder{lead.reminder_count ? ` (${lead.reminder_count} sent)` : ''}
            </Button>
          </>
        )}
      </div>

      <Modal open={dialog === 'assign'} title={lead.assigned_at ? 'Reassign lead' : 'Assign lead'} onClose={close}>
        <AssignForm lead={lead} onDone={close} />
      </Modal>
      <Modal open={dialog === 'reply'} title="Mark reply sent" onClose={close}>
        <ReplySentForm lead={lead} onDone={close} />
      </Modal>
      <Modal open={dialog === 'status'} title="Change status" onClose={close}>
        <StatusForm lead={lead} onDone={close} />
      </Modal>
    </>
  )
}
