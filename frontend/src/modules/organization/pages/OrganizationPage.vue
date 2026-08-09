<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ChevronLeft,
  FolderTree,
  Pencil,
  Plus,
  Power,
  PowerOff,
  Search,
  Trash2,
  ArrowRightLeft,
} from 'lucide-vue-next'

import { listUsers } from '@/modules/users/api/usersApi'
import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'
import { useQuery } from '@tanstack/vue-query'

import OrganizationTreeNodes from '../components/OrganizationTreeNodes.vue'
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

const queryParams = computed(() => ({
  search: filters.search || undefined,
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
const selectedId = ref<number | null>(null)
const expanded = ref<Set<number>>(new Set())
const mobileDetailsOpen = ref(false)

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

const usersQuery = useQuery({
  queryKey: ['users', 'org-manager-options'],
  queryFn: () => listUsers({ status: 'active', per_page: 100 }),
  enabled: computed(() => drawerOpen.value || moveOpen.value),
})

const managerOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('organization.noManager') },
  ...(usersQuery.data.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.email,
  })),
])

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
</script>

<template>
  <div class="mx-auto flex w-full max-w-[1400px] flex-col gap-5 px-4 py-6 sm:px-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-brand-ink">{{ t('organization.title') }}</h1>
        <p class="mt-1 text-sm text-brand-muted">{{ t('organization.subtitle') }}</p>
      </div>
      <PermissionGuard permission="organization_units.create">
        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-95"
          @click="openCreate()"
        >
          <Plus class="h-4 w-4" />
          {{ t('organization.createCta') }}
        </button>
      </PermissionGuard>
    </header>

    <div class="flex flex-col gap-3 rounded-2xl border border-brand-border/80 bg-brand-surface p-3 sm:flex-row sm:items-center">
      <div class="relative min-w-0 flex-1">
        <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" />
        <input
          v-model="filters.search"
          type="search"
          class="w-full rounded-xl border border-brand-border bg-white py-2.5 pe-3 ps-10 text-sm outline-none focus:border-brand-primary"
          :placeholder="t('organization.searchPlaceholder')"
        />
      </div>
      <div class="w-full sm:w-48">
        <AppSelect
          v-model="filters.status"
          :options="[
            { value: 'all', label: t('organization.filters.all') },
            { value: 'active', label: t('organization.status.active') },
            { value: 'inactive', label: t('organization.status.inactive') },
          ]"
        />
      </div>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border/80 bg-brand-surface px-6 py-16 text-center text-brand-muted"
    >
      {{ t('organization.loading') }}
    </div>

    <div
      v-else-if="isError"
      class="rounded-2xl border border-red-200 bg-red-50 px-6 py-10 text-center"
    >
      <p class="text-red-700">{{ t('organization.error') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold text-brand-primary" @click="() => refetch()">
        {{ t('organization.retry') }}
      </button>
    </div>

    <div
      v-else-if="tree.length === 0"
      class="flex flex-col items-center rounded-2xl border border-dashed border-brand-border bg-brand-surface px-6 py-16 text-center"
    >
      <FolderTree class="mb-3 h-10 w-10 text-brand-muted" />
      <p class="text-base font-medium text-brand-ink">{{ t('organization.emptyTitle') }}</p>
      <p class="mt-1 text-sm text-brand-muted">{{ t('organization.emptyBody') }}</p>
      <PermissionGuard permission="organization_units.create">
        <button
          type="button"
          class="mt-5 inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white"
          @click="openCreate()"
        >
          <Plus class="h-4 w-4" />
          {{ t('organization.emptyCta') }}
        </button>
      </PermissionGuard>
    </div>

    <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
      <section class="rounded-2xl border border-brand-border/80 bg-brand-surface">
        <div class="border-b border-brand-border/70 px-4 py-3 text-sm font-semibold text-brand-ink">
          {{ t('organization.treeTitle') }}
          <span v-if="isFetching" class="ms-2 text-xs font-normal text-brand-muted">…</span>
        </div>
        <ul class="max-h-[70vh] overflow-y-auto p-2" role="tree">
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
        class="rounded-2xl border border-brand-border/80 bg-brand-surface"
        :class="mobileDetailsOpen ? 'block' : 'hidden lg:block'"
      >
        <div
          v-if="!selected"
          class="flex h-full min-h-[280px] items-center justify-center px-6 text-sm text-brand-muted"
        >
          {{ t('organization.selectHint') }}
        </div>
        <div v-else class="flex flex-col gap-4 p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-brand-ink">{{ selected.name }}</h2>
              <p class="mt-1 font-mono text-xs text-brand-muted">{{ selected.code }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-brand-muted hover:bg-brand-canvas lg:hidden"
              @click="mobileDetailsOpen = false"
            >
              <ChevronLeft class="h-5 w-5" />
            </button>
          </div>

          <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-brand-muted">{{ t('organization.fields.type') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-ink">{{ typeLabel(selected.type) }}</dd>
            </div>
            <div>
              <dt class="text-brand-muted">{{ t('organization.fields.status') }}</dt>
              <dd class="mt-0.5">
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="
                    selected.status === 'active'
                      ? 'bg-emerald-50 text-emerald-800'
                      : 'bg-slate-100 text-slate-600'
                  "
                >
                  {{ statusLabel(selected.status) }}
                </span>
              </dd>
            </div>
            <div>
              <dt class="text-brand-muted">{{ t('organization.fields.parent') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-ink">{{ parentName }}</dd>
            </div>
            <div>
              <dt class="text-brand-muted">{{ t('organization.fields.manager') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-ink">
                <template v-if="selected.manager">
                  {{ selected.manager.name }}
                  <span
                    v-if="selected.manager.status !== 'active'"
                    class="ms-1 text-xs text-amber-700"
                    >({{ t('organization.managerDisabled') }})</span
                  >
                </template>
                <template v-else>—</template>
              </dd>
            </div>
            <div>
              <dt class="text-brand-muted">{{ t('organization.fields.childrenCount') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-ink">{{ selected.children_count }}</dd>
            </div>
          </dl>

          <div class="flex flex-wrap gap-2 border-t border-brand-border/70 pt-4">
            <PermissionGuard permission="organization_units.update">
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-xl border border-brand-border px-3 py-2 text-sm font-medium hover:bg-brand-canvas"
                @click="openEdit(selected)"
              >
                <Pencil class="h-4 w-4" />
                {{ t('organization.edit') }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-xl border border-brand-border px-3 py-2 text-sm font-medium hover:bg-brand-canvas"
                @click="openMove"
              >
                <ArrowRightLeft class="h-4 w-4" />
                {{ t('organization.moveCta') }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-xl border border-brand-border px-3 py-2 text-sm font-medium hover:bg-brand-canvas"
                @click="toggleStatus(selected)"
              >
                <PowerOff v-if="selected.status === 'active'" class="h-4 w-4" />
                <Power v-else class="h-4 w-4" />
                {{
                  selected.status === 'active'
                    ? t('organization.deactivateCta')
                    : t('organization.activateCta')
                }}
              </button>
            </PermissionGuard>
            <PermissionGuard permission="organization_units.delete">
              <button
                v-if="selected.children_count === 0"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                @click="confirmDelete(selected)"
              >
                <Trash2 class="h-4 w-4" />
                {{ t('organization.deleteCta') }}
              </button>
            </PermissionGuard>
            <PermissionGuard permission="organization_units.create">
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-xl border border-brand-border px-3 py-2 text-sm font-medium hover:bg-brand-canvas"
                @click="openCreate(selected.id)"
              >
                <Plus class="h-4 w-4" />
                {{ t('organization.addChild') }}
              </button>
            </PermissionGuard>
          </div>
        </div>
      </section>
    </div>

    <OrganizationUnitFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :submitting="isSubmitting"
      :parent-options="parentOptions"
      :manager-options="managerOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="form = $event"
    />

    <Teleport to="body">
      <div
        v-if="moveOpen && selected"
        class="fixed inset-0 z-50 flex items-center justify-center bg-[rgba(15,23,20,0.32)] p-4"
      >
        <div class="w-full max-w-md rounded-2xl bg-brand-surface p-5 shadow-xl">
          <h3 class="text-lg font-semibold text-brand-ink">{{ t('organization.moveTitle') }}</h3>
          <p class="mt-1 text-sm text-brand-muted">{{ t('organization.moveBody') }}</p>
          <div class="mt-4">
            <span class="mb-1.5 block text-sm font-medium">{{ t('organization.fields.parent') }}</span>
            <AppSelect
              :model-value="moveParentId"
              :options="moveParentOptions"
              searchable
              @update:model-value="
                moveParentId = $event === null || $event === '' ? null : Number($event)
              "
            />
          </div>
          <div class="mt-5 flex justify-end gap-2">
            <button
              type="button"
              class="rounded-xl px-4 py-2 text-sm text-brand-muted"
              @click="moveOpen = false"
            >
              {{ t('organization.cancel') }}
            </button>
            <button
              type="button"
              class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
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

