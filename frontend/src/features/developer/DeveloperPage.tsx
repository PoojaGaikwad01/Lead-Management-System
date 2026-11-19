import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useToast } from '../../components/Toast'
import { Badge, Button, EmptyState, ErrorState, ListSkeleton, PageHeader, TextField } from '../../components/ui'
import { api, errorMessage, fieldErrors, type FieldErrors } from '../../lib/api'
import { useRealtimeConnected, useRealtimeEvent } from '../../lib/realtime'
import type { Message } from '../../lib/types'

type Preset = 'customer-text' | 'customer-media' | 'agency-text' | 'agency-file'

const PRESETS: Record<Preset, { label: string; agency: boolean; text: string; file: boolean }> = {
  'customer-text': {
    label: 'Customer text',
    agency: false,
    text: 'Hi! I want to book a birthday party for my son next month, around 15 kids. What packages do you have?',
    file: false,
  },
  'customer-media': { label: 'Customer media', agency: false, text: 'Sharing the details here', file: true },
  'agency-text': {
    label: 'Agency text',
    agency: true,
    text: 'Name: Rahul Sharma\nPhone: 98765 43210\nType: Birthday Party\nBranch: Baner\nParty date: 12/12/2026\nKids: 15\n\nName: Sneha Patil\nPhone: +91 91234 56789\nType: Membership\nBranch: Wakad',
    file: false,
  },
  'agency-file': { label: 'Agency Excel/CSV', agency: true, text: '', file: true },
}

const SAMPLE_CSV =
  'Name,Phone,Lead Type,Branch,Notes\n' +
  'Amit Kulkarni,9822012345,Birthday Party,Baner,Party for 20 kids\n' +
  'Pooja Desai,+91 98500 11223,Play Zone Visit,Wakad,Weekend visit\n' +
  'Invalid Row,12345,General,,Bad phone number\n'

function AgencyNumbers({ numbers }: { numbers: string[] }) {
  const queryClient = useQueryClient()
  const toast = useToast()
  const [value, setValue] = useState(numbers.join(', '))
  const [error, setError] = useState<string>()

  const save = useMutation({
    mutationFn: () =>
      api.put('/settings/agency_numbers', {
        value: value.split(',').map((n) => n.trim()).filter(Boolean),
      }),
    onSuccess: () => {
      setError(undefined)
      queryClient.invalidateQueries({ queryKey: ['settings', 'agency_numbers'] })
      toast('Agency numbers saved')
    },
    onError: (err) => setError(Object.values(fieldErrors(err))[0] ?? errorMessage(err)),
  })

  return (
    <form
      className="flex flex-wrap items-end gap-3"
      onSubmit={(e) => {
        e.preventDefault()
        save.mutate()
      }}
    >
      <div className="min-w-64 flex-1">
        <TextField label="Agency WhatsApp numbers (comma-separated)" value={value} error={error} onChange={(e) => setValue(e.target.value)} />
      </div>
      <Button type="submit" variant="secondary" loading={save.isPending}>
        Save numbers
      </Button>
    </form>
  )
}

