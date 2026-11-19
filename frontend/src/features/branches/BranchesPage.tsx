import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ConfirmDialog, Modal } from '../../components/Modal'
import { useToast } from '../../components/Toast'
import { Badge, Button, EmptyState, ErrorState, ListSkeleton, PageHeader } from '../../components/ui'
import { api, errorMessage } from '../../lib/api'
import type { Branch } from '../../lib/types'
import { BranchForm } from './BranchForm'
import { useBranches } from './useBranches'

export function BranchesPage() {
  const queryClient = useQueryClient()
  const toast = useToast()
  const branches = useBranches()
  const [editing, setEditing] = useState<Branch | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Branch | null>(null)

  const remove = useMutation({
    mutationFn: (branch: Branch) => api.delete(`/branches/${branch.id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['branches'] })
      queryClient.invalidateQueries({ queryKey: ['users'] })
      toast('Branch deleted')
      setDeleting(null)
    },
    onError: (error) => toast(errorMessage(error), 'error'),
  })

  const addButton = <Button onClick={() => setEditing('new')}>Add branch</Button>

  return (
    <>
      <PageHeader title="Branches" description="Locations that leads and staff can be assigned to." action={addButton} />

      {branches.isPending ? (
        <ListSkeleton />
      ) : branches.isError ? (
        <ErrorState message={errorMessage(branches.error)} onRetry={() => branches.refetch()} />
      ) : branches.data.length === 0 ? (
        <EmptyState title="No branches yet" description="Add your first branch to start assigning staff." action={addButton} />
      ) : (
        <ul className="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
          {branches.data.map((branch) => (
            <li key={branch.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
              <div className="min-w-0 flex-1 basis-56">
                <p className="font-semibold text-gray-900">{branch.name}</p>
                <p className="text-sm text-gray-600">{[branch.phone, branch.address].filter(Boolean).join(' · ') || 'No contact details'}</p>
              </div>
              <span className="text-sm text-gray-700">
                {branch.users_count ?? 0} {branch.users_count === 1 ? 'user' : 'users'}
              </span>
              <Badge tone={branch.active ? 'green' : 'gray'}>{branch.active ? 'Active' : 'Inactive'}</Badge>
              <div className="flex gap-2">
                <Button variant="secondary" onClick={() => setEditing(branch)} aria-label={`Edit ${branch.name}`}>
                  Edit
                </Button>
                <Button variant="ghost" className="text-red-700" onClick={() => setDeleting(branch)} aria-label={`Delete ${branch.name}`}>
                  Delete
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <Modal open={editing !== null} title={editing === 'new' ? 'Add branch' : 'Edit branch'} onClose={() => setEditing(null)}>
        {editing && <BranchForm branch={editing === 'new' ? undefined : editing} onDone={() => setEditing(null)} />}
      </Modal>

      <ConfirmDialog
        open={deleting !== null}
        title="Delete branch?"
        message={`Delete ${deleting?.name}? Its users and leads will no longer be linked to a branch.`}
        confirmLabel="Delete branch"
        loading={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting)}
        onClose={() => setDeleting(null)}
      />
    </>
  )
}
