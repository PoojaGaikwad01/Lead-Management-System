import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useToast } from '../../components/Toast'
import { Button, CheckboxField, SelectField, TextField } from '../../components/ui'
import { api, errorMessage, fieldErrors, type FieldErrors } from '../../lib/api'
import { ROLE_LABELS, type Branch, type Role, type User } from '../../lib/types'

export function UserForm({ user, branches, isSelf, onDone }: { user?: User; branches: Branch[]; isSelf: boolean; onDone: () => void }) {
  const queryClient = useQueryClient()
  const toast = useToast()
  const [form, setForm] = useState({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
    role: user?.role ?? ('assignee' as Role),
    branch_id: user?.branch_id ? String(user.branch_id) : '',
    active: user?.active ?? true,
    password: '',
  })
  const [errors, setErrors] = useState<FieldErrors>({})

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        ...form,
        phone: form.phone || null,
        branch_id: form.branch_id ? Number(form.branch_id) : null,
        password: form.password || null,
      }
      return user ? api.put(`/users/${user.id}`, payload) : api.post('/users', payload)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['branches'] })
      if (isSelf) queryClient.invalidateQueries({ queryKey: ['me'] })
      toast(user ? 'User updated' : 'User created')
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

  const branchOptions = branches.filter((b) => b.active || b.id === user?.branch_id)

  return (
    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2" noValidate>
      <TextField label="Name" required value={form.name} error={errors.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
      <TextField
        label="Email"
        type="email"
        required
        value={form.email}
        error={errors.email}
        onChange={(e) => setForm({ ...form, email: e.target.value })}
      />
      <TextField label="Phone" type="tel" value={form.phone} error={errors.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
      <SelectField
        label="Role"
        value={form.role}
        error={errors.role}
        disabled={isSelf}
        onChange={(e) => setForm({ ...form, role: e.target.value as Role })}
      >
        {(Object.keys(ROLE_LABELS) as Role[]).map((role) => (
          <option key={role} value={role}>
            {ROLE_LABELS[role]}
          </option>
        ))}
      </SelectField>
      <SelectField
        label="Branch"
        value={form.branch_id}
        error={errors.branch_id}
        onChange={(e) => setForm({ ...form, branch_id: e.target.value })}
      >
        <option value="">No branch</option>
        {branchOptions.map((b) => (
          <option key={b.id} value={b.id}>
            {b.name}
          </option>
        ))}
      </SelectField>
      <TextField
        label={user ? 'New password (leave blank to keep)' : 'Password'}
        type="password"
        autoComplete="new-password"
        required={!user}
        value={form.password}
        error={errors.password}
        onChange={(e) => setForm({ ...form, password: e.target.value })}
      />
      <CheckboxField
        label="Active (can sign in)"
        checked={form.active}
        disabled={isSelf}
        onChange={(e) => setForm({ ...form, active: e.target.checked })}
      />
      <div className="flex justify-end gap-2 sm:col-span-2">
        <Button variant="secondary" onClick={onDone}>
          Cancel
        </Button>
        <Button type="submit" loading={save.isPending}>
          {user ? 'Save changes' : 'Create user'}
        </Button>
      </div>
    </form>
  )
}
