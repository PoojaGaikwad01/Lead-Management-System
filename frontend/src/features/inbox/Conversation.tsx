import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Fragment, useEffect, useRef, useState, type FormEvent, type KeyboardEvent, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import {
  BackIcon,
  CheckIcon,
  ClockIcon,
  DoubleCheckIcon,
  FileIcon,
  InfoIcon,
  PaperclipIcon,
  PhoneIcon,
  SendIcon,
  CloseIcon,
} from '../../components/icons'
import { useToast } from '../../components/Toast'
import { Button, ErrorState, focusRing } from '../../components/ui'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { dayLabel, timeOfDay } from '../../lib/format'
import type { Lead, Message } from '../../lib/types'
import { useMessages } from './api'
import { Avatar, LeadChips } from './LeadBadges'

const TICKS: Partial<Record<Message['status'], { icon: ReactNode; label: string }>> = {
  pending: { icon: <ClockIcon className="size-3.5" />, label: 'Sending' },
  sent: { icon: <CheckIcon className="size-3.5" />, label: 'Sent' },
  delivered: { icon: <DoubleCheckIcon className="size-4" />, label: 'Delivered' },
  read: { icon: <DoubleCheckIcon className="size-4 text-blue-700" />, label: 'Read' },
}

function Ticks({ status }: { status: Message['status'] }) {
  if (status === 'failed') return <span className="font-semibold text-red-700">Failed</span>
  const tick = TICKS[status]
  if (!tick) return null
  return (
    <span className="inline-flex" title={tick.label}>
      {tick.icon}
      <span className="sr-only">{tick.label}</span>
    </span>
  )
}

function Media({ message }: { message: Message }) {
  const media = message.media
  if (!media) return null
  if (!media.url) return <p className="text-sm italic text-gray-600">Attachment unavailable{media.name ? `: ${media.name}` : ''}</p>

  switch (message.type) {
    case 'image':
      return (
        <a href={media.url} target="_blank" rel="noreferrer" className="block">
          <img src={media.url} alt={media.name ?? 'Image'} loading="lazy" className="max-h-72 rounded-lg object-contain" />
        </a>
      )
    case 'audio':
      return <audio controls src={media.url} aria-label={media.name ?? 'Voice message'} className="w-64 max-w-full" />
    case 'video':
      return <video controls src={media.url} aria-label={media.name ?? 'Video'} className="max-h-72 rounded-lg" />
    default: {
      const ext = (media.name?.split('.').pop() ?? 'file').toUpperCase().slice(0, 4)
      return (
        <div className="flex items-center gap-3 rounded-lg bg-black/5 p-2">
          <span className="grid size-10 shrink-0 place-items-center rounded bg-white text-[10px] font-bold text-gray-700">
            <FileIcon className="size-4" />
            {ext}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold">{media.name ?? 'Document'}</p>
            <div className="flex gap-3 text-sm">
              <a href={media.url} target="_blank" rel="noreferrer" className="font-semibold text-brand-700 underline">
                Open
              </a>
              <a href={`${media.url}?download=1`} className="font-semibold text-brand-700 underline">
                Download
              </a>
            </div>
          </div>
        </div>
      )
    }
  }
}

function Bubble({ message }: { message: Message }) {
  const out = message.direction === 'out'
  return (
    <div className={`flex ${out ? 'justify-end' : 'justify-start'}`}>
      <div
        className={`flex max-w-[85%] flex-col gap-1 rounded-2xl px-3 py-2 shadow-sm sm:max-w-[70%] ${
          out ? 'rounded-br-sm bg-brand-100' : 'rounded-bl-sm bg-white'
        } ${message.status === 'failed' ? 'ring-1 ring-red-300' : ''}`}
      >
        <span className="sr-only">{out ? 'You:' : 'Customer:'}</span>
        <Media message={message} />
        {message.body && <p className="whitespace-pre-wrap wrap-break-word text-sm text-gray-900">{message.body}</p>}
        <div className="flex items-center justify-end gap-1 text-[11px] text-gray-600">
          <time dateTime={message.sent_at}>{timeOfDay(message.sent_at)}</time>
          {out && <Ticks status={message.status} />}
        </div>
      </div>
    </div>
  )
}

