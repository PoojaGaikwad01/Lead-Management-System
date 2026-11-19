import type { BadgeTone } from '../components/ui'
import type { ImportStatus, LeadSource, LeadStatus, ReplyStatus } from './types'

export const STATUS: Record<LeadStatus, { label: string; tone: BadgeTone }> = {
  new: { label: 'New', tone: 'blue' },
  assigned: { label: 'Assigned', tone: 'purple' },
  contacted: { label: 'Contacted', tone: 'orange' },
  follow_up: { label: 'Follow-up', tone: 'amber' },
  converted: { label: 'Converted', tone: 'green' },
  lost: { label: 'Lost', tone: 'red' },
  closed: { label: 'Closed', tone: 'gray' },
}

export const SOURCE: Record<LeadSource, string> = {
  whatsapp_enquiry: 'WhatsApp enquiry',
  agency_text: 'Agency text',
  agency_file: 'Agency file',
  manual_upload: 'Manual upload',
}

export const REPLY: Record<ReplyStatus, { label: string; tone: BadgeTone }> = {
  not_sent: { label: 'Reply not sent', tone: 'red' },
  sent: { label: 'Reply sent', tone: 'green' },
}

export const CALL_OUTCOMES: Record<string, string> = {
  connected: 'Connected',
  no_answer: 'No answer',
  busy: 'Busy',
  wrong_number: 'Wrong number',
  call_back: 'Call back later',
}

export const LOST_REASONS = [
  'Price too high',
  'Chose another venue',
  'Date not available',
  'No response from customer',
  'Not interested',
]

export const IMPORT_STATUS: Record<ImportStatus, { label: string; tone: BadgeTone }> = {
  pending: { label: 'Ready to import', tone: 'blue' },
  needs_mapping: { label: 'Needs column mapping', tone: 'amber' },
  completed: { label: 'Completed', tone: 'green' },
  failed: { label: 'Failed', tone: 'red' },
}

const TYPE_TONES = new Set<string>(['pink', 'green', 'purple', 'blue', 'slate', 'amber', 'gray', 'orange', 'red'])

export function typeTone(color: string | null): BadgeTone {
  return color && TYPE_TONES.has(color) ? (color as BadgeTone) : 'gray'
}
