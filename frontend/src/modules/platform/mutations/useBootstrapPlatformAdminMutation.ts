import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'

import { bootstrapPlatformAdministrator } from '../api/platformSetupApi'
import type { BootstrapPlatformAdminPayload } from '../types/platform'
import { platformSetupStatusQueryKey } from '../queries/usePlatformSetupStatusQuery'

export function useBootstrapPlatformAdminMutation() {
  const queryClient = useQueryClient()
  const router = useRouter()

  return useMutation({
    mutationFn: (payload: BootstrapPlatformAdminPayload) =>
      bootstrapPlatformAdministrator(payload),
    onSuccess: async () => {
      queryClient.setQueryData(platformSetupStatusQueryKey, { available: false })
      await router.replace({ name: 'login', query: { setup: '1' } })
    },
  })
}