function Simulator({ agencyNumbers }: { agencyNumbers: string[] }) {
  const toast = useToast()
  const [preset, setPreset] = useState<Preset>('customer-text')
  const [phone, setPhone] = useState('+91 98765 43210')
  const [name, setName] = useState('Priya Mehta')
  const [text, setText] = useState(PRESETS['customer-text'].text)
  const [file, setFile] = useState<File | null>(null)
  const [errors, setErrors] = useState<FieldErrors>({})

  const choose = (next: Preset) => {
    setPreset(next)
    setText(PRESETS[next].text)
    setFile(null)
    setErrors({})
    if (PRESETS[next].agency) {
      setPhone(agencyNumbers[0] ?? '')
      setName('Ad Agency')
    } else if (PRESETS[preset].agency) {
      setPhone('+91 98765 43210')
      setName('Priya Mehta')
    }
  }

  const send = useMutation({
    mutationFn: () => {
      const form = new FormData()
      form.append('phone', phone)
      if (name) form.append('name', name)
      if (text) form.append('text', text)
      if (file) form.append('file', file)
      return api.post('/dev/simulate', form)
    },
    onSuccess: () => toast('Webhook sent. It appears below once the queue worker processes it.'),
    onError: (err) => {
      const errs = fieldErrors(err)
      setErrors(errs)
      if (!Object.keys(errs).length) toast(errorMessage(err), 'error')
    },
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    setErrors({})
    send.mutate()
  }

  const current = PRESETS[preset]

  return (
    <form onSubmit={submit} className="flex flex-col gap-4">
      <fieldset className="flex flex-wrap gap-2">
        <legend className="sr-only">Message type</legend>
        {(Object.keys(PRESETS) as Preset[]).map((key) => (
          <label
            key={key}
            className={`flex min-h-11 cursor-pointer items-center rounded-lg border px-3 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand-600 ${
              preset === key ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-gray-300 bg-white text-gray-700'
            }`}
          >
            <input type="radio" name="preset" className="sr-only" checked={preset === key} onChange={() => choose(key)} />
            {PRESETS[key].label}
          </label>
        ))}
      </fieldset>

      {current.agency && agencyNumbers.length === 0 && (
        <p role="alert" className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
          Add an agency number above first, otherwise this is treated as a customer message.
        </p>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="From phone" value={phone} error={errors.phone} onChange={(e) => setPhone(e.target.value)} />
        <TextField label="Sender name" value={name} error={errors.name} onChange={(e) => setName(e.target.value)} />
      </div>

      <div className="flex flex-col gap-1">
        <label htmlFor="sim-text" className="text-sm font-medium text-gray-800">
          Message text
        </label>
        <textarea
          id="sim-text"
          rows={current.agency ? 8 : 3}
          value={text}
          onChange={(e) => setText(e.target.value)}
          aria-invalid={!!errors.text}
          className="rounded-lg border border-gray-300 p-3 text-sm focus-visible:outline-2 focus-visible:outline-brand-600"
        />
        {errors.text && <p className="text-sm text-red-700">{errors.text}</p>}
      </div>

      {current.file && (
        <div className="flex flex-wrap items-center gap-3">
          <label className="text-sm font-medium text-gray-800">
            Attachment
            <input
              type="file"
              className="mt-1 block text-sm"
              accept={preset === 'agency-file' ? '.csv,.xls,.xlsx' : 'image/*,audio/*,video/*,.pdf,.csv,.xls,.xlsx'}
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            />
          </label>
          {preset === 'agency-file' && (
            <Button variant="secondary" onClick={() => setFile(new File([SAMPLE_CSV], 'agency-leads.csv', { type: 'text/csv' }))}>
              Use sample CSV
            </Button>
          )}
          {file && <span className="text-sm text-gray-700">Selected: {file.name}</span>}
          {errors.file && <p className="w-full text-sm text-red-700">{errors.file}</p>}
        </div>
      )}

      <div>
        <Button type="submit" loading={send.isPending}>
          Send webhook
        </Button>
      </div>
    </form>
  )
}

function LiveMessages() {
  const queryClient = useQueryClient()
  const connected = useRealtimeConnected()
  const messages = useQuery({
    queryKey: ['dev-messages'],
    queryFn: async () => (await api.get<{ data: Message[] }>('/dev/messages')).data.data,
    refetchInterval: connected ? false : 5000,
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['dev-messages'] })
  useRealtimeEvent(['admins'], 'message.created', refresh)
  useRealtimeEvent(['admins'], 'message.updated', refresh)

  return (
    <section aria-labelledby="live-heading" className="rounded-xl border border-gray-200 bg-white p-5">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 id="live-heading" className="text-lg font-bold">
          Received messages
        </h2>
        <Badge tone={connected ? 'green' : 'gray'}>{connected ? 'Live' : 'Polling every 5s'}</Badge>
      </div>
      {messages.isPending ? (
        <ListSkeleton rows={3} />
      ) : messages.isError ? (
        <ErrorState message={errorMessage(messages.error)} onRetry={() => messages.refetch()} />
      ) : messages.data.length === 0 ? (
        <EmptyState title="No messages yet" description="Send a webhook above, with `php artisan queue:work` running." />
      ) : (
        <ul className="divide-y divide-gray-100">
          {messages.data.map((m) => (
            <li key={m.id} className="flex flex-col gap-1 py-3">
              <div className="flex flex-wrap items-center gap-2 text-sm">
                <Badge tone={m.direction === 'in' ? 'blue' : 'purple'}>{m.direction === 'in' ? 'Incoming' : 'Outgoing'}</Badge>
                {m.is_agency && <Badge tone="orange">Agency</Badge>}
                <Badge>{m.type}</Badge>
                <span className="font-semibold">{m.contact?.name ?? 'Unknown'}</span>
                <span className="text-gray-600">{m.contact?.phone}</span>
                <span className="ml-auto text-xs text-gray-600">
                  {new Date(m.sent_at).toLocaleString()} · {m.status}
                </span>
              </div>
              {m.body && <p className="whitespace-pre-line text-sm text-gray-800">{m.body}</p>}
              {m.media &&
                (m.media.url ? (
                  <a href={m.media.url} target="_blank" rel="noreferrer" className="text-sm font-semibold text-brand-700 underline">
                    Open {m.media.name}
                  </a>
                ) : (
                  <p className="text-sm text-red-700">Attachment {m.media.name} could not be downloaded</p>
                ))}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

export function DeveloperPage() {
  const agency = useQuery({
    queryKey: ['settings', 'agency_numbers'],
    queryFn: async () => (await api.get<{ data: string[] }>('/settings/agency_numbers')).data.data,
  })

  return (
    <>
      <PageHeader
        title="WhatsApp simulator"
        description="Development only. Sends Chatwoot-style webhooks through the mock connector (WHATSAPP_PROVIDER=mock)."
      />
      <div className="flex flex-col gap-6">
        <section className="rounded-xl border border-gray-200 bg-white p-5">
          {agency.isPending ? (
            <ListSkeleton rows={1} />
          ) : agency.isError ? (
            <ErrorState message={errorMessage(agency.error)} onRetry={() => agency.refetch()} />
          ) : (
            <div className="flex flex-col gap-5">
              <AgencyNumbers numbers={agency.data} />
              <Simulator agencyNumbers={agency.data} />
            </div>
          )}
        </section>
        <LiveMessages />
      </div>
    </>
  )
}
