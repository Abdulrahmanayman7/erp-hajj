<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import type { RoleSummary } from '@/modules/roles/types/roles'
import type { AvatarGroup, TenantUser } from '../types/users'

export interface UserFormState {
  name: string
  email: string
  role_ids: number[]
  send_invite: boolean
  temporary_password: string
  temporary_password_confirmation: string
  avatar_group: AvatarGroup
}

const props = defineProps<{
  open: boolean
  editing: TenantUser | null
  form: UserFormState
  roles: RoleSummary[]
  formError: string
  submitting: boolean
  canAssignRoles: boolean
  forceManualPassword?: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
}>()

const { t } = useI18n()

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('users.editTitle') : t('users.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('users.editSubtitle') : t('users.createSubtitle'),
)
const primaryLabel = computed(() => (isEdit.value ? t('users.editCta') : t('users.createCta')))

const avatarOptions = computed(() => [
  { value: 'male' as const, label: t('users.fields.avatarMale') },
  { value: 'female' as const, label: t('users.fields.avatarFemale') },
  { value: 'neutral' as const, label: t('users.fields.avatarNeutral') },
])

function isOwnerRole(role: RoleSummary): boolean {
  return role.code === 'tenant_owner'
}

function isRoleDisabled(role: RoleSummary): boolean {
  return !role.is_active && !props.form.role_ids.includes(role.id)
}

