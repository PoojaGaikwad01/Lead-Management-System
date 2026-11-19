import { useQuery } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Bar, BarChart, LabelList, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { EmptyState, ErrorState, ListSkeleton, PageHeader, SelectField, TextField, focusRing } from '../../components/ui'
import { api, errorMessage } from '../../lib/api'
import type { Breakdown, ReportData } from '../../lib/types'

const BAR = '#c2410c'
const INK = '#374151'
const MUTED = '#6b7280'

type Preset = '7' | '30' | 'month' | 'last_month' | 'custom'

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

function rangeFor(preset: Preset, custom: { from: string; to: string }): { from: string; to: string } {
  const today = new Date()
  const daysAgo = (n: number) => new Date(today.getFullYear(), today.getMonth(), today.getDate() - n)
  switch (preset) {
    case '7':
      return { from: iso(daysAgo(6)), to: iso(today) }
    case 'month':
      return { from: iso(new Date(today.getFullYear(), today.getMonth(), 1)), to: iso(today) }
    case 'last_month':
      return { from: iso(new Date(today.getFullYear(), today.getMonth() - 1, 1)), to: iso(new Date(today.getFullYear(), today.getMonth(), 0)) }
    case 'custom':
      return custom
    default:
      return { from: iso(daysAgo(29)), to: iso(today) }
  }
}

export function minutesLabel(minutes: number | null): string {
  if (minutes === null) return '—'
  if (minutes < 60) return `${minutes} min`
  const hours = Math.floor(minutes / 60)
  if (hours < 48) return `${hours} hr${hours === 1 ? '' : 's'}${minutes % 60 ? ` ${minutes % 60} min` : ''}`
  return `${Math.round(hours / 24)} days`
}

const percent = (part: number, whole: number) => (whole ? `${Math.round((part / whole) * 100)}%` : '—')

function Tile({ label, value, note }: { label: string; value: ReactNode; note?: string }) {
  return (
    <div className="rounded-xl border border-gray-200 bg-white px-4 py-3">
      <dt className="text-sm font-semibold text-gray-600">{label}</dt>
      <dd className="text-3xl font-extrabold text-gray-900">{value}</dd>
      {note && <dd className="text-xs text-gray-600">{note}</dd>}
    </div>
  )
}

function Card({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="rounded-xl border border-gray-200 bg-white p-5">
      <h2 className="mb-3 text-lg font-bold text-gray-900">{title}</h2>
      {children}
    </section>
  )
}

function BreakdownTooltip({ active, payload }: { active?: boolean; payload?: ReadonlyArray<{ payload?: unknown }> }) {
  const row = payload?.[0]?.payload as Breakdown | undefined
  if (!active || !row) return null
  return (
    <div className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-lg">
      <p className="font-semibold text-gray-900">{row.label}</p>
      <p className="text-gray-700">{row.total} leads</p>
      <p className="text-gray-700">
        {row.converted} converted ({percent(row.converted, row.total)})
      </p>
    </div>
  )
}

function BreakdownChart({ title, rows }: { title: string; rows: Breakdown[] }) {
  return (
    <Card title={title}>
      {rows.length === 0 ? (
        <p className="text-sm text-gray-600">No leads in this period.</p>
      ) : (
        <>
          <div aria-hidden="true" style={{ height: rows.length * 36 + 16 }}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={rows} layout="vertical" margin={{ top: 0, right: 40, bottom: 0, left: 0 }} barCategoryGap={6}>
                <XAxis type="number" hide allowDecimals={false} />
                <YAxis type="category" dataKey="label" width={130} tickLine={false} axisLine={false} tick={{ fill: INK, fontSize: 13 }} />
                <Tooltip content={<BreakdownTooltip />} cursor={{ fill: '#f3f4f6' }} />
                <Bar dataKey="total" fill={BAR} barSize={20} radius={[0, 4, 4, 0]} isAnimationActive={false}>
                  <LabelList dataKey="total" position="right" fill={INK} fontSize={13} />
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
          <table className="sr-only">
            <caption>{title}</caption>
            <thead>
              <tr>
                <th scope="col">Group</th>
                <th scope="col">Leads</th>
                <th scope="col">Converted</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.label}>
                  <th scope="row">{row.label}</th>
                  <td>{row.total}</td>
                  <td>{row.converted}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="mt-2 text-xs" style={{ color: MUTED }}>
            Hover a bar to see conversions.
          </p>
        </>
      )}
    </Card>
  )
}

