<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useCreatePurchaseApprovalMatrixMutation,
  useGrantPurchaseApproverAuthorityMutation,
  usePublishPurchaseApprovalMatrixMutation,
  useUpsertPurchaseApprovalSlaPolicyMutation,
} from '../mutations/usePurchaseRequestMutations'
import {
  usePurchaseApprovalDelegationsQuery,
  usePurchaseApprovalMatricesQuery,
  usePurchaseApprovalSlaPoliciesQuery,
  usePurchaseApproverAuthoritiesQuery,
} from '../queries/usePurchaseRequestsQuery'
import { mapPurchaseRequestErrorCode } from '../validation/purchaseRequestValidation'

const { t } = useI18n()
const toast = useToast()
const { can } = usePermissions()

const canConfig = computed(() => can('purchase_requests.manage_approval_config'))
const canDelegations = computed(() => can('purchase_requests.manage_delegations'))

const { data: matricesData, isLoading: matricesLoading } = usePurchaseApprovalMatricesQuery(canConfig)
const { data: authoritiesData, isLoading: authoritiesLoading } =
  usePurchaseApproverAuthoritiesQuery(canConfig)
const { data: slaData, isLoading: slaLoading } = usePurchaseApprovalSlaPoliciesQuery(canConfig)
const { data: delegationsData, isLoading: delegationsLoading } =
  usePurchaseApprovalDelegationsQuery(canDelegations)

const matrices = computed(() => matricesData.value?.data ?? [])
const authorities = computed(() => authoritiesData.value?.data ?? [])
const slaPolicies = computed(() => slaData.value ?? [])
const delegations = computed(() => delegationsData.value?.data ?? [])

const formError = ref('')
const matrixName = ref('')
const authorityForm = reactive({
  user_id: '',
  max_amount: '',
  covers_unpriced: false,
  effective_from: new Date().toISOString().slice(0, 10),
})
const slaForm = reactive({
  priority: 'normal',
  level_order: 1,
  target_hours: 24,
})

const createMatrix = useCreatePurchaseApprovalMatrixMutation()
const publishMatrix = usePublishPurchaseApprovalMatrixMutation()
const grantAuthority = useGrantPurchaseApproverAuthorityMutation()
const upsertSla = useUpsertPurchaseApprovalSlaPolicyMutation()

