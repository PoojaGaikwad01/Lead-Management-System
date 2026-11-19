import { useQuery } from '@tanstack/react-query'
import { api } from '../../lib/api'
import type { Branch } from '../../lib/types'

export function useBranches() {
  return useQuery({
    queryKey: ['branches'],
    queryFn: async () => (await api.get<{ data: Branch[] }>('/branches')).data.data,
  })
}