function ReplyBox({ leadId }: { leadId: number }) {
  const queryClient = useQueryClient()
  const toast = useToast()
  const [text, setText] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const fileInput = useRef<HTMLInputElement>(null)

  const send = useMutation({
    mutationFn: () => {
      const form = new FormData()
      if (text.trim()) form.append('text', text.trim())
      if (file) form.append('file', file)
      return api.post(`/leads/${leadId}/messages`, form)
    },
    onSuccess: () => {
      setText('')
      setFile(null)
    },
    onError: (error) => {
      const errs = fieldErrors(error)
      toast(errs.file ?? errs.text ?? errorMessage(error), 'error')
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['messages', leadId] })
      queryClient.invalidateQueries({ queryKey: ['leads'] })
    },
  })

  const canSend = (text.trim() !== '' || file !== null) && !send.isPending
  const submit = (event?: FormEvent) => {
    event?.preventDefault()
    if (canSend) send.mutate()
  }
  const onKey = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      submit()
    }
  }

  return (
    <form onSubmit={submit} className="shrink-0 border-t border-gray-200 bg-white p-2">
      {file && (
        <div className="mb-2 flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-1.5 text-sm">
          <PaperclipIcon className="size-4" />
          <span className="flex-1 truncate">{file.name}</span>
          <button type="button" onClick={() => setFile(null)} aria-label="Remove attachment" className={`rounded p-1 ${focusRing}`}>
            <CloseIcon className="size-4" />
          </button>
        </div>
      )}
      <div className="flex items-end gap-2">
        <input
          ref={fileInput}
          type="file"
          className="sr-only"
          tabIndex={-1}
          aria-hidden="true"
          accept="image/*,audio/*,video/mp4,.pdf,.xls,.xlsx,.csv,.doc,.docx"
          onChange={(e) => {
            setFile(e.target.files?.[0] ?? null)
            e.target.value = ''
          }}
        />
        <Button variant="ghost" className="px-3" aria-label="Attach a file" onClick={() => fileInput.current?.click()}>
          <PaperclipIcon />
        </Button>
        <label className="flex-1">
          <span className="sr-only">Type a reply</span>
          <textarea
            rows={1}
            value={text}
            onChange={(e) => setText(e.target.value)}
            onKeyDown={onKey}
            placeholder="Type a reply"
            className="max-h-32 min-h-11 w-full resize-none rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus-visible:outline-2 focus-visible:outline-brand-600"
          />
        </label>
        <Button type="submit" className="px-3" disabled={!canSend} loading={send.isPending} aria-label="Send reply">
          {!send.isPending && <SendIcon />}
        </Button>
      </div>
    </form>
  )
}

export function Conversation({ lead, poll, onShowDetails }: { lead: Lead; poll: boolean; onShowDetails: () => void }) {
  const messages = useMessages(lead.id, poll)
  const bottom = useRef<HTMLDivElement>(null)
  const count = messages.data?.length ?? 0

  useEffect(() => {
    bottom.current?.scrollIntoView({ block: 'end' })
  }, [count, lead.id])

  return (
    <section aria-label={`Conversation with ${lead.contact.name ?? lead.contact.phone}`} className="flex min-h-0 flex-1 flex-col">
      <header className="flex shrink-0 items-center gap-2 border-b border-gray-200 bg-white px-2 py-2 sm:px-3">
        <Link to="/" className={`rounded-lg p-2 lg:hidden ${focusRing}`} aria-label="Back to leads">
          <BackIcon />
        </Link>
        <Avatar lead={lead} size="size-10" />
        <div className="min-w-0 flex-1">
          <h2 className="truncate font-bold text-gray-900">{lead.contact.name ?? lead.contact.phone}</h2>
          <p className="truncate text-xs text-gray-600">{lead.contact.phone}</p>
          <div className="mt-1 hidden sm:block">
            <LeadChips lead={lead} showReply />
          </div>
        </div>
        <a
          href={`tel:${lead.contact.phone}`}
          className={`inline-flex min-h-11 items-center gap-1 rounded-lg px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 ${focusRing}`}
        >
          <PhoneIcon />
          <span className="sr-only sm:not-sr-only">Call</span>
        </a>
        <Button variant="ghost" className="px-3 xl:hidden" onClick={onShowDetails} aria-label="Lead details">
          <InfoIcon />
        </Button>
      </header>

      <div className="min-h-0 flex-1 overflow-y-auto bg-brand-50/60 px-3 py-4">
        {messages.isPending ? (
          <p role="status" className="text-center text-sm text-gray-600">
            Loading messages…
          </p>
        ) : messages.isError ? (
          <ErrorState message={errorMessage(messages.error)} onRetry={() => messages.refetch()} />
        ) : messages.data.length === 0 ? (
          <p className="text-center text-sm text-gray-600">No messages yet. Send a reply to start the conversation.</p>
        ) : (
          <div role="log" aria-live="polite" aria-label="Messages" className="flex flex-col gap-2">
            {messages.data.map((message, i, all) => {
              const day = dayLabel(message.sent_at)
              const separator = i === 0 || dayLabel(all[i - 1].sent_at) !== day
              return (
                <Fragment key={message.id}>
                  {separator && (
                    <div className="my-2 flex justify-center">
                      <span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-700 shadow-sm">{day}</span>
                    </div>
                  )}
                  <Bubble message={message} />
                </Fragment>
              )
            })}
          </div>
        )}
        <div ref={bottom} />
      </div>

      <ReplyBox leadId={lead.id} />
    </section>
  )
}