function isRoleSelected(role: RoleSummary): boolean {
  return props.form.role_ids.includes(role.id)
}

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="title"
    :description="subtitle"
    size="md"
    :submitting="submitting"
    :cancel-label="t('users.cancel')"
    :submit-label="submitting ? t('users.saving') : primaryLabel"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <div class="space-y-5">
      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('users.fields.name') }}
        </span>
        <input
          v-model="form.name"
          type="text"
          required
          autocomplete="name"
          :placeholder="t('users.fields.namePlaceholder')"
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition duration-150 placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:cursor-not-allowed disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
        />
      </label>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('users.fields.email') }}
        </span>
        <input
          v-model="form.email"
          type="email"
          required
          autocomplete="email"
          dir="ltr"
          :placeholder="t('users.fields.emailPlaceholder')"
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition duration-150 placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:cursor-not-allowed disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
        />
      </label>

      <fieldset class="min-w-0">
        <legend class="text-sm font-semibold text-brand-text">
          {{ t('users.fields.avatarGroup') }}
        </legend>
        <p class="mt-1.5 text-[12px] leading-relaxed text-brand-text-secondary">
          {{ t('users.fields.avatarGroupHint') }}
        </p>
        <div class="mt-3 grid grid-cols-3 gap-2">
          <button
            v-for="option in avatarOptions"
            :key="option.value"
            type="button"
            class="rounded-[11px] border px-2 py-3 text-center text-xs font-semibold transition duration-150"
            :class="
              form.avatar_group === option.value
                ? 'border-brand-primary bg-brand-primary-soft text-brand-primary-dark'
                : 'border-brand-border bg-brand-surface text-brand-text-secondary hover:border-brand-primary/25 hover:bg-brand-primary-soft/40'
            "
            :disabled="submitting"
            @click="form.avatar_group = option.value"
          >
            {{ option.label }}
          </button>
        </div>
      </fieldset>
    </div>

    <fieldset v-if="canAssignRoles" class="min-w-0">
      <legend class="text-[15px] font-semibold text-brand-text">
        {{ t('users.fields.roles') }}
      </legend>
      <p class="mt-1.5 text-[13px] text-brand-text-secondary">
        {{ t('users.rolesHelper') }}
      </p>

      <div class="mt-4 space-y-2.5">
        <label
          v-for="role in roles"
          :key="role.id"
          class="flex min-h-[60px] cursor-pointer items-start gap-3 rounded-[11px] border px-3.5 py-3 transition duration-[160ms] ease-out"
          :class="[
            isRoleDisabled(role)
              ? 'cursor-not-allowed border-brand-border bg-brand-bg opacity-55'
              : isOwnerRole(role) && isRoleSelected(role)
                ? 'border-brand-gold/55 bg-brand-gold-soft'
                : isRoleSelected(role)
                  ? 'border-brand-primary bg-brand-primary-soft'
                  : isOwnerRole(role)
                    ? 'border-brand-gold/35 bg-brand-surface hover:bg-brand-gold-soft/50'
                    : 'border-brand-border bg-brand-surface hover:border-brand-primary/25 hover:bg-brand-primary-soft/55',
          ]"
        >
          <input
            v-model="form.role_ids"
            type="checkbox"
            class="mt-0.5 h-4 w-4 shrink-0 rounded border-brand-border text-brand-primary focus:ring-brand-primary/25 disabled:cursor-not-allowed"
            :value="role.id"
            :disabled="isRoleDisabled(role) || submitting"
          />
          <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
              <span class="text-[14px] font-semibold text-brand-text">
                {{ role.name }}
              </span>
              <span
                v-if="isOwnerRole(role)"
                class="inline-flex items-center rounded-md bg-brand-gold-soft px-2 py-0.5 text-[11px] font-semibold text-[#8A6A2E] ring-1 ring-brand-gold/35"
              >
                {{ t('users.highAuthorityBadge') }}
              </span>
            </span>
            <span
              v-if="role.description"
              class="mt-1 block text-[12px] leading-relaxed text-brand-text-secondary"
            >
              {{ role.description }}
            </span>
          </span>
        </label>
      </div>
    </fieldset>

    <section v-if="!editing" class="space-y-4">
      <h4 class="text-[15px] font-semibold text-brand-text">
        {{ t('users.passwordSection') }}
      </h4>

      <p
        v-if="forceManualPassword"
        class="rounded-[11px] border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm text-amber-900"
        role="status"
      >
        {{ t('users.manualPasswordForcedHint') }}
      </p>

      <label
        class="flex cursor-pointer items-start gap-4 rounded-[11px] border border-brand-border bg-[#F7F8F6] px-3.5 py-3.5 transition duration-150 hover:border-brand-primary/20"
        :class="{ 'opacity-60': forceManualPassword }"
      >
        <span class="min-w-0 flex-1 text-start">
          <span class="block text-sm font-semibold text-brand-text">
            {{ t('users.fields.sendInvite') }}
          </span>
          <span class="mt-1 block text-[12px] leading-relaxed text-brand-text-secondary">
            {{
              forceManualPassword
                ? t('users.inviteMailerUnavailableHint')
                : t('users.sendInviteHint')
            }}
          </span>
        </span>

        <span class="relative mt-0.5 inline-flex h-6 w-11 shrink-0">
          <input
            v-model="form.send_invite"
            type="checkbox"
            role="switch"
            class="peer sr-only"
            :disabled="submitting || forceManualPassword"
            :aria-checked="form.send_invite"
          />
          <span
            class="absolute inset-0 rounded-full bg-neutral-300 transition duration-150 peer-checked:bg-brand-primary peer-focus-visible:ring-2 peer-focus-visible:ring-brand-primary/25 peer-disabled:opacity-50"
            aria-hidden="true"
          />
          <span
            class="pointer-events-none absolute top-0.5 start-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-[inset-inline-start] duration-150 peer-checked:start-[1.375rem]"
            aria-hidden="true"
          />
        </span>
      </label>

      <template v-if="!form.send_invite">
        <label class="block">
          <span class="mb-2 block text-sm font-semibold text-brand-text">
            {{ t('users.fields.temporaryPassword') }}
          </span>
          <input
            v-model="form.temporary_password"
            type="password"
            autocomplete="new-password"
            class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition duration-150 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:cursor-not-allowed disabled:bg-brand-bg disabled:opacity-70"
            :disabled="submitting"
          />
        </label>
        <label class="block">
          <span class="mb-2 block text-sm font-semibold text-brand-text">
            {{ t('users.fields.confirmPassword') }}
          </span>
          <input
            v-model="form.temporary_password_confirmation"
            type="password"
            autocomplete="new-password"
            class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition duration-150 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:cursor-not-allowed disabled:bg-brand-bg disabled:opacity-70"
            :disabled="submitting"
          />
        </label>
      </template>
    </section>

    <p
      v-if="formError"
      class="rounded-[11px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700"
      role="alert"
    >
      {{ formError }}
    </p>
  </AppFormDrawer>
</template>
