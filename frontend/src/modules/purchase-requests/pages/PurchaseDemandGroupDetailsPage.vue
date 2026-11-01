<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'

import {
  getDemandGroup,
  linkDemandGroupItem,
  transitionDemandGroup,
  unlinkDemandGroupItem,
} from '../api/demandGroupsApi'
import type { DemandGroup, DemandGroupStatus } from '../types/demandGroups'

const { t } = useI18n()
const route = useRoute()
const { can } = usePermissions()

const id = computed(() => Number(route.params.id))
const group = ref<DemandGroup | null>(null)
const isLoading = ref(true)
const isError = ref(false)
const prId = ref('')
const actionError = ref('')
const busy = ref(false)

const canMutate = computed(() => can('purchase_requests.consolidate_planning'))
const membershipLocked = computed(
  () => group.value?.status === 'frozen' || group.value?.status === 'closed',
)

async function load(): Promise<void> {
  isLoading.value = true
  isError.value = false
  try {
    group.value = await getDemandGroup(id.value)
  } catch {
    isError.value = true
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void load()
})

async function run(action: () => Promise<DemandGroup>): Promise<void> {
  busy.value = true
  actionError.value = ''
  try {
    group.value = await action()
  } catch (e) {
    actionError.value = e instanceof Error ? e.message : t('common.error')
  } finally {
    busy.value = false
  }
}

function transitionTo(to: DemandGroupStatus): void {
  void run(() => transitionDemandGroup(id.value, to))
}

function link(): void {
  const purchaseRequestId = Number(prId.value)
  if (!purchaseRequestId) return
  void run(async () => {
    const result = await linkDemandGroupItem(id.value, purchaseRequestId)
    prId.value = ''
    return result
  })
}

function unlink(purchaseRequestId: number): void {
  void run(() => unlinkDemandGroupItem(id.value, purchaseRequestId))
}
</script>

<template>
  <div class="space-y-6">
    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('common.loading') }}</div>
    <div v-else-if="isError || !group" class="text-sm text-destructive">{{ t('common.error') }}</div>
    <template v-else>
      <header class="space-y-2">
        <h1 class="text-2xl font-semibold">{{ group.group_number }} — {{ group.title }}</h1>
        <p class="text-sm text-muted-foreground">{{ t('purchaseDemandGroups.status') }}: {{ group.status }}</p>
        <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
          {{ t('purchaseDemandGroups.noRfqsBanner') }}
        </p>
        <ul v-if="group.scope_warnings?.length" class="text-sm text-amber-900 list-disc pr-5">
          <li v-for="(w, i) in group.scope_warnings" :key="i">{{ w }}</li>
        </ul>
      </header>

      <p v-if="actionError" class="text-sm text-destructive">{{ actionError }}</p>

      <PermissionGuard permission="purchase_requests.consolidate_planning">
        <div class="flex flex-wrap gap-2">
          <button
            v-if="group.status === 'draft'"
            type="button"
            class="rounded-md border px-3 py-2 text-sm"
            :disabled="busy"
            @click="transitionTo('active')"
          >
            {{ t('purchaseDemandGroups.toActive') }}
          </button>
          <button
            v-if="group.status === 'active' || group.status === 'draft'"
            type="button"
            class="rounded-md border px-3 py-2 text-sm"
            :disabled="busy"
            @click="transitionTo('frozen')"
          >
            {{ t('purchaseDemandGroups.toFrozen') }}
          </button>
          <button
            v-if="group.status === 'frozen'"
            type="button"
            class="rounded-md border px-3 py-2 text-sm"
            :disabled="busy"
            @click="transitionTo('active')"
          >
            {{ t('purchaseDemandGroups.toActive') }}
          </button>
          <button
            v-if="group.status !== 'closed'"
            type="button"
            class="rounded-md border px-3 py-2 text-sm"
            :disabled="busy"
            @click="transitionTo('closed')"
          >
            {{ t('purchaseDemandGroups.toClosed') }}
          </button>
        </div>
      </PermissionGuard>

      <section class="space-y-3">
        <h2 class="text-lg font-medium">{{ t('purchaseDemandGroups.members') }}</h2>
        <p v-if="group.planning_totals" class="text-sm text-muted-foreground">
          {{ t('purchaseDemandGroups.pricedTotal') }}:
          {{ group.planning_totals.priced_total ?? '—' }}
          · {{ t('purchaseDemandGroups.unpricedMembers') }}:
          {{ group.planning_totals.unpriced_member_count }}
        </p>
        <PermissionGuard permission="purchase_requests.consolidate_planning">
          <div v-if="canMutate && !membershipLocked" class="flex flex-wrap gap-2">
            <input
              v-model="prId"
              type="number"
              class="rounded-md border px-3 py-2 text-sm"
              :placeholder="t('purchaseDemandGroups.prId')"
            />
            <button
              type="button"
              class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
              :disabled="busy || !prId"
              @click="link"
            >
              {{ t('purchaseDemandGroups.link') }}
            </button>
          </div>
          <p v-else-if="membershipLocked" class="text-sm text-muted-foreground">
            {{ t('purchaseDemandGroups.membershipLocked') }}
          </p>
        </PermissionGuard>
        <table class="w-full text-sm">
          <tbody>
            <tr v-for="m in group.members ?? []" :key="m.purchase_request_id" class="border-b">
              <td class="py-2">{{ m.pr_number ?? m.purchase_request_id }} — {{ m.title }}</td>
              <td class="py-2">{{ m.status }}</td>
              <td class="py-2 text-left">
                <PermissionGuard permission="purchase_requests.consolidate_planning">
                  <button
                    v-if="!membershipLocked"
                    type="button"
                    class="text-destructive text-sm underline disabled:opacity-50"
                    :disabled="busy"
                    @click="unlink(m.purchase_request_id)"
                  >
                    {{ t('purchaseDemandGroups.unlink') }}
                  </button>
                </PermissionGuard>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>
