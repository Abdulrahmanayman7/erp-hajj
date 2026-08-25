<script setup lang="ts">
import { CalendarDays, Pencil, Plus, Power, PowerOff, Trash2, ArrowRightLeft, UserRound, X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'

import type { OrganizationUnit, OrganizationUnitType } from '../types/organization'

defineProps<{
  unit: OrganizationUnit
  parentName: string
  showClose?: boolean
}>()

const emit = defineEmits<{
  close: []
  edit: []
  move: []
  toggleStatus: []
  addChild: []
  delete: []
}>()

const { t } = useI18n()

function typeLabel(type: OrganizationUnitType): string {
  return t(`organization.types.${type}`)
}

function statusLabel(status: string): string {
  return status === 'active' ? t('organization.status.active') : t('organization.status.inactive')
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('ar-SA')
}

function typeBadgeClass(type: OrganizationUnitType): string {
  if (type === 'department') return 'bg-brand-primary-soft text-brand-primary-dark'
  if (type === 'section') return 'bg-brand-gold-soft text-[#8a6a2e]'
  return 'bg-[#F4F6F5] text-brand-text-secondary'
}
</script>

<template>
  <div class="flex min-h-0 flex-col">
    <div class="shrink-0 border-b border-brand-border bg-[#F7F8F6] px-4 py-4 sm:px-5">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="break-words text-lg font-bold text-brand-text">{{ unit.name }}</h2>
            <span
              class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
              :class="
                unit.status === 'active'
                  ? 'bg-emerald-50 text-emerald-800'
                  : 'bg-slate-100 text-slate-600'
              "
            >
              {{ statusLabel(unit.status) }}
            </span>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <span
              class="inline-flex items-center rounded-lg border border-brand-border bg-brand-surface px-2 py-0.5 font-mono text-[11px] font-bold tracking-wide text-brand-primary-dark"
              dir="ltr"
            >
              {{ unit.code }}
            </span>
            <span
              class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-bold"
              :class="typeBadgeClass(unit.type)"
            >
              {{ typeLabel(unit.type) }}
            </span>
          </div>
        </div>
        <button
          v-if="showClose"
          type="button"
          class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-brand-text-muted transition hover:bg-brand-surface hover:text-brand-text"
          :aria-label="t('organization.close')"
          @click="emit('close')"
        >
          <X class="h-5 w-5" :stroke-width="2" />
        </button>
      </div>
    </div>

    <div class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto p-4 sm:p-5">
      <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-xl bg-brand-bg px-3.5 py-3">
          <dt class="text-[11px] font-semibold text-brand-text-muted">
            {{ t('organization.fields.parent') }}
          </dt>
          <dd class="mt-1 break-words text-sm font-semibold text-brand-text">{{ parentName }}</dd>
        </div>
        <div class="rounded-xl bg-brand-bg px-3.5 py-3">
          <dt class="text-[11px] font-semibold text-brand-text-muted">
            {{ t('organization.fields.manager') }}
          </dt>
          <dd class="mt-1 flex items-start gap-1.5 text-sm font-semibold text-brand-text">
            <UserRound class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-text-muted" :stroke-width="1.85" />
            <template v-if="unit.manager">
              <span class="min-w-0 break-words">{{ unit.manager.name }}</span>
              <span
                v-if="unit.manager.status !== 'active'"
                class="shrink-0 text-xs font-medium text-amber-700"
              >
                ({{ t('organization.managerDisabled') }})
              </span>
            </template>
            <template v-else>{{ t('organization.noManager') }}</template>
          </dd>
        </div>
        <div class="rounded-xl bg-brand-bg px-3.5 py-3">
          <dt class="text-[11px] font-semibold text-brand-text-muted">
            {{ t('organization.fields.childrenCount') }}
          </dt>
          <dd class="mt-1 text-sm font-semibold text-brand-text">{{ unit.children_count }}</dd>
        </div>
        <div class="rounded-xl bg-brand-bg px-3.5 py-3">
          <dt class="text-[11px] font-semibold text-brand-text-muted">
            {{ t('organization.fields.createdAt') }}
          </dt>
          <dd class="mt-1 flex items-center gap-1.5 text-sm font-semibold text-brand-text">
            <CalendarDays class="h-3.5 w-3.5 shrink-0 text-brand-text-muted" :stroke-width="1.85" />
            {{ formatDate(unit.created_at) }}
          </dd>
        </div>
        <div class="rounded-xl bg-brand-bg px-3.5 py-3 sm:col-span-2">
          <dt class="text-[11px] font-semibold text-brand-text-muted">
            {{ t('organization.fields.updatedAt') }}
          </dt>
          <dd class="mt-1 text-sm font-semibold text-brand-text">
            {{ formatDate(unit.updated_at) }}
          </dd>
        </div>
      </dl>

      <div class="grid grid-cols-1 gap-2 border-t border-brand-border pt-4 sm:flex sm:flex-wrap">
        <PermissionGuard permission="organization_units.update">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg sm:h-10 sm:w-auto"
            @click="emit('edit')"
          >
            <Pencil class="h-4 w-4" :stroke-width="1.85" />
            {{ t('organization.edit') }}
          </button>
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg sm:h-10 sm:w-auto"
            @click="emit('move')"
          >
            <ArrowRightLeft class="h-4 w-4" :stroke-width="1.85" />
            {{ t('organization.moveCta') }}
          </button>
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold transition hover:bg-brand-bg sm:h-10 sm:w-auto"
            :class="
              unit.status === 'active'
                ? 'text-amber-800 hover:border-amber-200 hover:bg-amber-50'
                : 'text-emerald-800 hover:border-emerald-200 hover:bg-emerald-50'
            "
            @click="emit('toggleStatus')"
          >
            <PowerOff v-if="unit.status === 'active'" class="h-4 w-4" :stroke-width="1.85" />
            <Power v-else class="h-4 w-4" :stroke-width="1.85" />
            {{
              unit.status === 'active'
                ? t('organization.deactivateCta')
                : t('organization.activateCta')
            }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="organization_units.create">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-brand-primary-dark px-3 text-sm font-semibold text-white transition hover:bg-brand-primary sm:h-10 sm:w-auto"
            @click="emit('addChild')"
          >
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            {{ t('organization.addChild') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="organization_units.delete">
          <button
            v-if="unit.children_count === 0"
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-red-200 px-3 text-sm font-semibold text-red-700 transition hover:bg-red-50 sm:h-10 sm:w-auto"
            @click="emit('delete')"
          >
            <Trash2 class="h-4 w-4" :stroke-width="1.85" />
            {{ t('organization.deleteCta') }}
          </button>
        </PermissionGuard>
      </div>

      <EntityDocumentsSection
        linkable-type="organization_unit"
        :linkable-id="unit.id"
        :link-label="`${unit.code} — ${unit.name}`"
      />
    </div>
  </div>
</template>