export function ReportsPage() {
  const [preset, setPreset] = useState<Preset>('30')
  const [custom, setCustom] = useState(() => rangeFor('30', { from: '', to: '' }))
  const range = rangeFor(preset, custom)
  const report = useQuery({
    queryKey: ['reports', range],
    queryFn: async () => (await api.get<{ data: ReportData }>('/reports', { params: range })).data.data,
    enabled: Boolean(range.from && range.to),
  })
  const exportUrl = `/api/reports/export?from=${range.from}&to=${range.to}`

  return (
    <>
      <PageHeader
        title="Reports"
        description="Leads created in the selected period."
        action={
          <a
            href={exportUrl}
            className={`inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50 ${focusRing}`}
          >
            Export leads (CSV)
          </a>
        }
      />

      <div className="mb-5 flex flex-wrap items-end gap-3">
        <div className="w-48">
          <SelectField label="Period" value={preset} onChange={(e) => setPreset(e.target.value as Preset)}>
            <option value="7">Last 7 days</option>
            <option value="30">Last 30 days</option>
            <option value="month">This month</option>
            <option value="last_month">Last month</option>
            <option value="custom">Custom range</option>
          </SelectField>
        </div>
        {preset === 'custom' && (
          <>
            <div className="w-44">
              <TextField label="From" type="date" value={custom.from} max={custom.to} onChange={(e) => setCustom((c) => ({ ...c, from: e.target.value }))} />
            </div>
            <div className="w-44">
              <TextField label="To" type="date" value={custom.to} min={custom.from} onChange={(e) => setCustom((c) => ({ ...c, to: e.target.value }))} />
            </div>
          </>
        )}
      </div>

      {report.isPending ? (
        <ListSkeleton rows={4} />
      ) : report.isError ? (
        <ErrorState message={errorMessage(report.error)} onRetry={() => report.refetch()} />
      ) : report.data.totals.leads === 0 ? (
        <EmptyState title="No leads in this period" description="Choose a longer period to see figures." />
      ) : (
        <div className="flex flex-col gap-5">
          <dl className="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
            <Tile label="Leads" value={report.data.totals.leads} />
            <Tile label="Converted" value={report.data.totals.converted} />
            <Tile label="Conversion rate" value={report.data.totals.conversion_rate === null ? '—' : `${report.data.totals.conversion_rate}%`} />
            <Tile label="Lost" value={report.data.totals.lost} />
            <Tile label="Avg time to assign" value={minutesLabel(report.data.totals.avg_minutes_to_assign)} note="lead created → assigned" />
            <Tile label="Avg time to reply" value={minutesLabel(report.data.totals.avg_minutes_to_reply)} note="assigned → reply sent" />
          </dl>

          <div className="grid gap-5 lg:grid-cols-2">
            <BreakdownChart title="Leads by type" rows={report.data.by_type} />
            <BreakdownChart title="Leads by source" rows={report.data.by_source} />
            <BreakdownChart title="Leads by branch" rows={report.data.by_branch} />
            <Card title="Lost reasons">
              {report.data.lost_reasons.length === 0 ? (
                <p className="text-sm text-gray-600">No lost leads in this period.</p>
              ) : (
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-gray-200 text-gray-600">
                      <th scope="col" className="py-2 font-semibold">Reason</th>
                      <th scope="col" className="py-2 text-right font-semibold">Leads</th>
                      <th scope="col" className="py-2 text-right font-semibold">Share</th>
                    </tr>
                  </thead>
                  <tbody>
                    {report.data.lost_reasons.map((row) => (
                      <tr key={row.label} className="border-b border-gray-100">
                        <th scope="row" className="py-2 font-medium text-gray-900">{row.label}</th>
                        <td className="py-2 text-right">{row.total}</td>
                        <td className="py-2 text-right">{percent(row.total, report.data.totals.lost)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Card>
          </div>

          <Card title="Assignee performance">
            {report.data.by_assignee.length === 0 ? (
              <p className="text-sm text-gray-600">No leads were assigned in this period.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-160 text-left text-sm">
                  <thead>
                    <tr className="border-b border-gray-200 text-gray-600">
                      {['Assignee', 'Leads', 'Replied', 'Awaiting reply', 'Converted', 'Avg time to reply', 'Reminders received'].map((h, i) => (
                        <th key={h} scope="col" className={`py-2 font-semibold ${i ? 'text-right' : ''}`}>
                          {h}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {report.data.by_assignee.map((row) => (
                      <tr key={row.label} className="border-b border-gray-100">
                        <th scope="row" className="py-2 font-medium text-gray-900">{row.label}</th>
                        <td className="py-2 text-right">{row.total}</td>
                        <td className="py-2 text-right">{row.replied}</td>
                        <td className={`py-2 text-right ${row.awaiting_reply ? 'font-semibold text-red-700' : ''}`}>{row.awaiting_reply}</td>
                        <td className="py-2 text-right">
                          {row.converted} <span className="text-gray-600">({percent(row.converted, row.total)})</span>
                        </td>
                        <td className="py-2 text-right">{minutesLabel(row.avg_minutes_to_reply)}</td>
                        <td className="py-2 text-right">{row.reminders}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        </div>
      )}
    </>
  )
}
