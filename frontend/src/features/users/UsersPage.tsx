import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ConfirmDialog, Modal } from '../../components/Modal'
import { useToast } from '../../components/Toast'
import { Badge, Button, EmptyState, ErrorState, ListSkeleton, PageHeader, type BadgeTone } from '../../components/ui'
import { api, errorMessage } from '../../lib/api'
import { ROLE_LABELS, type Role, type User } from '../../lib/types'
import { useAuth } from '../auth/AuthContext'
import { useBranches } from '../branches/useBranches'
import { UserForm } from './UserForm'

const roleTone: Record<Role, BadgeTone> = { super_admin: 'purple', admin: 'blue', assignee: 'gray' }

export function UsersPage() {
  const { user: me } = useAuth()
  const queryClient = useQueryClient()
  const toast = useToast()
  const branches = useBranches()
  const users = useQuery({
    queryKey: ['users'],
    queryFn: async () => (await api.get<{ data: User[] }>('/users')).data.data,
  })
  const [editing, setEditing] = useState<User | 'new' | null>(null)
  const [deleting, setDeleting] = useState<User | null>(null)

  const remove = useMutation({
    mutationFn: (user: User) => api.delete(`/users/${user.id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['branches'] })
      toast('User deleted')
      setDeleting(null)
    },
    onError: (error) => toast(errorMessage(error), 'error'),
  })

  const addButton = <Button onClick={() => setEditing('new')}>Add user</Button>

  return (
    <>
      <PageHeader title="Users" description="Admins and branch staff who can sign in." action={addButton} />

      {users.isPending || branches.isPending ? (
        <ListSkeleton />
      ) : users.isError || branches.isError ? (
        <ErrorState
          message={errorMessage(users.error ?? branches.error)}
          onRetry={() => {
            users.refetch()
            branches.refetch()
          }}
        />
      ) : users.data.length === 0 ? (
        <EmptyState title="No users yet" action={addButton} />
      ) : (
        <ul className="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
          {users.data.map((user) => (
            <li key={user.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
              <div className="min-w-0 flex-1 basis-56">
                <p className="font-semibold text-gray-900">
                  {user.name}
                  {user.id === me?.id && <span className="font-normal text-gray-600"> (you)</span>}
                </p>
                <p className="truncate text-sm text-gray-600">
                  {user.email}
                  {user.phone && ` · ${user.phone}`}
                </p>
              </div>
              <span className="text-sm text-gray-700">{user.branch?.name ?? 'No branch'}</span>
              <Badge tone={roleTone[user.role]}>{ROLE_LABELS[user.role]}</Badge>
              <Badge tone={user.active ? 'green' : 'gray'}>{user.active ? 'Active' : 'Inactive'}</Badge>
              <div className="flex gap-2">
                <Button variant="secondary" onClick={() => setEditing(user)} aria-label={`Edit ${user.name}`}>
                  Edit
                </Button>
                {user.id !== me?.id && (
                  <Button variant="ghost" className="text-red-700" onClick={() => setDeleting(user)} aria-label={`Delete ${user.name}`}>
                    Delete
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      <Modal open={editing !== null} title={editing === 'new' ? 'Add user' : 'Edit user'} onClose={() => setEditing(null)}>
        {editing && (
          <UserForm
            user={editing === 'new' ? undefined : editing}
            branches={branches.data ?? []}
            isSelf={editing !== 'new' && editing.id === me?.id}
            onDone={() => setEditing(null)}
          />
        )}
      </Modal>

      <ConfirmDialog
        open={deleting !== null}
        title="Delete user?"
        message={`Delete ${deleting?.name}? Leads assigned to them will become unassigned. To keep history, mark them inactive instead.`}
        confirmLabel="Delete user"
        loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting)}
        onClose={() => setDeleting(null)}
      />
    </>
  )
}
