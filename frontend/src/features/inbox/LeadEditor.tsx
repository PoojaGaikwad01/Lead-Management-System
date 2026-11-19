import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useToast } from '../../components/Toast'
import { Button, SelectField, TextField } from '../../components/ui'
import { api, errorMessage, fieldErrors, type FieldErrors } from '../../lib/api'
import type { Lead, LeadTypeField } from '../../lib/types'
import { useAuth } from '../auth/AuthContext'
import { useBranches } from '../branches/useBranches'
import { useLeadTypes } from './api'

function FieldInput({
  field,
  value,
  error,
  onChange,
}: {
  field: LeadTypeField
  value: string
  error?: string
  onChange: (value: string) => void
}) {
  const label = field.required ? `${field.label} *` : field.label
  if (field.field_type === 'select') {
    return (
      <SelectField label={label} value={value} error={error} onChange={(e) => onChange(e.target.value)}>
        <option value="">—</option>
        {(field.options ?? []).map((option) => (
          <option key={option} value={option}>
            {option}
          </option>
        ))}
      </SelectField>
    )
  }
  const type = field.field_type === 'number' ? 'number' : field.field_type === 'date' ? 'date' : 'text'
  return <TextField label={label} type={type} value={value} error={error} onChange={(e) => onChange(e.target.value)} />
}

export function LeadEditor({ lead, onDone }: { lead: Lead; onDone: () => void }) {
  const { user } = useAuth()
  const isAdmin = user?.role !== 'assignee'
  const queryClient = useQueryClient()
  const toast = useToast()
  const types = useLeadTypes()
  const branches = useBranches()
  const [name, setName] = useState(lead.contact.name ?? '')
  const [typeId, setTypeId] = useState(lead.lead_type ? String(lead.lead_type.id) : '')
  const [branchId, setBranchId] = useState(lead.branch ? String(lead.branch.id) : '')
  const [values, setValues] = useState<Record<string, string>>(() =>
    Object.fromEntries(Object.entries(lead.custom_data).map(([key, value]) => [key, String(value ?? '')])),
  )
  const [errors, setErrors] = useState<FieldErrors>({})
  const type = types.data?.find((t) => String(t.id) === typeId)

  const save = useMutation({
    mutationFn: () =>
      api.patch<{ data: Lead }>(`/leads/${lead.id}`, {
        name: name.trim() || null,
        lead_type_id: typeId ? Number(typeId) : null,
        ...(isAdmin ? { branch_id: branchId ? Number(branchId) : null } : {}),
        custom_data: Object.fromEntries((type?.fields ?? []).map((f) => [f.key, values[f.key] || null])),
      }),
    onSuccess: ({ data }) => {
      queryClient.setQueryData(['lead', lead.id], data.data)
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      toast('Lead updated')
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
    <form onSubmit={submit} className="flex flex-col gap-3" noValidate>
      <TextField label="Customer name" value={name} error={errors.name} onChange={(e) => setName(e.target.value)} />
      <SelectField label="Lead type" value={typeId} error={errors.lead_type_id} onChange={(e) => setTypeId(e.target.value)}>
        <option value="">Not set</option>
        {types.data
          ?.filter((t) => t.active || String(t.id) === typeId)
          .map((t) => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
      </SelectField>
      {isAdmin && (
        <SelectField label="Branch" value={branchId} error={errors.branch_id} onChange={(e) => setBranchId(e.target.value)}>
          <option value="">Not set</option>
          {branches.data
            ?.filter((b) => b.active || String(b.id) === branchId)
            .map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
        </SelectField>
      )}
      {type?.fields.map((field) => (
        <FieldInput
          key={field.key}
          field={field}
          value={values[field.key] ?? ''}
          error={errors[`custom_data.${field.key}`]}
          onChange={(value) => setValues((current) => ({ ...current, [field.key]: value }))}
        />
      ))}
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={save.isPending}>
          Save
        </Button>
      </div>
    </form>
  )
}
