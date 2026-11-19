import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useToast } from '../../components/Toast'
import { Button, CheckboxField, TextField } from '../../components/ui'
import { api, errorMessage, fieldErrors, type FieldErrors } from '../../lib/api'
import type { Branch } from '../../lib/types'

export function BranchForm({ branch, onDone }: { branch?: Branch; onDone: () => void }) {
  const queryClient = useQueryClient()
  const toast = useToast()
  const [form, setForm] = useState({
    name: branch?.name ?? '',
    phone: branch?.phone ?? '',
    address: branch?.address ?? '',
    active: branch?.active ?? true,
  })
  const [errors, setErrors] = useState<FieldErrors>({})

  const save = useMutation({
    mutationFn: () => {
      const payload = { ...form, phone: form.phone || null, address: form.address || null }
      return branch ? api.put(`/branches/${branch.id}`, payload) : api.post('/branches', payload)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['branches'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
      toast(branch ? 'Branch updated' : 'Branch created')
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
    <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
      <TextField label="Name" required value={form.name} error={errors.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
      <TextField label="Phone" type="tel" value={form.phone} error={errors.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
      <TextField label="Address" value={form.address} error={errors.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
      <CheckboxField label="Active" checked={form.active} onChange={(e) => setForm({ ...form, active: e.target.checked })} />
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={save.isPending}>
          {branch ? 'Save changes' : 'Create branch'}
        </Button>
      </div>
    </form>
  )
}
