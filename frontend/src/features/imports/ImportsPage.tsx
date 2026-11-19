import { useMutation, useQuery } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useToast } from '../../components/Toast'
import { Badge, Button, EmptyState, ErrorState, ListSkeleton, PageHeader, focusRing } from '../../components/ui'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { dateTime } from '../../lib/format'
import { IMPORT_STATUS, SOURCE } from '../../lib/labels'
import type { ImportDetail, ImportSummary, Paginated } from '../../lib/types'

export function importTitle(item: ImportSummary): string {
  return item.original_name ?? (item.source === 'agency_text' ? 'Agency text message' : 'Import')
}

function Upload() {
  const navigate = useNavigate()
  const toast = useToast()
  const [file, setFile] = useState<File | null>(null)
  const [error, setError] = useState<string>()

  const upload = useMutation({
    mutationFn: () => {
      const form = new FormData()
      if (file) form.append('file', file)
      return api.post<{ data: ImportDetail }>('/imports', form)
    },
    onSuccess: ({ data }) => navigate(`/imports/${data.data.id}`),
    onError: (err) => {
      const message = fieldErrors(err).file ?? errorMessage(err)
      setError(message)
      toast(message, 'error')
    },
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    setError(undefined)
    if (file) upload.mutate()
  }

  return (
    <form onSubmit={submit} className="mb-6 rounded-xl border border-gray-200 bg-white p-5">
      <h2 className="text-lg font-bold">Upload Excel or CSV</h2>
      <p className="mb-3 text-sm text-gray-600">
        Phones are normalised to +91 and matched against existing leads. You can check the column mapping before anything is imported.
      </p>
      <div className="flex flex-wrap items-center gap-3">
        <label className="text-sm font-medium text-gray-800">
          <span className="sr-only">File</span>
          <input
            type="file"
            accept=".csv,.xls,.xlsx"
            aria-invalid={!!error}
            className="block text-sm file:mr-3 file:min-h-11 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:font-semibold file:text-brand-700"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
        </label>
        <Button type="submit" disabled={!file} loading={upload.isPending}>
          Upload and map columns
        </Button>
      </div>
      {error && <p className="mt-2 text-sm text-red-700">{error}</p>}
    </form>
  )
}

export function ImportsPage() {
  const [page, setPage] = useState(1)
  const imports = useQuery({
    queryKey: ['imports', page],
    queryFn: async () => (await api.get<Paginated<ImportSummary>>('/imports', { params: { page } })).data,
  })

  return (
    <>
      <PageHeader title="Imports" description="Agency files and messages are imported automatically; upload a file here as a backup." />
      <Upload />

      <h2 className="mb-2 text-lg font-bold">Import history</h2>
      {imports.isPending ? (
        <ListSkeleton />
      ) : imports.isError ? (
        <ErrorState message={errorMessage(imports.error)} onRetry={() => imports.refetch()} />
      ) : imports.data.data.length === 0 ? (
        <EmptyState title="No imports yet" description="Agency messages, agency files and uploads will be listed here." />
      ) : (
        <>
          <ul className="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
            {imports.data.data.map((item) => (
              <li key={item.id}>
                <Link to={`/imports/${item.id}`} className={`flex flex-wrap items-center gap-x-4 gap-y-1 p-4 hover:bg-gray-50 ${focusRing}`}>
                  <div className="min-w-0 flex-1 basis-56">
                    <p className="truncate font-semibold text-gray-900">{importTitle(item)}</p>
                    <p className="text-sm text-gray-600">
                      {dateTime(item.created_at)} · {item.uploaded_by?.name ?? (item.sender_phone ? `Agency ${item.sender_phone}` : 'System')}
                    </p>
                  </div>
                  <Badge tone="slate">{SOURCE[item.source]}</Badge>
                  <Badge tone={IMPORT_STATUS[item.status].tone}>{IMPORT_STATUS[item.status].label}</Badge>
                  {item.status === 'completed' && (
                    <span className="text-sm text-gray-700">
                      {item.created_count} created · {item.duplicate_count} duplicates ·{' '}
                      <span className={item.error_count ? 'font-semibold text-red-700' : ''}>{item.error_count} errors</span>
                    </span>
                  )}
                </Link>
              </li>
            ))}
          </ul>
          {imports.data.meta.last_page > 1 && (
            <div className="mt-3 flex items-center justify-center gap-3">
              <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                Previous
              </Button>
              <span className="text-sm text-gray-700">
                Page {page} of {imports.data.meta.last_page}
              </span>
              <Button variant="secondary" disabled={page >= imports.data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
                Next
              </Button>
            </div>
          )}
        </>
      )}
    </>
  )
}
