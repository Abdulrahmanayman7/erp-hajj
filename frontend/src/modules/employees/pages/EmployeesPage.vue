<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  Briefcase,
  ChevronLeft,
  ChevronRight,
  FileText,
  Link2,
  Pencil,
  Plus,
  Search,
  UserCheck,
  UserCog,
  UserX,
} from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { listUsers } from '@/modules/users/api/usersApi'
import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useQuery } from '@tanstack/vue-query'

import EmployeeFormDrawer from '../components/EmployeeFormDrawer.vue'
import PositionsManagerDrawer from '../components/PositionsManagerDrawer.vue'
import SupervisorDialog from '../components/SupervisorDialog.vue'
import UserLinkDialog from '../components/UserLinkDialog.vue'
import {
  useActivateEmployeeMutation,
  useAssignEmployeeSupervisorMutation,
  useCreateEmployeeMutation,
  useDeactivateEmployeeMutation,
  useLinkEmployeeUserMutation,
  useUpdateEmployeeMutation,
} from '../mutations/useEmployeeMutations'
import { useEmployeesQuery } from '../queries/useEmployeesQuery'
import { usePositionsQuery } from '../queries/usePositionsQuery'
import type { Employee, EmployeeFormState } from '../types/employees'
import {
  filterLinkableUsers,
  filterSupervisorCandidates,
  mapEmployeeErrorCode,
  resolveEmployeesListState,
  validateEmployeeForm,
} from '../validation/employeeValidation'

const { t } = useI18n()
const { can } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  status: 'all' as 'all' | 'active' | 'inactive',
  organization_unit_id: '' as number | '',
  position_id: '' as number | '',
  supervisor_id: '' as number | '',
  page: 1,
  per_page: 15,
  sort: 'employee_number',
  direction: 'asc',
})

const queryParams = computed(() => ({
  search: filters.search || undefined,
  status: filters.status,
  organization_unit_id: filters.organization_unit_id,
  position_id: filters.position_id,
  supervisor_id: filters.supervisor_id,
  page: filters.page,
  per_page: filters.per_page,
  sort: filters.sort,
  direction: filters.direction,
}))

const { data, isLoading, isError, refetch, isFetching } = useEmployeesQuery(queryParams)

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: positionsData } = usePositionsQuery(
  { is_active: true, per_page: 100 },
  { enabled: computed(() => can('positions.view') || can('employees.create') || can('employees.update')) },
)
const { data: activeEmployeesData } = useEmployeesQuery(
  computed(() => ({ status: 'active' as const, per_page: 100 })),
)

const createMutation = useCreateEmployeeMutation()
const updateMutation = useUpdateEmployeeMutation()
const activateMutation = useActivateEmployeeMutation()
const deactivateMutation = useDeactivateEmployeeMutation()
const supervisorMutation = useAssignEmployeeSupervisorMutation()
const linkUserMutation = useLinkEmployeeUserMutation()

const employees = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveEmployeesListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: employees.value.length,
  }),
)

const drawerOpen = ref(false)
const editing = ref<Employee | null>(null)
const docsTarget = ref<Employee | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<EmployeeFormState>({
  full_name: '',
  organization_unit_id: '',
  position_id: '',
  phone: '',
  email: '',
  hire_date: '',
  notes: '',
})

const positionsOpen = ref(false)

const supervisorOpen = ref(false)
const supervisorTarget = ref<Employee | null>(null)
const supervisorId = ref<number | ''>('')
const supervisorError = ref('')

const userLinkOpen = ref(false)
const userLinkTarget = ref<Employee | null>(null)
const userId = ref<number | ''>('')
const userLinkError = ref('')

const usersQuery = useQuery({
  queryKey: ['users', 'employee-link-options'],
  queryFn: () => listUsers({ status: 'active', per_page: 100 }),
  enabled: computed(() => userLinkOpen.value),
})

const linkedUserIds = computed(() => {
  const ids = new Set<number>()
  for (const emp of activeEmployeesData.value?.data ?? []) {
    if (emp.user?.id != null) ids.add(emp.user.id)
  }
  for (const emp of employees.value) {
    if (emp.user?.id != null) ids.add(emp.user.id)
  }
  return ids
})

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('employees.filters.allStatuses') },
  { value: 'active', label: t('employees.status.active') },
  { value: 'inactive', label: t('employees.status.inactive') },
])

const orgUnitFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('employees.filters.allOrgUnits') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.code,
  })),
])

const orgUnitFormOptions = computed<AppSelectOption[]>(() =>
  (orgUnitsData.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.code,
  })),
)

const positionFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('employees.filters.allPositions') },
  ...(positionsData.value?.data ?? []).map((p) => ({
    value: p.id,
    label: p.name,
    hint: p.code ?? undefined,
  })),
])

const positionFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('employees.noPosition') },
  ...(positionsData.value?.data ?? []).map((p) => ({
    value: p.id,
    label: p.name,
    hint: p.code ?? undefined,
  })),
])

const supervisorFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('employees.filters.allSupervisors') },
  ...(activeEmployeesData.value?.data ?? []).map((e) => ({
    value: e.id,
    label: e.full_name,
    hint: e.employee_number,
  })),
])

const supervisorDialogOptions = computed<AppSelectOption[]>(() => {
  const selfId = supervisorTarget.value?.id ?? -1
  const candidates = filterSupervisorCandidates(
    activeEmployeesData.value?.data ?? [],
    selfId,
  )
  return [
    { value: '', label: t('employees.noSupervisor') },
    ...candidates.map((e) => ({
      value: e.id,
      label: e.full_name,
      hint: `${e.employee_number}${e.organization_unit ? ` · ${e.organization_unit.name}` : ''}`,
    })),
  ]
})

const userLinkOptions = computed<AppSelectOption[]>(() => {
  const currentId = userLinkTarget.value?.user?.id ?? null
  const users = filterLinkableUsers(
    usersQuery.data.value?.data ?? [],
    linkedUserIds.value,
    currentId,
  )
  return [
    { value: '', label: t('employees.selectUser') },
    ...users.map((u) => ({
      value: u.id,
      label: u.name,
      hint: u.email,
    })),
  ]
})

const isFormSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)
const isSupervisorSubmitting = computed(() => supervisorMutation.isPending.value)
const isUserLinkSubmitting = computed(() => linkUserMutation.isPending.value)

watch(
  () => [
    filters.search,
    filters.status,
    filters.organization_unit_id,
    filters.position_id,
    filters.supervisor_id,
  ],
  () => {
    filters.page = 1
  },
)

function emptyForm(): EmployeeFormState {
  return {
    full_name: '',
    organization_unit_id: '',
    position_id: '',
    phone: '',
    email: '',
    hire_date: '',
    notes: '',
  }
}

function assignForm(next: EmployeeFormState): void {
  Object.assign(form, next)
}

function clearFieldErrors(): void {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
}

function fieldMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`employees.validation.${key}`)
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('employees.errors.generic')
  }
  const mapped = mapEmployeeErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`employees.errors.${mapped}`)
  }
  return error.message || t('employees.errors.generic')
}

function openCreate(): void {
  editing.value = null
  assignForm(emptyForm())
  formError.value = ''
  clearFieldErrors()
  drawerOpen.value = true
}

function openEdit(employee: Employee): void {
  editing.value = employee
  assignForm({
    full_name: employee.full_name,
    organization_unit_id: employee.organization_unit?.id ?? '',
    position_id: employee.position?.id ?? '',
    phone: employee.phone ?? '',
    email: employee.email ?? '',
    hire_date: employee.hire_date ?? '',
    notes: employee.notes ?? '',
  })
  formError.value = ''
  clearFieldErrors()
  drawerOpen.value = true
}

function closeDrawer(): void {
  drawerOpen.value = false
}

