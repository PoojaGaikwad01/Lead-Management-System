import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useToast } from '../../components/Toast'
import { Badge, Button, CheckboxField, ErrorState, ListSkeleton, PageHeader, SelectField } from '../../components/ui'
import { api, errorMessage, fieldErrors, type FieldErrors } from '../../lib/api'
import { dateTime } from '../../lib/format'
import { IMPORT_STATUS, SOURCE } from '../../lib/labels'
import type { ImportDetail } from '../../lib/types'
import { importTitle } from './ImportsPage'

function MappingStep({ item }: { item: ImportDetail }) {
  const queryClient = useQueryClient()
  const toast = useToast()
  const headers = item.headers ?? []
  const [mapping, setMapping] = useState<Record<string, string>>({ ...item.mapping })
  const [save, setSave] = useState(true)
  const [errors, setErrors] = useState<FieldErrors>({})

  const run = useMutation({
    mutationFn: () => api.post<{ data: ImportDetail }>(`/imports/${item.id}/run`, { mapping, save_mapping: save }),
    onSuccess: ({ data }) => {
      queryClient.setQueryData(['import', item.id], data.data)
      queryClient.invalidateQueries({ queryKey: ['imports'] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      queryClient.invalidateQueries({ queryKey: ['lead-summary'] })
      toast(`Import finished: ${data.data.created_count} created, ${data.data.duplicate_count} duplicates, ${data.data.error_count} errors`)
    },
    onError: (err) => {
      const errs = fieldErrors(err)
      setErrors(errs)
      toast(Object.values(errs)[0] ?? errorMessage(err), 'error')
    },
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    setErrors({})
    run.mutate()
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-5">
      <section className="rounded-xl border border-gray-200 bg-white p-5">
        <h2 className="text-lg font-bold">Map columns</h2>
        <p className="mb-4 text-sm text-gray-600">Choose which column holds each value. Only the phone column is required.</p>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {(item.targets ?? []).map((target) => (
            <SelectField
              key={target.key}
              label={target.required ? `${target.label} *` : target.label}
              value={mapping[target.key] ?? ''}
              error={errors[`mapping.${target.key}`]}
              onChange={(e) => setMapping((m) => ({ ...m, [target.key]: e.target.value }))}
            >
              <option value="">— Not in this file —</option>
              {headers.map((header) => (
                <option key={header} value={header}>
                  {header}
                </option>
              ))}
            </SelectField>
          ))}
        </div>
        <div className="mt-3">
          <CheckboxField label="Save this mapping as the default for future files" checked={save} onChange={(e) => setSave(e.target.checked)} />
        </div>
      </section>

      {item.preview && item.preview.length > 0 && (
        <section className="rounded-xl border border-gray-200 bg-white p-5">
          <h2 className="mb-3 text-lg font-bold">Preview (first {item.preview.length} rows)</h2>
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead>
                <tr className="border-b border-gray-200">
                  {headers.map((header) => (
                    <th key={header} scope="col" className="whitespace-nowrap px-3 py-2 font-semibold text-gray-700">
                      {header}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {item.preview.map((row, i) => (
                  <tr key={i} className="border-b border-gray-100">
                    {headers.map((header, j) => (
                      <td key={header} className="whitespace-nowrap px-3 py-2 text-gray-800">
                        {row[j] ?? ''}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="mt-2 text-sm text-gray-600">{item.total_rows} rows in total.</p>
        </section>
      )}

      <div>
        <Button type="submit" loading={run.isPending}>
          Import {item.total_rows} rows
        </Button>
      </div>
    </form>
  )
}

function Summary({ item }: { item: ImportDetail }) {
  const cards = [
    { label: 'Rows', value: item.total_rows },
    { label: 'Created', value: item.created_count },
    { label: 'Duplicates (merged)', value: item.duplicate_count },
    { label: 'Errors', value: item.error_count, alert: item.error_count > 0 },
  ]

  return (
    <div className="flex flex-col gap-5">
      <dl className="grid grid-cols-2 gap-2 lg:grid-cols-4">
        {cards.map((card) => (
          <div key={card.label} className={`rounded-xl border px-4 py-3 ${card.alert ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white'}`}>
            <dt className={`text-sm font-semibold ${card.alert ? 'text-red-800' : 'text-gray-600'}`}>{card.label}</dt>
            <dd className={`text-3xl font-extrabold ${card.alert ? 'text-red-800' : 'text-gray-900'}`}>{card.value}</dd>
          </div>
        ))}
      </dl>

      {item.errors.length > 0 && (
        <section className="rounded-xl border border-gray-200 bg-white p-5">
          <h2 className="mb-2 text-lg font-bold">Errors</h2>
          <ul className="flex flex-col gap-1 text-sm">
            {item.errors.map((error, i) => (
              <li key={i} className="text-gray-800">
                {error.row !== null && <span className="font-semibold">Row {error.row}: </span>}
                {error.message}
              </li>
            ))}
          </ul>
          {item.error_count > item.errors.length && (
            <p className="mt-2 text-sm text-gray-600">Showing the first {item.errors.length} of {item.error_count} errors.</p>
          )}
        </section>
      )}

      {item.message_text && (
        <section className="rounded-xl border border-gray-200 bg-white p-5">
          <h2 className="mb-2 text-lg font-bold">Agency message</h2>
          <pre className="whitespace-pre-wrap font-sans text-sm text-gray-800">{item.message_text}</pre>
        </section>
      )}
    </div>
  )
}

export function ImportDetailPage() {
  const id = Number(useParams().importId)
  const item = useQuery({
    queryKey: ['import', id],
    queryFn: async () => (await api.get<{ data: ImportDetail }>(`/imports/${id}`)).data.data,
  })

  return (
    <>
      <Link to="/imports" className="mb-3 inline-block text-sm font-semibold text-brand-700 underline">
        ← All imports
      </Link>
      {item.isPending ? (
        <ListSkeleton rows={3} />
      ) : item.isError ? (
        <ErrorState message={errorMessage(item.error)} onRetry={() => item.refetch()} />
      ) : (
        <>
          <PageHeader
            title={importTitle(item.data)}
            description={`${SOURCE[item.data.source]} · ${dateTime(item.data.created_at)}${
              item.data.uploaded_by ? ` · ${item.data.uploaded_by.name}` : item.data.sender_phone ? ` · from ${item.data.sender_phone}` : ''
            }`}
            action={<Badge tone={IMPORT_STATUS[item.data.status].tone}>{IMPORT_STATUS[item.data.status].label}</Badge>}
          />
          {item.data.status === 'pending' || item.data.status === 'needs_mapping' ? (
            <MappingStep item={item.data} />
          ) : (
            <Summary item={item.data} />
          )}
        </>
      )}
    </>
  )
}
