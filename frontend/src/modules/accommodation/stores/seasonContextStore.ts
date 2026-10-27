import { sessionGeneration } from '@/shared/api/sessionLifetime'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import type { AccommodationSeason, SeasonStatus } from '../types/accommodation'

const STORAGE_KEY = 'erp-hajj.accommodation.seasonId'

function readStoredSeasonId(): number | null {
  if (typeof sessionStorage === 'undefined') return null
  const raw = sessionStorage.getItem(STORAGE_KEY)
  if (!raw || !/^\d+$/.test(raw)) return null
  const id = Number(raw)
  return id > 0 ? id : null
}

export const useAccommodationSeasonStore = defineStore('accommodationSeason', () => {
  const selectedSeasonId = ref<number | null>(readStoredSeasonId())
  const season = ref<AccommodationSeason | null>(null)
  /** Fail closed until season-context succeeds; never assume writes are allowed. */
  const isWritable = ref(false)
  const selectionMode = ref<'explicit' | 'default_active' | 'none'>('none')

  const status = computed<SeasonStatus | null>(() => season.value?.status ?? null)
  const isClosed = computed(() => status.value === 'closed')
  const isActive = computed(() => status.value === 'active')
  const canWrite = computed(() => isWritable.value && !isClosed.value)
  /** Confirm APIs require Active season (`assertActive`); Draft/Closed are disabled in UI. */
  const canConfirm = computed(() => isActive.value)
  /** Sprint 3 ops APIs also require Active season (`assertActive`). */
  const canOps = computed(() => isActive.value)

  const contextGeneration = ref(0)

  function setSelectedSeasonId(id: number | null): void {
    if (id !== selectedSeasonId.value) {
      contextGeneration.value++
      clearSeasonDetails()
    }
    selectedSeasonId.value = id
    if (typeof sessionStorage === 'undefined') return
    if (id == null) {
      sessionStorage.removeItem(STORAGE_KEY)
      return
    }
    sessionStorage.setItem(STORAGE_KEY, String(id))
  }

  function applyContext(payload: {
    season: AccommodationSeason
    selection: 'explicit' | 'default_active'
    is_writable: boolean
  }, expectedContext = contextGeneration.value, expectedSession = sessionGeneration.value): void {
    if (expectedContext !== contextGeneration.value || expectedSession !== sessionGeneration.value) return
    // Select before applying details: changing selection invalidates the previous context.
    if (payload.selection === 'explicit') setSelectedSeasonId(payload.season.id)
    season.value = payload.season
    isWritable.value = payload.is_writable
    selectionMode.value = payload.selection
  }

  function clearSeasonDetails(): void {
    season.value = null
    isWritable.value = false
    selectionMode.value = 'none'
  }

  /** Headers for Accommodation and other season-scoped APIs. Omits header when unset (Active default). */
  function seasonHeaders(): Record<string, string> {
    if (selectedSeasonId.value == null) return {}
    return { 'X-Season-Id': String(selectedSeasonId.value) }
  }

  function resetSession(): void {
    contextGeneration.value++
    setSelectedSeasonId(null)
    clearSeasonDetails()
  }

  return {
    contextGeneration,
    resetSession,
    selectedSeasonId,
    season,
    isWritable,
    selectionMode,
    status,
    isClosed,
    isActive,
    canWrite,
    canConfirm,
    canOps,
    setSelectedSeasonId,
    applyContext,
    clearSeasonDetails,
    seasonHeaders,
  }
})