async function submitForm(): Promise<void> {
  formError.value = ''
  clearFieldErrors()
  const validation = validateEmployeeForm(form)
  if (validation.full_name || validation.organization_unit_id || validation.email) {
    if (validation.full_name) fieldErrors.full_name = fieldMessage(validation.full_name)
    if (validation.organization_unit_id) {
      fieldErrors.organization_unit_id = fieldMessage(validation.organization_unit_id)
    }
    if (validation.email) fieldErrors.email = fieldMessage(validation.email)
    return
  }

  const payload = {
    full_name: form.full_name.trim(),
    organization_unit_id: Number(form.organization_unit_id),
    position_id: form.position_id === '' ? null : Number(form.position_id),
    phone: form.phone.trim() || null,
    email: form.email.trim() || null,
    hire_date: form.hire_date || null,
    notes: form.notes.trim() || null,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('employees.toasts.updated'))
    } else {
      await createMutation.mutateAsync(payload)
      toast.success(t('employees.toasts.created'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleStatus(employee: Employee): Promise<void> {
  const deactivating = employee.status === 'active'
  const confirmed = await confirm({
    title: deactivating
      ? t('employees.confirmDeactivateTitle')
      : t('employees.confirmActivateTitle'),
    message: deactivating
      ? t('employees.confirmDeactivateBody')
      : t('employees.confirmActivateBody'),
    confirmLabel: deactivating
      ? t('employees.confirmDeactivateCta')
      : t('employees.confirmActivateCta'),
    cancelLabel: t('employees.cancel'),
    variant: deactivating ? 'warning' : 'primary',
  })
  if (!confirmed) return
  try {
    if (deactivating) {
      await deactivateMutation.mutateAsync(employee.id)
      toast.success(t('employees.toasts.deactivated'))
    } else {
      await activateMutation.mutateAsync(employee.id)
      toast.success(t('employees.toasts.activated'))
    }
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function openSupervisor(employee: Employee): void {
  supervisorTarget.value = employee
  supervisorId.value = employee.supervisor?.id ?? ''
  supervisorError.value = ''
  supervisorOpen.value = true
}

async function submitSupervisor(): Promise<void> {
  if (!supervisorTarget.value) return
  const nextId = supervisorId.value === '' ? null : Number(supervisorId.value)
  const currentId = supervisorTarget.value.supervisor?.id ?? null
  if (nextId !== currentId) {
    const confirmed = await confirm({
      title: t('employees.confirmSupervisorTitle'),
      message: t('employees.confirmSupervisorBody'),
      confirmLabel: t('employees.confirmSupervisorCta'),
      cancelLabel: t('employees.cancel'),
      variant: 'primary',
    })
    if (!confirmed) return
  }
  supervisorError.value = ''
  try {
    await supervisorMutation.mutateAsync({
      id: supervisorTarget.value.id,
      supervisorId: nextId,
    })
    toast.success(t('employees.toasts.supervisorChanged'))
    supervisorOpen.value = false
  } catch (error) {
    supervisorError.value = apiMessage(error)
  }
}

function openUserLink(employee: Employee): void {
  userLinkTarget.value = employee
  userId.value = employee.user?.id ?? ''
  userLinkError.value = ''
  userLinkOpen.value = true
}

async function submitUserLink(): Promise<void> {
  if (!userLinkTarget.value || userId.value === '') return
  userLinkError.value = ''
  try {
    await linkUserMutation.mutateAsync({
      id: userLinkTarget.value.id,
      userId: Number(userId.value),
    })
    toast.success(t('employees.toasts.userLinked'))
    userLinkOpen.value = false
  } catch (error) {
    userLinkError.value = apiMessage(error)
  }
}

async function unlinkUser(): Promise<void> {
  if (!userLinkTarget.value) return
  const confirmed = await confirm({
    title: t('employees.confirmUnlinkTitle'),
    message: t('employees.confirmUnlinkBody'),
    confirmLabel: t('employees.confirmUnlinkCta'),
    cancelLabel: t('employees.cancel'),
    variant: 'warning',
  })
  if (!confirmed) return
  userLinkError.value = ''
  try {
    await linkUserMutation.mutateAsync({
      id: userLinkTarget.value.id,
      userId: null,
    })
    toast.success(t('employees.toasts.userUnlinked'))
    userLinkOpen.value = false
  } catch (error) {
    userLinkError.value = apiMessage(error)
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div class="min-w-0">
        <h2 class="text-[1.75rem] font-bold leading-tight text-brand-text">
          {{ t('employees.title') }}
        </h2>
        <p class="mt-1.5 text-sm text-brand-text-secondary">
          {{ t('employees.subtitle') }}
        </p>
        <p v-if="meta" class="mt-2">
          <span
            class="inline-flex items-center rounded-full bg-brand-primary-soft px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark"
          >
            {{ t('employees.total', { count: meta.total }) }}
          </span>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <PermissionGuard permission="positions.view">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
            @click="positionsOpen = true"
          >
            <Briefcase class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('employees.positionsLink') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="employees.create">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            <span>{{ t('employees.add') }}</span>
          </button>
        </PermissionGuard>
      </div>
    </div>

    <div
      class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
    >
      <div class="relative min-w-48 flex-1">
        <Search
          class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
          :stroke-width="1.75"
          aria-hidden="true"
        />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :placeholder="t('employees.searchPlaceholder')"
        />
      </div>
      <AppSelect v-model="filters.status" :options="statusOptions" />
      <AppSelect
        v-model="filters.organization_unit_id"
        :options="orgUnitFilterOptions"
        searchable
      />
      <AppSelect
        v-if="can('positions.view')"
        v-model="filters.position_id"
        :options="positionFilterOptions"
        searchable
      />
      <AppSelect
        v-model="filters.supervisor_id"
        :options="supervisorFilterOptions"
        searchable
      />
    </div>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('employees.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('employees.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('employees.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center"
    >
      <p class="text-sm text-brand-text-muted">{{ t('employees.empty') }}</p>
      <PermissionGuard permission="employees.create">
        <button
          type="button"
          class="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('employees.add') }}
        </button>
      </PermissionGuard>
    </div>
    <div
      v-else
      class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('employees.columns.employeeNumber') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('employees.columns.fullName') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('employees.columns.orgUnit') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('employees.columns.position') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('employees.columns.supervisor') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('employees.columns.status') }}
              </th>
              <th
                class="w-36 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
              >
                {{ t('employees.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(employee, index) in employees"
              :key="employee.id"
              class="group transition-colors duration-150"
              :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
            >
              <td
                class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 font-mono text-xs text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                dir="ltr"
              >
                {{ employee.employee_number }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 font-semibold text-brand-text group-hover:bg-[#EEF2F0]"
              >
                {{ employee.full_name }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
              >
                {{ employee.organization_unit?.name ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
              >
                {{ employee.position?.name ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
              >
                {{ employee.supervisor?.full_name ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]"
              >
                <div class="flex justify-center">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="
                      employee.status === 'active'
                        ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
                        : 'bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200/80'
                    "
                  >
                    <span
                      class="h-1.5 w-1.5 rounded-full"
                      :class="employee.status === 'active' ? 'bg-emerald-500' : 'bg-neutral-400'"
                    />
                    {{ t(`employees.status.${employee.status}`) }}
                  </span>
                </div>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]"
              >
                <div class="inline-flex items-center justify-center gap-0.5">
                  <PermissionGuard permission="documents.view">
                    <AppTooltip :text="t('documents.entitySection.title')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-secondary transition hover:bg-brand-bg"
                        :aria-label="t('documents.entitySection.title')"
                        @click="docsTarget = employee"
                      >
                        <FileText class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                  <PermissionGuard permission="employees.update">
                    <AppTooltip :text="t('employees.actions.edit')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-primary-soft"
                        :aria-label="t('employees.actions.edit')"
                        @click="openEdit(employee)"
                      >
                        <Pencil class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                    <AppTooltip :text="t('employees.actions.linkUser')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-secondary transition hover:bg-brand-bg"
                        :aria-label="t('employees.actions.linkUser')"
                        @click="openUserLink(employee)"
                      >
                        <Link2 class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                  <PermissionGuard permission="employees.assign_supervisor">
                    <AppTooltip :text="t('employees.actions.assignSupervisor')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-secondary transition hover:bg-brand-bg"
                        :aria-label="t('employees.actions.assignSupervisor')"
                        @click="openSupervisor(employee)"
                      >
                        <UserCog class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                  <PermissionGuard
                    v-if="employee.status === 'active'"
                    permission="employees.deactivate"
                  >
                    <AppTooltip :text="t('employees.actions.deactivate')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-amber-700 transition hover:bg-amber-50"
                        :aria-label="t('employees.actions.deactivate')"
                        @click="toggleStatus(employee)"
                      >
                        <UserX class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                  <PermissionGuard v-else permission="employees.update">
                    <AppTooltip :text="t('employees.actions.activate')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-emerald-700 transition hover:bg-emerald-50"
                        :aria-label="t('employees.actions.activate')"
                        @click="toggleStatus(employee)"
                      >
                        <UserCheck class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 border-t border-brand-border bg-[#F7F8F6] px-5 py-3 text-sm"
      >
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page <= 1 || isFetching"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
          <span>{{ t('employees.prev') }}</span>
        </button>
        <span class="text-xs font-semibold text-brand-text-muted">
          {{ filters.page }} / {{ meta.last_page }}
        </span>
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          <span>{{ t('employees.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </div>

    <EntityDocumentsSection
      v-if="docsTarget"
      class="mt-2"
      linkable-type="employee"
      :linkable-id="docsTarget.id"
      :link-label="`${docsTarget.employee_number} — ${docsTarget.full_name}`"
    />

    <EmployeeFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgUnitFormOptions"
      :position-options="positionFormOptions"
      @close="closeDrawer"
      @submit="submitForm"
      @update:form="assignForm"
    />

    <SupervisorDialog
      :open="supervisorOpen"
      :employee="supervisorTarget"
      :supervisor-id="supervisorId"
      :supervisor-options="supervisorDialogOptions"
      :form-error="supervisorError"
      :submitting="isSupervisorSubmitting"
      @close="supervisorOpen = false"
      @submit="submitSupervisor"
      @update:supervisor-id="supervisorId = $event"
    />

    <UserLinkDialog
      :open="userLinkOpen"
      :employee="userLinkTarget"
      :user-id="userId"
      :user-options="userLinkOptions"
      :form-error="userLinkError"
      :submitting="isUserLinkSubmitting"
      @close="userLinkOpen = false"
      @submit="submitUserLink"
      @unlink="unlinkUser"
      @update:user-id="userId = $event"
    />

    <PositionsManagerDrawer :open="positionsOpen" @close="positionsOpen = false" />
  </div>
</template>
