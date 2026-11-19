import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { ConfirmDialog, Modal } from '../../components/Modal'
import { useToast } from '../../components/Toast'
import { Badge, Button, CheckboxField, EmptyState, ErrorState, ListSkeleton, PageHeader, SelectField, TextField } from '../../components/ui'
import { api, errorMessage, fieldErrors, type FieldErrors } from '../../lib/api'
import { typeTone } from '../../lib/labels'
import type { LeadType } from '../../lib/types'
import { useLeadTypes } from '../inbox/api'

const COLORS = ['pink', 'green', 'purple', 'blue', 'slate', 'amber', 'gray', 'orange', 'red']
const FIELD_TYPES = { text: 'Text', number: 'Number', date: 'Date', select: 'Choice list' }

interface FieldRow {
  id?: number
  key: string
  label: string
  field_type: keyof typeof FIELD_TYPES
  options: string
  required: boolean
}

const toKey = (label: string) =>
  label
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .replace(/^(\d)/, 'f_$1')

function LeadTypeForm({ type, onDone }: { type?: LeadType; onDone: () => void }) {
  const queryClient = useQueryClient()
  const toast = useToast()
  const [name, setName] = useState(type?.name ?? '')
  const [color, setColor] = useState(type?.color ?? 'gray')
  const [active, setActive] = useState(type?.active ?? true)
  const [keywords, setKeywords] = useState((type?.keywords ?? []).join(', '))
  const [fields, setFields] = useState<FieldRow[]>(
    (type?.fields ?? []).map((f) => ({
      id: f.id,
      key: f.key,
      label: f.label,
      field_type: f.field_type as FieldRow['field_type'],
      options: (f.options ?? []).join(', '),
      required: f.required,
    })),
  )
  const [errors, setErrors] = useState<FieldErrors>({})

  const save = useMutation({
    mutationFn: () => {
      const body = {
        name,
        color,
        active,
        sort: type?.sort ?? 99,
        keywords: keywords.split(',').map((k) => k.trim()).filter(Boolean),
        fields: fields.map((f) => ({
          id: f.id,
          key: f.key,
          label: f.label,
          field_type: f.field_type,
          options: f.field_type === 'select' ? f.options.split(',').map((o) => o.trim()).filter(Boolean) : null,
          required: f.required,
        })),
      }
      return type ? api.put(`/lead-types/${type.id}`, body) : api.post('/lead-types', body)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-types'] })
      toast(type ? 'Lead type updated' : 'Lead type created')
      onDone()
    },
    onError: (error) => {
      const errs = fieldErrors(error)
      setErrors(errs)
      toast(Object.values(errs)[0] ?? errorMessage(error), 'error')
    },
  })

  const update = (i: number, patch: Partial<FieldRow>) => setFields((all) => all.map((f, j) => (j === i ? { ...f, ...patch } : f)))

  return (
    <form
      onSubmit={(e: FormEvent) => {
        e.preventDefault()
        setErrors({})
        save.mutate()
      }}
      className="flex flex-col gap-4"
      noValidate
    >
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField label="Name *" value={name} error={errors.name} onChange={(e) => setName(e.target.value)} />
        <SelectField label="Colour" value={color} onChange={(e) => setColor(e.target.value)}>
          {COLORS.map((c) => (
            <option key={c} value={c}>
              {c[0].toUpperCase() + c.slice(1)}
            </option>
          ))}
        </SelectField>
      </div>
      <TextField
        label="Detection keywords (comma-separated)"
        value={keywords}
        error={errors.keywords}
        placeholder="birthday, bday, party"
        onChange={(e) => setKeywords(e.target.value)}
      />
      <CheckboxField label="Active (offered for new leads)" checked={active} onChange={(e) => setActive(e.target.checked)} />

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-1 text-sm font-semibold text-gray-800">Custom fields</legend>
        {fields.length === 0 && <p className="text-sm text-gray-600">No custom fields.</p>}
        {fields.map((field, i) => (
          <div key={field.id ?? `new-${i}`} className="grid gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-2">
            <TextField
              label="Label *"
              value={field.label}
              error={errors[`fields.${i}.label`]}
              onChange={(e) => update(i, { label: e.target.value, ...(field.id ? {} : { key: toKey(e.target.value) }) })}
            />
            <TextField
              label="Key"
              value={field.key}
              disabled={!!field.id}
              error={errors[`fields.${i}.key`]}
              onChange={(e) => update(i, { key: e.target.value })}
            />
            <SelectField label="Type" value={field.field_type} onChange={(e) => update(i, { field_type: e.target.value as FieldRow['field_type'] })}>
              {Object.entries(FIELD_TYPES).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </SelectField>
            {field.field_type === 'select' ? (
              <TextField
                label="Choices (comma-separated) *"
                value={field.options}
                error={errors[`fields.${i}.options`]}
                onChange={(e) => update(i, { options: e.target.value })}
              />
            ) : (
              <div />
            )}
            <CheckboxField label="Required" checked={field.required} onChange={(e) => update(i, { required: e.target.checked })} />
            <div className="flex items-center justify-end">
              <Button variant="ghost" className="text-red-700" onClick={() => setFields((all) => all.filter((_, j) => j !== i))}>
                Remove field
              </Button>
            </div>
          </div>
        ))}
        <div>
          <Button
            variant="secondary"
            onClick={() => setFields((all) => [...all, { key: '', label: '', field_type: 'text', options: '', required: false }])}
          >
            Add field
          </Button>
        </div>
      </fieldset>

      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={save.isPending}>
          {type ? 'Save changes' : 'Create lead type'}
        </Button>
      </div>
    </form>
  )
}

export function LeadTypesPage() {
  const queryClient = useQueryClient()
  const toast = useToast()
  const types = useLeadTypes()
  const [editing, setEditing] = useState<LeadType | 'new' | null>(null)
  const [deleting, setDeleting] = useState<LeadType | null>(null)

  const remove = useMutation({
    mutationFn: (type: LeadType) => api.delete(`/lead-types/${type.id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['lead-types'] })
      toast('Lead type deleted')
      setDeleting(null)
    },
    onError: (error) => {
      toast(Object.values(fieldErrors(error))[0] ?? errorMessage(error), 'error')
      setDeleting(null)
    },
  })

  const addButton = <Button onClick={() => setEditing('new')}>Add lead type</Button>

  return (
    <>
      <PageHeader title="Lead types" description="Types, their detection keywords and custom fields. Changes apply immediately." action={addButton} />
      {types.isPending ? (
        <ListSkeleton />
      ) : types.isError ? (
        <ErrorState message={errorMessage(types.error)} onRetry={() => types.refetch()} />
      ) : types.data.length === 0 ? (
        <EmptyState title="No lead types" action={addButton} />
      ) : (
        <ul className="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
          {types.data.map((type) => (
            <li key={type.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
              <div className="min-w-0 flex-1 basis-64">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge tone={typeTone(type.color)}>{type.name}</Badge>
                  {!type.active && <Badge>Inactive</Badge>}
                </div>
                <p className="mt-1 text-sm text-gray-600">
                  {type.fields.length ? `Fields: ${type.fields.map((f) => f.label).join(', ')}` : 'No custom fields'}
                </p>
                <p className="text-sm text-gray-600">
                  Keywords: {type.keywords?.length ? type.keywords.join(', ') : '—'}
                </p>
              </div>
              <span className="text-sm text-gray-700">
                {type.leads_count ?? 0} {type.leads_count === 1 ? 'lead' : 'leads'}
              </span>
              <div className="flex gap-2">
                <Button variant="secondary" onClick={() => setEditing(type)} aria-label={`Edit ${type.name}`}>
                  Edit
                </Button>
                {(type.leads_count ?? 0) === 0 && (
                  <Button variant="ghost" className="text-red-700" onClick={() => setDeleting(type)} aria-label={`Delete ${type.name}`}>
                    Delete
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      <Modal open={editing !== null} title={editing === 'new' ? 'Add lead type' : 'Edit lead type'} onClose={() => setEditing(null)}>
        {editing && <LeadTypeForm type={editing === 'new' ? undefined : editing} onDone={() => setEditing(null)} />}
      </Modal>
      <ConfirmDialog
        open={deleting !== null}
        title="Delete lead type?"
        message={`Delete "${deleting?.name}"? This cannot be undone.`}
        confirmLabel="Delete"
        loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting)}
        onClose={() => setDeleting(null)}
      />
    </>
  )
}
