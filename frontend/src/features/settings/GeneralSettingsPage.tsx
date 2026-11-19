import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { useToast } from '../../components/Toast'
import { Badge, Button, CheckboxField, ErrorState, ListSkeleton, PageHeader, TextAreaField, TextField } from '../../components/ui'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import type { ReminderRules, SettingsData } from '../../lib/types'

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return (
    <section className="rounded-xl border border-gray-200 bg-white p-5">
      <h2 className="text-lg font-bold text-gray-900">{title}</h2>
      {description && <p className="mb-4 mt-1 text-sm text-gray-600">{description}</p>}
      {children}
    </section>
  )
}

function useSave(key: string, success: string) {
  const queryClient = useQueryClient()
  const toast = useToast()
  return useMutation({
    mutationFn: (value: unknown) => api.put(`/settings/${key}`, { value }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] })
      toast(success)
    },
    onError: (error) => toast(Object.values(fieldErrors(error))[0] ?? errorMessage(error), 'error'),
  })
}

function Connection({ status }: { status: SettingsData['status'] }) {
  const toast = useToast()
  const check = useMutation({
    mutationFn: () => api.post<{ message: string }>('/settings/provider/check'),
    onSuccess: ({ data }) => toast(data.message),
    onError: (error) => toast(errorMessage(error), 'error'),
  })
  const copy = () => navigator.clipboard?.writeText(status.webhook_url).then(() => toast('Webhook URL copied'))

  return (
    <Section title="WhatsApp connection" description="Provider and credentials are set in backend/.env (WHATSAPP_PROVIDER and CHATWOOT_*). Secrets are never stored in the database.">
      <dl className="flex flex-col gap-2 text-sm">
        <div className="flex flex-wrap items-center gap-2">
          <dt className="w-32 text-gray-600">Provider</dt>
          <dd className="flex items-center gap-2 font-semibold capitalize">
            {status.whatsapp_provider}
            {status.whatsapp_provider === 'mock' ? (
              <Badge tone="amber">Test mode</Badge>
            ) : (
              <Badge tone={status.provider_configured ? 'green' : 'red'}>{status.provider_configured ? 'Configured' : 'Missing settings'}</Badge>
            )}
          </dd>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <dt className="w-32 text-gray-600">Webhook URL</dt>
          <dd className="flex min-w-0 flex-1 items-center gap-2">
            <code className="min-w-0 break-all rounded bg-gray-100 px-2 py-1 text-xs">{status.webhook_url}</code>
            <Button variant="ghost" className="min-h-9 px-2 text-brand-700" onClick={copy}>
              Copy
            </Button>
          </dd>
        </div>
      </dl>
      <Button variant="secondary" className="mt-4" loading={check.isPending} onClick={() => check.mutate()}>
        Test connection
      </Button>
    </Section>
  )
}

function AgencyNumbers({ numbers }: { numbers: string[] }) {
  const [value, setValue] = useState(numbers.join('\n'))
  const save = useSave('agency_numbers', 'Agency numbers saved')
  const submit = (e: FormEvent) => {
    e.preventDefault()
    save.mutate(value.split(/[\n,]/).map((n) => n.trim()).filter(Boolean))
  }

  return (
    <Section title="Agency numbers" description="Messages from these WhatsApp numbers are read as agency leads (text or Excel/CSV), not as customer chats.">
      <form onSubmit={submit} className="flex flex-col gap-3">
        <TextAreaField label="One number per line" rows={4} value={value} onChange={(e) => setValue(e.target.value)} placeholder="+91 90000 00001" />
        <div>
          <Button type="submit" loading={save.isPending}>
            Save numbers
          </Button>
        </div>
      </form>
    </Section>
  )
}

