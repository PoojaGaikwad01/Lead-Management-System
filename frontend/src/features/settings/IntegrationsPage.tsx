import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { ConfirmDialog, Modal } from '../../components/Modal'
import { useToast } from '../../components/Toast'
import { Badge, Button, ErrorState, ListSkeleton, PageHeader, TextField } from '../../components/ui'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { dateTime } from '../../lib/format'
import type { IntegrationsData } from '../../lib/types'

function Section({ title, description, action, children }: { title: string; description: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="rounded-xl border border-gray-200 bg-white p-5">
      <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-bold text-gray-900">{title}</h2>
          <p className="mt-1 text-sm text-gray-600">{description}</p>
        </div>
        {action}
      </div>
      {children}
    </section>
  )
}

function SecretBox({ label, value }: { label: string; value: string }) {
  const toast = useToast()
  return (
    <div className="flex flex-col gap-2">
      <p className="text-sm font-semibold text-gray-800">{label}</p>
      <code className="break-all rounded-lg bg-gray-100 p-3 text-sm">{value}</code>
      <div>
        <Button variant="secondary" onClick={() => navigator.clipboard?.writeText(value).then(() => toast('Copied'))}>
          Copy
        </Button>
      </div>
      <p className="text-sm text-amber-900">This is shown only once. Store it in the portal's configuration now.</p>
    </div>
  )
}

function CreateForm({ label, placeholder, onCreate, pending, error }: { label: string; placeholder: string; onCreate: (v: string) => void; pending: boolean; error?: string }) {
  const [value, setValue] = useState('')
  return (
    <form
      onSubmit={(e: FormEvent) => {
        e.preventDefault()
        onCreate(value.trim())
      }}
      className="flex flex-col gap-3"
    >
      <TextField label={label} value={value} placeholder={placeholder} error={error} onChange={(e) => setValue(e.target.value)} />
      <div className="flex justify-end">
        <Button type="submit" loading={pending}>
          Create
        </Button>
      </div>
    </form>
  )
}

const SIGNATURE_EXAMPLE = `$body = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
if (! hash_equals($expected, $_SERVER['HTTP_X_FUNKYMONKEY_SIGNATURE'] ?? '')) {
    http_response_code(401); exit;
}
$event = json_decode($body, true); // ['event' => 'lead.converted', 'data' => [...lead]]`

