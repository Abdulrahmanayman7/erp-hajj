<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ChevronsDown,
  ChevronsUp,
  FolderTree,
  LoaderCircle,
  Plus,
  Search,
  X,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useBodyScrollLock } from '@/shared/composables/useBodyScrollLock'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import OrganizationTreeNodes from '../components/OrganizationTreeNodes.vue'
import OrganizationUnitDetailsPanel from '../components/OrganizationUnitDetailsPanel.vue'
import OrganizationUnitFormDrawer, {
  type OrganizationUnitFormState,
} from '../components/OrganizationUnitFormDrawer.vue'
import {
  useActivateOrganizationUnitMutation,
  useCreateOrganizationUnitMutation,
  useDeactivateOrganizationUnitMutation,
  useDeleteOrganizationUnitMutation,
  useMoveOrganizationUnitMutation,
  useUpdateOrganizationUnitMutation,
} from '../mutations/useOrganizationUnitMutations'
import { useOrganizationUnitsTreeQuery } from '../queries/useOrganizationUnitsQuery'
import type { OrganizationUnit, OrganizationUnitType } from '../types/organization'
import { collectDescendantIds, findUnitInTree, flattenUnits } from '../utils/tree'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  status: 'all' as 'all' | 'active' | 'inactive',
})

const committedSearch = useDebouncedRef(() => filters.search)
const queryParams = computed(() => ({
  search: committedSearch.value || undefined,
  status: filters.status,
}))

const { data, isLoading, isError, refetch, isFetching } = useOrganizationUnitsTreeQuery(queryParams)
const createMutation = useCreateOrganizationUnitMutation()
const updateMutation = useUpdateOrganizationUnitMutation()
const moveMutation = useMoveOrganizationUnitMutation()
const activateMutation = useActivateOrganizationUnitMutation()
const deactivateMutation = useDeactivateOrganizationUnitMutation()
const deleteMutation = useDeleteOrganizationUnitMutation()

const tree = computed(() => data.value?.data ?? [])
const unitsCount = computed(() => flattenUnits(tree.value).length)
const selectedId = ref<number | null>(null)
const expanded = ref<Set<number>>(new Set())
const mobileDetailsOpen = ref(false)
useBodyScrollLock(mobileDetailsOpen)

const selected = computed(() =>
  selectedId.value != null ? findUnitInTree(tree.value, selectedId.value) : null,
)

const drawerOpen = ref(false)
const editing = ref<OrganizationUnit | null>(null)
const formError = ref('')
const form = ref<OrganizationUnitFormState>({
  name: '',
  code: '',
  type: 'department',
  parent_id: null,
  manager_user_id: null,
  sort_order: 0,
})

const moveOpen = ref(false)
const moveParentId = ref<number | null>(null)

const parentOptions = computed<AppSelectOption[]>(() => {
  const exclude = editing.value ? collectDescendantIds(editing.value) : new Set<number>()
  const flat = flattenUnits(tree.value).filter((u) => u.status === 'active' && !exclude.has(u.id))
  return [
    { value: '', label: t('organization.rootParent') },
    ...flat.map((u) => ({
      value: u.id,
      label: `${'— '.repeat(u.depth ?? 0)}${u.name}`,
      hint: u.code,
    })),
  ]
})

const moveParentOptions = computed<AppSelectOption[]>(() => {
  if (!selected.value) return [{ value: '', label: t('organization.rootParent') }]
  const exclude = collectDescendantIds(selected.value)
  const flat = flattenUnits(tree.value).filter((u) => u.status === 'active' && !exclude.has(u.id))
  return [
    { value: '', label: t('organization.rootParent') },
    ...flat.map((u) => ({
      value: u.id,
      label: `${'— '.repeat(u.depth ?? 0)}${u.name}`,
      hint: u.code,
    })),
  ]
})

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)
const isMoving = computed(() => moveMutation.isPending.value)

watch(
  tree,
  (nodes) => {
    if (nodes.length && expanded.value.size === 0) {
      expanded.value = new Set(nodes.map((n) => n.id))
    }
  },
  { immediate: true },
)

