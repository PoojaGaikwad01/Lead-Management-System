export type Role = 'super_admin' | 'admin' | 'assignee'

export const ROLE_LABELS: Record<Role, string> = {
  super_admin: 'Super admin',
  admin: 'Admin',
  assignee: 'Assignee',
}

export interface Branch {
  id: number
  name: string
  address: string | null
  phone: string | null
  active: boolean
  users_count?: number
}

export type LeadStatus = 'new' | 'assigned' | 'contacted' | 'follow_up' | 'converted' | 'lost' | 'closed'
export type LeadSource = 'whatsapp_enquiry' | 'agency_text' | 'agency_file' | 'manual_upload'
export type ReplyStatus = 'not_sent' | 'sent'

export interface LeadTypeField {
  id: number
  key: string
  label: string
  field_type: string
  options: string[] | null
  required: boolean
}

export interface LeadType {
  id: number
  name: string
  slug: string
  color: string | null
  active: boolean
  sort: number
  keywords: string[] | null
  leads_count?: number
  fields: LeadTypeField[]
}

export interface Breakdown {
  label: string
  total: number
  converted: number
}

export interface ReportData {
  from: string
  to: string
  totals: {
    leads: number
    converted: number
    lost: number
    conversion_rate: number | null
    avg_minutes_to_assign: number | null
    avg_minutes_to_reply: number | null
    reminders: number
  }
  by_type: Breakdown[]
  by_source: Breakdown[]
  by_branch: Breakdown[]
  by_assignee: {
    label: string
    total: number
    replied: number
    awaiting_reply: number
    converted: number
    avg_minutes_to_reply: number | null
    reminders: number
  }[]
  lost_reasons: { label: string; total: number }[]
}

export interface ReminderRules {
  auto_enabled: boolean
  after_hours: number
  whatsapp_alert: boolean
}

export interface SettingsData {
  agency_numbers: string[]
  reminder_rules: ReminderRules
  ai_extraction_enabled: boolean
  import_column_mapping: Record<string, string>
  mapping_targets: { key: string; label: string; required: boolean }[]
  status: {
    whatsapp_provider: string
    provider_configured: boolean
    webhook_url: string
    ai_key_configured: boolean
    ai_model: string
  }
}

export interface IntegrationsData {
  clients: { id: number; name: string; active: boolean; created_at: string; last_used_at: string | null }[]
  webhooks: {
    id: number
    url: string
    events: string[]
    active: boolean
    last_delivery_at: string | null
    last_status: number | null
    last_error: string | null
  }[]
  api_base_url: string
}

export interface Lead {
  id: number
  source: LeadSource
  status: LeadStatus
  reply_status: ReplyStatus | null
  unread_count: number
  reminder_count: number
  custom_data: Record<string, unknown>
  lost_reason: string | null
  source_text: string | null
  import_id: number | null
  created_at: string
  last_message_at: string | null
  assigned_at: string | null
  reply_sent_at: string | null
  last_reminder_at: string | null
  reply_due_at: string | null
  contact: { id: number; name: string | null; phone: string }
  lead_type: { id: number; name: string; color: string | null } | null
  branch: { id: number; name: string } | null
  assignee: { id: number; name: string } | null
  last_message?: {
    type: Message['type']
    body: string
    direction: 'in' | 'out'
    status: Message['status']
    media_name: string | null
    sent_at: string | null
  } | null
}

export interface LeadSummary {
  tabs: { new: number; reply_not_sent: number; mine: number; all: number }
  cards: { new_today: number; unassigned: number; reply_not_sent: number; converted_this_week: number }
  unread: number
}

export interface AppNotification {
  id: string
  kind: 'assigned' | 'reminder'
  lead_id: number
  title: string
  body: string
  read_at: string | null
  created_at: string
}

export interface LeadTimeline {
  notes: { id: number; kind: 'note' | 'call'; body: string | null; call_outcome: string | null; user: string | null; created_at: string }[]
  reminders: { id: number; auto: boolean; channel: string; sender: string | null; recipient: string | null; created_at: string }[]
  activity: { id: number; text: string; created_at: string }[]
}

export type ImportStatus = 'pending' | 'needs_mapping' | 'completed' | 'failed'

export interface ImportSummary {
  id: number
  source: LeadSource
  status: ImportStatus
  original_name: string | null
  sender_phone: string | null
  uploaded_by: { id: number; name: string } | null
  total_rows: number
  created_count: number
  duplicate_count: number
  error_count: number
  created_at: string
}

export interface ImportDetail extends ImportSummary {
  errors: { row: number | null; message: string }[]
  mapping: Record<string, string>
  message_text: string | null
  headers?: string[]
  preview?: (string | null)[][]
  targets?: { key: string; label: string; required: boolean }[]
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}

export interface Message {
  id: number
  lead_id: number | null
  contact_id: number
  direction: 'in' | 'out'
  type: 'text' | 'image' | 'audio' | 'video' | 'document'
  body: string | null
  status: 'received' | 'pending' | 'sent' | 'delivered' | 'read' | 'failed'
  is_agency: boolean
  sent_at: string
  media: { name: string | null; mime: string | null; url: string | null } | null
  contact?: { id: number; phone: string; name: string | null }
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  role: Role
  branch_id: number | null
  branch: Branch | null
  active: boolean
}