export function IntegrationsPage() {
  const queryClient = useQueryClient()
  const toast = useToast()
  const data = useQuery({
    queryKey: ['integrations'],
    queryFn: async () => (await api.get<{ data: IntegrationsData }>('/integrations')).data.data,
  })
  const [dialog, setDialog] = useState<'client' | 'webhook' | null>(null)
  const [secret, setSecret] = useState<{ label: string; value: string } | null>(null)
  const [removing, setRemoving] = useState<{ kind: 'clients' | 'webhooks'; id: number; name: string } | null>(null)
  const [formError, setFormError] = useState<string>()
  const refresh = () => queryClient.invalidateQueries({ queryKey: ['integrations'] })
  const failed = (error: unknown) => {
    const message = Object.values(fieldErrors(error))[0] ?? errorMessage(error)
    setFormError(message)
    toast(message, 'error')
  }

  const createClient = useMutation({
    mutationFn: (name: string) => api.post<{ data: { token: string } }>('/integrations/clients', { name }),
    onSuccess: ({ data: res }) => {
      refresh()
      setSecret({ label: 'API token', value: res.data.token })
    },
    onError: failed,
  })
  const createWebhook = useMutation({
    mutationFn: (url: string) => api.post<{ data: { secret: string } }>('/integrations/webhooks', { url }),
    onSuccess: ({ data: res }) => {
      refresh()
      setSecret({ label: 'Signing secret', value: res.data.secret })
    },
    onError: failed,
  })
  const toggle = useMutation({
    mutationFn: ({ id, active }: { id: number; active: boolean }) => api.patch(`/integrations/webhooks/${id}`, { active }),
    onSuccess: refresh,
    onError: (e) => toast(errorMessage(e), 'error'),
  })
  const test = useMutation({
    mutationFn: (id: number) => api.post<{ ok: boolean; status: number | null; error: string | null }>(`/integrations/webhooks/${id}/test`),
    onSuccess: ({ data: res }) => {
      refresh()
      toast(res.ok ? `Test delivered (HTTP ${res.status})` : `Test failed: ${res.error ?? 'no response'}`, res.ok ? 'success' : 'error')
    },
    onError: (e) => toast(errorMessage(e), 'error'),
  })
  const remove = useMutation({
    mutationFn: (item: NonNullable<typeof removing>) => api.delete(`/integrations/${item.kind}/${item.id}`),
    onSuccess: () => {
      refresh()
      setRemoving(null)
      toast('Removed')
    },
    onError: (e) => toast(errorMessage(e), 'error'),
  })

  const closeDialog = () => {
    setDialog(null)
    setSecret(null)
    setFormError(undefined)
  }

  if (data.isPending) return <ListSkeleton rows={4} />
  if (data.isError) return <ErrorState message={errorMessage(data.error)} onRetry={() => data.refetch()} />
  const { clients, webhooks, api_base_url } = data.data

  return (
    <>
      <PageHeader title="Portal integration" description="Lets the existing Funky Monkey portal read leads and get notified when a lead converts." />
      <div className="flex flex-col gap-5">
        <Section
          title="API clients"
          description={`Bearer tokens for GET ${api_base_url}/leads (filters: status, updated_since, per_page) and ${api_base_url}/leads/{id}.`}
          action={<Button onClick={() => setDialog('client')}>Create API client</Button>}
        >
          {clients.length === 0 ? (
            <p className="text-sm text-gray-600">No API clients yet.</p>
          ) : (
            <ul className="divide-y divide-gray-100">
              {clients.map((c) => (
                <li key={c.id} className="flex flex-wrap items-center gap-3 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold text-gray-900">{c.name}</p>
                    <p className="text-sm text-gray-600">
                      Created {dateTime(c.created_at)} · {c.last_used_at ? `last used ${dateTime(c.last_used_at)}` : 'never used'}
                    </p>
                  </div>
                  <Button variant="ghost" className="text-red-700" onClick={() => setRemoving({ kind: 'clients', id: c.id, name: c.name })}>
                    Revoke
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </Section>

        <Section
          title="Webhooks"
          description="We POST a signed JSON message to these URLs when a lead is marked Converted (event lead.converted). Failed deliveries are retried for about an hour."
          action={<Button onClick={() => setDialog('webhook')}>Add webhook</Button>}
        >
          {webhooks.length === 0 ? (
            <p className="text-sm text-gray-600">No webhooks yet.</p>
          ) : (
            <ul className="divide-y divide-gray-100">
              {webhooks.map((w) => (
                <li key={w.id} className="flex flex-wrap items-center gap-3 py-3">
                  <div className="min-w-0 flex-1 basis-64">
                    <p className="break-all font-semibold text-gray-900">{w.url}</p>
                    <p className="text-sm text-gray-600">
                      {w.last_delivery_at
                        ? `Last delivery ${dateTime(w.last_delivery_at)}: ${w.last_status ? `HTTP ${w.last_status}` : 'no response'}${w.last_error ? ` (${w.last_error})` : ''}`
                        : 'Nothing sent yet'}
                    </p>
                  </div>
                  <Badge tone={w.active ? 'green' : 'gray'}>{w.active ? 'Active' : 'Paused'}</Badge>
                  <Button variant="secondary" loading={test.isPending && test.variables === w.id} onClick={() => test.mutate(w.id)}>
                    Send test
                  </Button>
                  <Button variant="secondary" onClick={() => toggle.mutate({ id: w.id, active: !w.active })}>
                    {w.active ? 'Pause' : 'Resume'}
                  </Button>
                  <Button variant="ghost" className="text-red-700" onClick={() => setRemoving({ kind: 'webhooks', id: w.id, name: w.url })}>
                    Delete
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </Section>

        <Section title="For the portal developer" description="Each webhook request carries X-FunkyMonkey-Event, X-FunkyMonkey-Delivery and X-FunkyMonkey-Signature headers. Verify the signature with the webhook's secret:">
          <pre className="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100">{SIGNATURE_EXAMPLE}</pre>
        </Section>
      </div>

      <Modal open={dialog === 'client'} title="Create API client" onClose={closeDialog}>
        {secret ? (
          <SecretBox {...secret} />
        ) : (
          <CreateForm label="Name" placeholder="Funky Monkey portal" pending={createClient.isPending} error={formError} onCreate={(v) => createClient.mutate(v)} />
        )}
      </Modal>
      <Modal open={dialog === 'webhook'} title="Add webhook" onClose={closeDialog}>
        {secret ? (
          <SecretBox {...secret} />
        ) : (
          <CreateForm label="URL" placeholder="https://portal.funkymonkey.in/hooks/leads" pending={createWebhook.isPending} error={formError} onCreate={(v) => createWebhook.mutate(v)} />
        )}
      </Modal>
      <ConfirmDialog
        open={removing !== null}
        title={removing?.kind === 'clients' ? 'Revoke API client?' : 'Delete webhook?'}
        message={removing?.kind === 'clients' ? `"${removing?.name}" will immediately lose access to the API.` : `Stop sending events to ${removing?.name}?`}
        confirmLabel={removing?.kind === 'clients' ? 'Revoke' : 'Delete'}
        loading={remove.isPending}
        onConfirm={() => removing && remove.mutate(removing)}
        onClose={() => setRemoving(null)}
      />
    </>
  )
}
