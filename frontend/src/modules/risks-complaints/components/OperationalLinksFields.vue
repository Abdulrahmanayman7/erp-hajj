<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'

import { listSeasonFacilities, listFacilities } from '@/modules/accommodation/api/accommodationApi'
import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { listPilgrimGroups } from '@/modules/season-pilgrims/api/pilgrimGroupsApi'
import { listSeasonPilgrims } from '@/modules/season-pilgrims/api/seasonPilgrimsApi'
import { listSeasonVehicles } from '@/modules/transportation/api/seasonFleetApi'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { toSelectId } from '@/shared/lookups/selectOptions'
import { usePermissions } from '@/shared/composables/usePermissions'

import { getOperationalLinksCapabilities } from '../api/risksApi'

export interface OperationalLinksState {
  season_pilgrim_id: number | ''
  group_id: number | ''
  facility_id: number | ''
  transport_season_vehicle_id: number | ''
  project_id: number | ''
}

const props = defineProps<{
  modelValue: OperationalLinksState
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [OperationalLinksState]
}>()

const { t } = useI18n()
const { can } = usePermissions()
const seasonStore = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(seasonStore)

const projectsAvailable = ref(false)
const projectsReason = ref<string | null>('season_projects_api_unavailable')
const facilityOptions = ref<AppSelectOption[]>([{ value: '', label: t('risks.links.none') }])
const groupOptions = ref<AppSelectOption[]>([{ value: '', label: t('risks.links.none') }])
const vehicleOptions = ref<AppSelectOption[]>([{ value: '', label: t('risks.links.none') }])

const patch = (part: Partial<OperationalLinksState>) =>
  emit('update:modelValue', { ...props.modelValue, ...part })

const canPilgrims = computed(() => can('season_pilgrims.view'))
const canGroups = computed(() => can('season_pilgrims.view'))
const canFacilities = computed(() => can('accommodation.view'))
const canVehicles = computed(() => can('transportation.view') || can('transportation.manage_season_fleet'))

async function loadCapabilities(): Promise<void> {
  try {
    const caps = await getOperationalLinksCapabilities(selectedSeasonId.value)
    projectsAvailable.value = caps.season_projects_available
    projectsReason.value = caps.season_projects_reason
  } catch {
    projectsAvailable.value = false
    projectsReason.value = 'season_projects_api_unavailable'
  }
}

async function loadLookups(): Promise<void> {
  if (selectedSeasonId.value == null) return

  if (canGroups.value) {
    try {
      const groups = await listPilgrimGroups(selectedSeasonId.value)
      groupOptions.value = [
        { value: '', label: t('risks.links.none') },
        ...groups.map((g) => ({ value: g.id, label: g.name, hint: g.code ?? undefined })),
      ]
    } catch {
      groupOptions.value = [{ value: '', label: t('risks.links.none') }]
    }
  }

  if (canFacilities.value) {
    try {
      const [seasonFacilities, facilitiesPage] = await Promise.all([
        listSeasonFacilities(1, 100),
        listFacilities(1, 100),
      ])
      const names = new Map(
        (facilitiesPage.data ?? []).map((f) => [f.id, f.name] as const),
      )
      facilityOptions.value = [
        { value: '', label: t('risks.links.none') },
        ...seasonFacilities.data
          .filter((sf) => sf.is_active)
          .map((sf) => ({
            value: sf.facility_id,
            label: names.get(sf.facility_id) ?? `#${sf.facility_id}`,
          })),
      ]
    } catch {
      facilityOptions.value = [{ value: '', label: t('risks.links.none') }]
    }
  }

  if (canVehicles.value) {
    try {
      const vehicles = await listSeasonVehicles({
        seasonId: selectedSeasonId.value,
        is_active: true,
        per_page: 100,
      })
      vehicleOptions.value = [
        { value: '', label: t('risks.links.none') },
        ...vehicles.data.map((v) => ({
          value: v.id,
          label: v.vehicle?.plate_number ?? v.vehicle?.vehicle_number ?? `#${v.id}`,
          hint: v.vehicle?.vehicle_number,
        })),
      ]
    } catch {
      vehicleOptions.value = [{ value: '', label: t('risks.links.none') }]
    }
  }
}

const fetchPilgrims = (params: { search?: string; page: number; per_page: number }) =>
  listSeasonPilgrims(
    { search: params.search, page: params.page, per_page: params.per_page },
    selectedSeasonId.value,
  )

const mapPilgrim = (row: {
  id: number
  reference_number?: string | null
  pilgrim?: { full_name?: string | null } | null
}) => ({
  value: row.id,
  label: row.pilgrim?.full_name ?? row.reference_number ?? `#${row.id}`,
  hint: row.reference_number ?? undefined,
})

onMounted(async () => {
  await loadCapabilities()
  await loadLookups()
})

watch(selectedSeasonId, async () => {
  await loadCapabilities()
  await loadLookups()
})
</script>

<template>
  <section class="space-y-3 rounded-xl border border-brand-border bg-brand-bg p-3" data-testid="operational-links-fields">
    <h4 class="text-sm font-bold text-brand-text">{{ t('risks.links.title') }}</h4>
    <p class="text-xs text-brand-text-muted">{{ t('risks.links.subtitle') }}</p>

    <label v-if="canPilgrims" class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.links.pilgrim') }}</span>
      <AppRemoteSelect
        :model-value="modelValue.season_pilgrim_id"
        query-key="season-pilgrims-link"
        :fetcher="fetchPilgrims"
        :map-option="mapPilgrim"
        :empty-option="{ value: '', label: t('risks.links.none') }"
        :placeholder="t('risks.links.pilgrim')"
        :disabled="disabled || selectedSeasonId == null"
        :enabled="selectedSeasonId != null"
        @update:model-value="patch({ season_pilgrim_id: toSelectId($event) })"
      />
    </label>

    <label v-if="canGroups" class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.links.group') }}</span>
      <AppSelect
        :model-value="modelValue.group_id"
        :options="groupOptions"
        :disabled="disabled"
        @update:model-value="patch({ group_id: toSelectId($event) })"
      />
    </label>

    <label v-if="canFacilities" class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.links.facility') }}</span>
      <AppSelect
        :model-value="modelValue.facility_id"
        :options="facilityOptions"
        :disabled="disabled"
        @update:model-value="patch({ facility_id: toSelectId($event) })"
      />
    </label>

    <label v-if="canVehicles" class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.links.vehicle') }}</span>
      <AppSelect
        :model-value="modelValue.transport_season_vehicle_id"
        :options="vehicleOptions"
        :disabled="disabled"
        @update:model-value="patch({ transport_season_vehicle_id: toSelectId($event) })"
      />
    </label>

    <div data-testid="project-link-field">
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold">{{ t('risks.links.project') }}</span>
        <AppSelect
          v-if="projectsAvailable"
          :model-value="modelValue.project_id"
          :options="[{ value: '', label: t('risks.links.none') }]"
          :disabled="disabled"
          @update:model-value="patch({ project_id: toSelectId($event) })"
        />
        <p
          v-else
          class="rounded-xl border border-dashed border-brand-border bg-brand-surface px-3 py-2 text-xs text-brand-text-muted"
          data-testid="project-link-unavailable"
        >
          {{ t('risks.links.projectUnavailable') }}
        </p>
      </label>
    </div>
  </section>
</template>