function Reminders({ rules }: { rules: ReminderRules }) {
  const [form, setForm] = useState(rules)
  const save = useSave('reminder_rules', 'Reminder rules saved')

  return (
    <Section
      title="Reply time & reminders"
      description="A lead counts as overdue when no reply is marked within this many hours of assignment. Automatic reminders repeat once per period until the reply is marked sent."
    >
      <form
        onSubmit={(e) => {
          e.preventDefault()
          save.mutate({ ...form, after_hours: Number(form.after_hours) })
        }}
        className="flex flex-col gap-3"
      >
        <div className="w-48">
          <TextField
            label="Reply expected within (hours)"
            type="number"
            min={1}
            max={168}
            value={form.after_hours}
            onChange={(e) => setForm({ ...form, after_hours: Number(e.target.value) })}
          />
        </div>
        <CheckboxField
          label="Send automatic reminders when a reply is overdue"
          checked={form.auto_enabled}
          onChange={(e) => setForm({ ...form, auto_enabled: e.target.checked })}
        />
        <CheckboxField
          label="Also alert staff on WhatsApp for new assignments and reminders (uses their phone number)"
          checked={form.whatsapp_alert}
          onChange={(e) => setForm({ ...form, whatsapp_alert: e.target.checked })}
        />
        <div>
          <Button type="submit" loading={save.isPending}>
            Save reminder rules
          </Button>
        </div>
      </form>
    </Section>
  )
}

function AiToggle({ enabled, keyConfigured, model }: { enabled: boolean; keyConfigured: boolean; model: string }) {
  const save = useSave('ai_extraction_enabled', enabled ? 'AI extraction turned off' : 'AI extraction turned on')

  return (
    <Section
      title="AI extraction (optional)"
      description="When on, Claude fills in lead type, branch and details that keyword matching missed. Everything works without it."
    >
      {!keyConfigured && (
        <p className="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
          No ANTHROPIC_API_KEY in backend/.env, so AI stays off even if switched on here.
        </p>
      )}
      <div className="flex flex-wrap items-center gap-3">
        <Badge tone={enabled && keyConfigured ? 'green' : 'gray'}>{enabled && keyConfigured ? `On (${model})` : 'Off'}</Badge>
        <Button variant="secondary" loading={save.isPending} onClick={() => save.mutate(!enabled)}>
          {enabled ? 'Turn off' : 'Turn on'}
        </Button>
      </div>
    </Section>
  )
}

function ColumnMapping({ mapping, targets }: { mapping: Record<string, string>; targets: SettingsData['mapping_targets'] }) {
  const [form, setForm] = useState<Record<string, string>>({ ...mapping })
  const save = useSave('import_column_mapping', 'Default column mapping saved')

  return (
    <Section
      title="Default import column mapping"
      description="Column headings used for agency Excel/CSV files. Leave blank to let the system guess from the heading."
    >
      <form
        onSubmit={(e) => {
          e.preventDefault()
          save.mutate(Object.fromEntries(Object.entries(form).filter(([, v]) => v.trim())))
        }}
        className="flex flex-col gap-3"
      >
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {targets.map((t) => (
            <TextField
              key={t.key}
              label={t.label}
              value={form[t.key] ?? ''}
              placeholder="Column heading"
              onChange={(e) => setForm({ ...form, [t.key]: e.target.value })}
            />
          ))}
        </div>
        <div className="flex gap-2">
          <Button type="submit" loading={save.isPending}>
            Save mapping
          </Button>
          <Button variant="secondary" onClick={() => setForm({})}>
            Clear all
          </Button>
        </div>
      </form>
    </Section>
  )
}

export function GeneralSettingsPage() {
  const settings = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get<{ data: SettingsData }>('/settings')).data.data,
  })

  return (
    <>
      <PageHeader title="General settings" />
      {settings.isPending ? (
        <ListSkeleton rows={4} />
      ) : settings.isError ? (
        <ErrorState message={errorMessage(settings.error)} onRetry={() => settings.refetch()} />
      ) : (
        <div className="flex flex-col gap-5">
          <Connection status={settings.data.status} />
          <AgencyNumbers numbers={settings.data.agency_numbers} />
          <Reminders rules={settings.data.reminder_rules} />
          <AiToggle
            enabled={settings.data.ai_extraction_enabled}
            keyConfigured={settings.data.status.ai_key_configured}
            model={settings.data.status.ai_model}
          />
          <ColumnMapping mapping={settings.data.import_column_mapping} targets={settings.data.mapping_targets} />
        </div>
      )}
    </>
  )
}