function typeLabel(type: OrganizationUnitType): string {
  return t(`organization.types.${type}`)
}

function statusLabel(status: string): string {
  return status === 'active' ? t('organization.status.active') : t('organization.status.inactive')
}

function toggleExpand(id: number): void {
  const next = new Set(expanded.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expanded.value = next
}

function selectUnit(unit: OrganizationUnit): void {
  selectedId.value = unit.id
  mobileDetailsOpen.value = true
}

function openCreate(parentId: number | null = null): void {
  editing.value = null
  form.value = {
    name: '',
    code: '',
    type: 'department',
    parent_id: parentId,
    manager_user_id: null,
    sort_order: 0,
  }
  formError.value = ''
  drawerOpen.value = true
}

function openEdit(unit: OrganizationUnit): void {
  editing.value = unit
  form.value = {
    name: unit.name,
    code: unit.code,
    type: unit.type,
    parent_id: unit.parent_id,
    manager_user_id: unit.manager?.id ?? null,
    sort_order: unit.sort_order,
  }
  formError.value = ''
  drawerOpen.value = true
}

async function submitForm(): Promise<void> {
  formError.value = ''
  if (!form.value.name.trim() || !form.value.code.trim() || !form.value.type) {
    formError.value = t('organization.validation.required')
    return
  }
  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload: {
          name: form.value.name.trim(),
          type: form.value.type as OrganizationUnitType,
          manager_user_id: form.value.manager_user_id,
          sort_order: form.value.sort_order,
        },
      })
      toast.success(t('organization.toasts.updated'))
    } else {
      const created = await createMutation.mutateAsync({
        name: form.value.name.trim(),
        code: form.value.code.trim().toUpperCase(),
        type: form.value.type as OrganizationUnitType,
        parent_id: form.value.parent_id,
        manager_user_id: form.value.manager_user_id,
        sort_order: form.value.sort_order,
      })
      selectedId.value = created.id
      toast.success(t('organization.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value =
      error instanceof ApiError ? error.message : t('organization.toasts.genericError')
  }
}

function openMove(): void {
  if (!selected.value) return
  moveParentId.value = selected.value.parent_id
  moveOpen.value = true
}

async function confirmMove(): Promise<void> {
  if (!selected.value) return
  const ok = await confirm({
    title: t('organization.moveConfirmTitle'),
    message: t('organization.moveConfirmBody'),
    confirmLabel: t('organization.moveCta'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await moveMutation.mutateAsync({
      id: selected.value.id,
      parentId: moveParentId.value,
    })
    moveOpen.value = false
    toast.success(t('organization.toasts.moved'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('organization.toasts.genericError'))
  }
}

async function toggleStatus(unit: OrganizationUnit): Promise<void> {
  if (unit.status === 'active') {
    const ok = await confirm({
      title: t('organization.deactivateTitle'),
      message: t('organization.deactivateBody'),
      confirmLabel: t('organization.deactivateCta'),
      variant: 'warning',
    })
    if (!ok) return
    try {
      await deactivateMutation.mutateAsync(unit.id)
      toast.success(t('organization.toasts.deactivated'))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t('organization.toasts.genericError'))
    }
    return
  }
  try {
    await activateMutation.mutateAsync(unit.id)
    toast.success(t('organization.toasts.activated'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('organization.toasts.genericError'))
  }
}

async function confirmDelete(unit: OrganizationUnit): Promise<void> {
  const ok = await confirm({
    title: t('organization.deleteTitle'),
    message: t('organization.deleteBody'),
    confirmLabel: t('organization.deleteCta'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(unit.id)
    if (selectedId.value === unit.id) selectedId.value = null
    toast.success(t('organization.toasts.deleted'))
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : t('organization.toasts.genericError'))
  }
}

const parentName = computed(() => {
  if (!selected.value?.parent_id) return t('organization.rootParent')
  return findUnitInTree(tree.value, selected.value.parent_id)?.name ?? '—'
})

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('organization.filters.all') },
  { value: 'active', label: t('organization.status.active') },
  { value: 'inactive', label: t('organization.status.inactive') },
])

function collectExpandableIds(nodes: OrganizationUnit[]): number[] {
  const ids: number[] = []
  const walk = (list: OrganizationUnit[]) => {
    for (const node of list) {
      if (node.children?.length) {
        ids.push(node.id)
        walk(node.children)
      }
    }
  }
  walk(nodes)
  return ids
}

function expandAll(): void {
  expanded.value = new Set(collectExpandableIds(tree.value))
}

function collapseAll(): void {
  expanded.value = new Set()
}
</script>

<template>
  <div class="min-w-0 space-y-6 overflow-x-hidden">
    <AppPageHeader
      :title="t('organization.title')"
      :subtitle="t('organization.subtitle')"
      :meta="!isLoading && !isError ? t('organization.total', { count: unitsCount }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="organization_units.create">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary sm:w-auto"
            @click="openCreate()"
          >
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            <span>{{ t('organization.createCta') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <div
      class="flex flex-col gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] sm:flex-row sm:flex-wrap sm:items-center"
    >
      <div class="relative min-w-0 flex-1 sm:min-w-48">
        <Search
          class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
          :stroke-width="1.75"
          aria-hidden="true"
        />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :placeholder="t('organization.searchPlaceholder')"
        />
      </div>
      <div class="w-full sm:w-48">
        <AppSelect v-model="filters.status" :options="statusOptions" />
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border border-brand-border bg-brand-bg px-3 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft hover:text-brand-primary-dark sm:flex-none"
          @click="expandAll"
        >
          <ChevronsDown class="h-4 w-4" :stroke-width="2" />
          <span class="sm:inline">{{ t('organization.expandAll') }}</span>
        </button>
        <button
          type="button"
          class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border border-brand-border bg-brand-bg px-3 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft hover:text-brand-primary-dark sm:flex-none"
          @click="collapseAll"
        >
          <ChevronsUp class="h-4 w-4" :stroke-width="2" />
          <span class="sm:inline">{{ t('organization.collapseAll') }}</span>
        </button>
      </div>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('organization.loading') }}
    </div>

    <div
      v-else-if="isError"
      class="rounded-2xl border border-red-200 bg-red-50 px-6 py-10 text-center"
    >
      <p class="font-medium text-red-700">{{ t('organization.error') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary hover:text-brand-primary-dark"
        @click="() => refetch()"
      >
        {{ t('organization.retry') }}
      </button>
    </div>

    <div
      v-else-if="tree.length === 0"
      class="flex flex-col items-center rounded-2xl border border-dashed border-brand-border bg-brand-surface px-6 py-16 text-center"
    >
      <span class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-primary-soft text-brand-primary-dark">
        <FolderTree class="h-7 w-7" :stroke-width="1.75" />
      </span>
      <p class="text-base font-semibold text-brand-text">{{ t('organization.emptyTitle') }}</p>
      <p class="mt-1 text-sm text-brand-text-secondary">{{ t('organization.emptyBody') }}</p>
      <PermissionGuard permission="organization_units.create">
        <button
          type="button"
          class="mt-5 inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary"
          @click="openCreate()"
        >
          <Plus class="h-4 w-4" :stroke-width="2.25" />
          {{ t('organization.emptyCta') }}
        </button>
      </PermissionGuard>
    </div>

    <div v-else class="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
      <section
        class="min-w-0 overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
      >
        <div class="flex items-center justify-between gap-3 border-b border-brand-border bg-[#F7F8F6] px-4 py-3">
          <div class="flex min-w-0 items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-primary-soft text-brand-primary-dark">
              <FolderTree class="h-4 w-4" :stroke-width="1.85" />
            </span>
            <h3 class="text-sm font-bold text-brand-text">{{ t('organization.treeTitle') }}</h3>
          </div>
          <LoaderCircle
            v-if="isFetching"
            class="h-4 w-4 shrink-0 animate-spin text-brand-primary"
            :stroke-width="2"
            aria-hidden="true"
          />
        </div>
        <ul class="max-h-[min(70vh,40rem)] min-w-0 overflow-x-hidden overflow-y-auto p-1.5 sm:p-2" role="tree">
          <OrganizationTreeNodes
            :nodes="tree"
            :expanded="expanded"
            :selected-id="selectedId"
            :type-label="typeLabel"
            :status-label="statusLabel"
            @toggle="toggleExpand"
            @select="selectUnit"
          />
        </ul>
      </section>

      <section
        class="hidden min-w-0 overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] lg:block"
      >
        <div
          v-if="!selected"
          class="flex min-h-[22rem] flex-col items-center justify-center gap-3 px-6 py-12 text-center"
        >
          <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-bg text-brand-text-muted">
            <FolderTree class="h-7 w-7" :stroke-width="1.75" />
          </span>
          <p class="text-sm font-semibold text-brand-text">{{ t('organization.selectHintTitle') }}</p>
          <p class="max-w-xs text-sm text-brand-text-secondary">{{ t('organization.selectHint') }}</p>
        </div>

        <OrganizationUnitDetailsPanel
          v-else
          :unit="selected"
          :parent-name="parentName"
          @edit="openEdit(selected)"
          @move="openMove"
          @toggle-status="toggleStatus(selected)"
          @add-child="openCreate(selected.id)"
          @delete="confirmDelete(selected)"
        />
      </section>
    </div>

    <Teleport to="body">
      <div
        v-if="mobileDetailsOpen && selected"
        class="fixed inset-0 z-50 lg:hidden"
        role="dialog"
        aria-modal="true"
        :aria-label="selected.name"
      >
        <button
          type="button"
          class="absolute inset-0 bg-brand-text/40"
          :aria-label="t('organization.close')"
          @click="mobileDetailsOpen = false"
        />
        <div
          class="absolute inset-x-0 bottom-0 flex max-h-[88dvh] flex-col overflow-hidden rounded-t-[1.5rem] border border-brand-border bg-brand-surface shadow-[0_-18px_40px_-24px_rgba(23,32,29,0.45)]"
          style="padding-bottom: max(8px, env(safe-area-inset-bottom))"
        >
          <OrganizationUnitDetailsPanel
            :unit="selected"
            :parent-name="parentName"
            show-close
            @close="mobileDetailsOpen = false"
            @edit="openEdit(selected)"
            @move="openMove"
            @toggle-status="toggleStatus(selected)"
            @add-child="openCreate(selected.id)"
            @delete="confirmDelete(selected)"
          />
        </div>
      </div>
    </Teleport>

    <OrganizationUnitFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :submitting="isSubmitting"
      :parent-options="parentOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="form = $event"
    />

    <Teleport to="body">
      <div
        v-if="moveOpen && selected"
        class="fixed inset-0 z-50 flex items-center justify-center bg-[rgba(15,23,20,0.32)] p-4"
      >
        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_14px_32px_-18px_rgba(23,32,29,0.38)]">
          <div class="flex items-start justify-between gap-3 border-b border-brand-border px-5 py-4">
            <div>
              <h3 class="text-lg font-bold text-brand-text">{{ t('organization.moveTitle') }}</h3>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ t('organization.moveBody') }}</p>
            </div>
            <button
              type="button"
              class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-brand-text-muted transition hover:bg-brand-bg hover:text-brand-text"
              :aria-label="t('organization.close')"
              @click="moveOpen = false"
            >
              <X class="h-4 w-4" :stroke-width="2" />
            </button>
          </div>
          <div class="px-5 py-4">
            <span class="mb-1.5 block text-sm font-medium text-brand-text">{{
              t('organization.fields.parent')
            }}</span>
            <AppSelect
              :model-value="moveParentId"
              :options="moveParentOptions"
              searchable
              @update:model-value="
                moveParentId = $event === null || $event === '' ? null : Number($event)
              "
            />
          </div>
          <div class="flex flex-col gap-2 border-t border-brand-border px-5 py-4 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl px-4 text-sm font-medium text-brand-text-secondary transition hover:bg-brand-bg sm:h-10"
              @click="moveOpen = false"
            >
              {{ t('organization.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary disabled:opacity-60 sm:h-10"
              :disabled="isMoving"
              @click="confirmMove"
            >
              {{ t('organization.moveCta') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

