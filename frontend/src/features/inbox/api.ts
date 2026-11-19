import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { Lead, LeadSummary, LeadTimeline, LeadType, Message, Paginated, User } from '../../lib/types'

export type Tab = 'new' | 'reply_not_sent' | 'mine' | 'all'

export interface LeadFilters {
  tab: Tab
  q: string
  lead_type_id: string
  source: string
  branch_id: string
  assigned_to: string
  status: string
  reply_status: string
  from: string
  to: string
}

export const EMPTY_FILTERS: LeadFilters = {
  tab: 'all',
  q: '',
  lead_type_id: '',
  source: '',
  branch_id: '',
  assigned_to: '',
  status: '',
  reply_status: '',
  from: '',
  to: '',
}

const POLL_MS = 10_000

export function useLeads(filters: LeadFilters, poll: boolean) {
  const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''))
  return useInfiniteQuery({
    queryKey: ['leads', params],
    queryFn: async ({ pageParam }) => (await api.get<Paginated<Lead>>('/leads', { params: { ...params, page: pageParam } })).data,
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    refetchInterval: poll ? POLL_MS : false,
  })
}

export function useLeadSummary(poll: boolean) {
  return useQuery({
    queryKey: ['lead-summary'],
    queryFn: async () => (await api.get<{ data: LeadSummary }>('/leads/summary')).data.data,
    refetchInterval: poll ? POLL_MS : false,
  })
}

export function useLead(id: number | null, poll: boolean) {
  return useQuery({
    queryKey: ['lead', id],
    queryFn: async () => (await api.get<{ data: Lead }>(`/leads/${id}`)).data.data,
    enabled: id !== null,
    retry: false,
    refetchInterval: poll ? POLL_MS : false,
  })
}

export function useMessages(leadId: number, poll: boolean) {
  return useQuery({
    queryKey: ['messages', leadId],
    queryFn: async () => (await api.get<{ data: Message[] }>(`/leads/${leadId}/messages`)).data.data,
    refetchInterval: poll ? POLL_MS : false,
  })
}

export function useLeadTypes() {
  return useQuery({
    queryKey: ['lead-types'],
    queryFn: async () => (await api.get<{ data: LeadType[] }>('/lead-types')).data.data,
    staleTime: 5 * 60_000,
  })
}

export function useAssignees(enabled: boolean) {
  return useQuery({
    queryKey: ['users'],
    queryFn: async () => (await api.get<{ data: User[] }>('/users')).data.data,
    enabled,
    staleTime: 5 * 60_000,
  })
}

export function useTimeline(leadId: number) {
  return useQuery({
    queryKey: ['lead-activity', leadId],
    queryFn: async () => (await api.get<{ data: LeadTimeline }>(`/leads/${leadId}/activity`)).data.data,
  })
}

/** POSTs a workflow action (assign, reply-sent, status, remind) and refreshes everything that shows the lead. */
export function useLeadAction(leadId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ action, body }: { action: string; body?: object }) =>
      api.post<{ data: Lead }>(`/leads/${leadId}/${action}`, body ?? {}),
    onSuccess: ({ data }) => {
      queryClient.setQueryData(['lead', leadId], data.data)
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      queryClient.invalidateQueries({ queryKey: ['lead-summary'] })
      queryClient.invalidateQueries({ queryKey: ['lead-activity', leadId] })
    },
  })
}

export function useMarkRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (leadId: number) => api.post(`/leads/${leadId}/read`),
    onSuccess: (_, leadId) => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      queryClient.invalidateQueries({ queryKey: ['lead-summary'] })
      queryClient.invalidateQueries({ queryKey: ['lead', leadId] })
    },
  })
}