async function onCreateMatrix(): Promise<void> {
  formError.value = ''
  try {
    await createMatrix.mutateAsync(matrixName.value.trim())
    matrixName.value = ''
    toast.success(t('purchaseRequests.settings.matrixCreated'))
  } catch (error) {
    formError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function onPublish(id: number): Promise<void> {
  formError.value = ''
  try {
    await publishMatrix.mutateAsync(id)
    toast.success(t('purchaseRequests.settings.matrixPublished'))
  } catch (error) {
    formError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function onGrantAuthority(): Promise<void> {
  formError.value = ''
  try {
    await grantAuthority.mutateAsync({
      user_id: Number(authorityForm.user_id),
      max_amount: Number(authorityForm.max_amount),
      covers_unpriced: authorityForm.covers_unpriced,
      effective_from: authorityForm.effective_from,
    })
    authorityForm.user_id = ''
    authorityForm.max_amount = ''
    toast.success(t('purchaseRequests.settings.authorityGranted'))
  } catch (error) {
    formError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function onUpsertSla(): Promise<void> {
  formError.value = ''
  try {
    await upsertSla.mutateAsync({
      priority: slaForm.priority,
      level_order: Number(slaForm.level_order),
      target_hours: Number(slaForm.target_hours),
    })
    toast.success(t('purchaseRequests.settings.slaSaved'))
  } catch (error) {
    formError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-8">
    <RouterLink
      to="/app/purchase-requests"
      class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
    >
      <ArrowRight class="h-4 w-4" />
      {{ t('purchaseRequests.backToList') }}
    </RouterLink>

    <header>
      <h1 class="text-2xl font-semibold">{{ t('purchaseRequests.settings.title') }}</h1>
      <p class="mt-1 text-sm text-muted-foreground">{{ t('purchaseRequests.settings.subtitle') }}</p>
    </header>

    <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>

    <section v-if="canConfig" class="space-y-4 rounded-md border border-border p-4">
      <h2 class="text-lg font-medium">{{ t('purchaseRequests.settings.matrices') }}</h2>
      <div class="flex flex-wrap gap-2">
        <input
          v-model="matrixName"
          type="text"
          class="rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseRequests.settings.matrixName')"
        />
        <button
          type="button"
          class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
          @click="onCreateMatrix"
        >
          {{ t('purchaseRequests.settings.createMatrix') }}
        </button>
      </div>
      <div v-if="matricesLoading" class="text-sm text-muted-foreground">{{ t('purchaseRequests.loading') }}</div>
      <ul v-else class="space-y-2 text-sm">
        <li
          v-for="matrix in matrices"
          :key="matrix.id"
          class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2"
        >
          <span>
            {{ matrix.name }}
            · v{{ matrix.version_no }}
            · {{ matrix.status }}
          </span>
          <button
            v-if="matrix.status === 'draft'"
            type="button"
            class="rounded-md border border-border px-2 py-1 text-xs"
            @click="onPublish(matrix.id)"
          >
            {{ t('purchaseRequests.settings.publish') }}
          </button>
        </li>
        <li v-if="matrices.length === 0" class="text-muted-foreground">
          {{ t('purchaseRequests.settings.noMatrices') }}
        </li>
      </ul>
    </section>

    <section v-if="canConfig" class="space-y-4 rounded-md border border-border p-4">
      <h2 class="text-lg font-medium">{{ t('purchaseRequests.settings.authorities') }}</h2>
      <div class="grid gap-2 sm:grid-cols-2">
        <input
          v-model="authorityForm.user_id"
          type="number"
          class="rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseRequests.settings.userId')"
        />
        <input
          v-model="authorityForm.max_amount"
          type="number"
          class="rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseRequests.settings.maxAmount')"
        />
        <input
          v-model="authorityForm.effective_from"
          type="date"
          class="rounded-md border border-border bg-background px-3 py-2 text-sm"
        />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="authorityForm.covers_unpriced" type="checkbox" />
          {{ t('purchaseRequests.settings.coversUnpriced') }}
        </label>
      </div>
      <button
        type="button"
        class="rounded-md border border-border px-3 py-2 text-sm"
        @click="onGrantAuthority"
      >
        {{ t('purchaseRequests.settings.grantAuthority') }}
      </button>
      <div v-if="authoritiesLoading" class="text-sm text-muted-foreground">{{ t('purchaseRequests.loading') }}</div>
      <ul v-else class="space-y-2 text-sm">
        <li
          v-for="row in authorities"
          :key="row.id"
          class="rounded-md border border-border px-3 py-2"
        >
          #{{ row.user_id }} · {{ row.max_amount }} {{ row.currency }}
          <span v-if="row.covers_unpriced"> · {{ t('purchaseRequests.settings.coversUnpriced') }}</span>
        </li>
        <li v-if="authorities.length === 0" class="text-muted-foreground">
          {{ t('purchaseRequests.settings.noAuthorities') }}
        </li>
      </ul>
    </section>

    <section v-if="canDelegations" class="space-y-4 rounded-md border border-border p-4">
      <h2 class="text-lg font-medium">{{ t('purchaseRequests.settings.delegations') }}</h2>
      <div v-if="delegationsLoading" class="text-sm text-muted-foreground">{{ t('purchaseRequests.loading') }}</div>
      <ul v-else class="space-y-2 text-sm">
        <li
          v-for="row in delegations"
          :key="row.id"
          class="rounded-md border border-border px-3 py-2"
        >
          {{ row.from_user?.name || row.from_user_id }}
          → {{ row.to_user?.name || row.to_user_id }}
          · {{ row.max_amount }}
          · {{ row.ends_at || '—' }}
        </li>
        <li v-if="delegations.length === 0" class="text-muted-foreground">
          {{ t('purchaseRequests.settings.noDelegations') }}
        </li>
      </ul>
    </section>

    <section v-if="canConfig" class="space-y-4 rounded-md border border-border p-4">
      <h2 class="text-lg font-medium">{{ t('purchaseRequests.settings.sla') }}</h2>
      <p class="text-xs text-muted-foreground">{{ t('purchaseRequests.settings.slaEmptyHint') }}</p>
      <div class="grid gap-2 sm:grid-cols-3">
        <select v-model="slaForm.priority" class="rounded-md border border-border bg-background px-3 py-2 text-sm">
          <option value="normal">{{ t('purchaseRequests.priorities.normal') }}</option>
          <option value="urgent">{{ t('purchaseRequests.priorities.urgent') }}</option>
          <option value="emergency">{{ t('purchaseRequests.priorities.emergency') }}</option>
        </select>
        <input
          v-model.number="slaForm.level_order"
          type="number"
          min="1"
          class="rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseRequests.settings.levelOrder')"
        />
        <input
          v-model.number="slaForm.target_hours"
          type="number"
          min="1"
          class="rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseRequests.settings.targetHours')"
        />
      </div>
      <button type="button" class="rounded-md border border-border px-3 py-2 text-sm" @click="onUpsertSla">
        {{ t('purchaseRequests.settings.saveSla') }}
      </button>
      <div v-if="slaLoading" class="text-sm text-muted-foreground">{{ t('purchaseRequests.loading') }}</div>
      <ul v-else class="space-y-2 text-sm">
        <li
          v-for="row in slaPolicies"
          :key="row.id"
          class="rounded-md border border-border px-3 py-2"
        >
          {{ row.priority }} · L{{ row.level_order }} · {{ row.target_hours }}h
        </li>
        <li v-if="slaPolicies.length === 0" class="text-muted-foreground">
          {{ t('purchaseRequests.settings.noSla') }}
        </li>
      </ul>
    </section>
  </div>
</template>
