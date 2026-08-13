import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { currentUserQueryKey } from '@/modules/auth/queries/useCurrentUserQuery'
import { dashboardQueryKey } from '@/modules/dashboard/queries/useDashboardQuery'

import { updateTenantSettings } from '../api/settingsApi'
import type { UpdateTenantSettingsPayload } from '../types/settings'
import { tenantSettingsQueryKey } from '../queries/useTenantSettingsQuery'

export function useUpdateTenantSettingsMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: UpdateTenantSettingsPayload) => updateTenantSettings(payload),
    onSuccess: async (data) => {
      queryClient.setQueryData(tenantSettingsQueryKey, data)
      await queryClient.invalidateQueries({ queryKey: currentUserQueryKey })
      await queryClient.invalidateQueries({ queryKey: dashboardQueryKey })
    },
  })
}
