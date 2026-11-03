<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import { useToast } from '@/shared/composables/useToast'
import { usePurchaseRequestQuery } from '@/modules/purchase-requests/queries/usePurchaseRequestsQuery'

import { useCreateRfqMutation } from '../mutations/useRfqMutations'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const purchaseRequestId = computed(() => {
  const raw = route.query.purchase_request_id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) && value > 0 ? value : null
})

const { data: prData, isLoading, isError } = usePurchaseRequestQuery(purchaseRequestId)
const pr = computed(() => prData.value ?? null)
const notes = ref('')
const title = ref('')
const formError = ref('')

const createMutation = useCreateRfqMutation()

const canCreate = computed(
  () => pr.value?.status === 'approved' && !createMutation.isPending.value,
)

async function submit(): Promise<void> {
  formError.value = ''
  if (purchaseRequestId.value == null) {
    formError.value = t('rfq.create.missingPr')
    return
  }
  if (pr.value?.status !== 'approved') {
    formError.value = t('rfq.create.prNotApproved')
    return
  }

  try {
    const rfq = await createMutation.mutateAsync({
      purchase_request_id: purchaseRequestId.value,
      title: title.value.trim() || undefined,
      notes: notes.value.trim() || undefined,
    })
    toast.success(t('rfq.create.success'))
    await router.push(`/app/rfqs/${rfq.id}`)
  } catch (error) {
    const code = error instanceof ApiError ? error.code : null
    formError.value =
      code === 'RFQ_PR_NOT_APPROVED'
        ? t('rfq.errors.prNotApproved')
        : t('rfq.create.failed')
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <RouterLink
      to="/app/rfqs"
      class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
    >
      <ArrowRight class="h-4 w-4" />
      {{ t('rfq.backToList') }}
    </RouterLink>

    <header>
      <h1 class="text-2xl font-semibold">{{ t('rfq.create.title') }}</h1>
      <p class="mt-1 text-sm text-muted-foreground">{{ t('rfq.create.subtitle') }}</p>
    </header>

    <div v-if="purchaseRequestId == null" class="text-sm text-destructive">
      {{ t('rfq.create.missingPr') }}
    </div>
    <div v-else-if="isLoading" class="text-sm text-muted-foreground">{{ t('rfq.loading') }}</div>
    <div v-else-if="isError || !pr" class="text-sm text-destructive">{{ t('rfq.create.prLoadError') }}</div>
    <form v-else class="space-y-4 rounded-md border border-border p-4" @submit.prevent="submit">
      <div class="rounded-md bg-muted/40 p-3 text-sm">
        <div>{{ t('rfq.create.prLabel') }}: <strong>{{ pr.pr_number }}</strong> — {{ pr.title }}</div>
        <div class="mt-1 text-muted-foreground">
          {{ t('rfq.create.prStatus') }}: {{ pr.status }}
        </div>
      </div>

      <div v-if="pr.status !== 'approved'" class="text-sm text-destructive">
        {{ t('rfq.create.prNotApproved') }}
      </div>

      <label class="block space-y-1 text-sm">
        <span>{{ t('rfq.create.titleField') }}</span>
        <input
          v-model="title"
          type="text"
          class="w-full rounded-md border border-border bg-background px-3 py-2"
          :placeholder="pr.title"
        />
      </label>

      <label class="block space-y-1 text-sm">
        <span>{{ t('rfq.create.notesField') }}</span>
        <textarea
          v-model="notes"
          rows="3"
          class="w-full rounded-md border border-border bg-background px-3 py-2"
        />
      </label>

      <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>

      <button
        type="submit"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
        :disabled="!canCreate"
      >
        {{ t('rfq.create.submit') }}
      </button>
    </form>
  </div>
</template>
