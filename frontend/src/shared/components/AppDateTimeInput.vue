<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch, type CSSProperties } from 'vue'
import { CalendarClock, ChevronLeft, ChevronRight, X } from 'lucide-vue-next'

import { useBodyScrollLock } from '@/shared/composables/useBodyScrollLock'
import {
  GREGORIAN_MONTHS_AR,
  formatDatetimeLocalAr,
  isDatetimeLocal,
  joinDatetimeLocal,
  monthGrid,
  splitDatetimeLocal,
  toIsoDate,
  twelveYearBlock,
} from '@/shared/utils/gregorianDate'

type CalendarPanel = 'days' | 'months' | 'years'

const WEEKDAYS = ['سبت', 'أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة'] as const
const HOURS = Array.from({ length: 24 }, (_, index) => index)
const MINUTES = Array.from({ length: 60 }, (_, index) => index)

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    placeholder?: string
    disabled?: boolean
    clearable?: boolean
    id?: string
    name?: string
    invalid?: boolean
  }>(),
  {
    modelValue: '',
    placeholder: 'اختر التاريخ والوقت',
    disabled: false,
    clearable: true,
    id: undefined,
    name: undefined,
    invalid: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const root = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLButtonElement | null>(null)
const open = ref(false)
const isDesktop = ref(false)
const panel = ref<CalendarPanel>('days')
const viewYear = ref(new Date().getFullYear())
const viewMonth = ref(new Date().getMonth())
const draftDate = ref('')
const draftHours = ref(0)
const draftMinutes = ref(0)
const now = new Date()
const currentYear = now.getFullYear()
const currentMonth = now.getMonth()
const menuPosition = ref({
  top: 0,
  start: 0,
  minWidth: 320,
  openUp: false,
  rtl: true,
})

const sheetOpen = computed(() => open.value && !isDesktop.value)
useBodyScrollLock(sheetOpen)

const value = computed(() => {
  const raw = String(props.modelValue ?? '').trim()
  return isDatetimeLocal(raw) ? raw : ''
})

const hasValue = computed(() => value.value !== '')

const displayLabel = computed(() => formatDatetimeLocalAr(value.value, props.placeholder))

const todayIso = computed(() => toIsoDate(new Date()))

const selectedDate = computed(() => draftDate.value || splitDatetimeLocal(value.value).date)

const cells = computed(() =>
  monthGrid(viewYear.value, viewMonth.value).map((cell) => ({
    ...cell,
    selected: cell.iso === selectedDate.value,
    today: cell.iso === todayIso.value,
  })),
)

const yearOptions = computed(() => twelveYearBlock(viewYear.value))
const yearBlockLabel = computed(() => `${yearOptions.value[0]} – ${yearOptions.value[11]}`)

const draftValue = computed(() =>
  draftDate.value ? joinDatetimeLocal(draftDate.value, draftHours.value, draftMinutes.value) : '',
)

function syncDraftFromValue(): void {
  const parts = splitDatetimeLocal(value.value)
  draftDate.value = parts.date
  draftHours.value = parts.hours
  draftMinutes.value = parts.minutes
  if (parts.date) {
    const [year, month] = parts.date.split('-').map(Number)
    viewYear.value = year
    viewMonth.value = month - 1
    return
  }
  const current = new Date()
  viewYear.value = current.getFullYear()
  viewMonth.value = current.getMonth()
}

function emitDraft(): void {
  if (!draftDate.value) return
  emit('update:modelValue', joinDatetimeLocal(draftDate.value, draftHours.value, draftMinutes.value))
}

function shiftMonth(delta: number): void {
  const next = new Date(viewYear.value, viewMonth.value + delta, 1)
  viewYear.value = next.getFullYear()
  viewMonth.value = next.getMonth()
}

function shiftYear(delta: number): void {
  viewYear.value += delta
}

function shiftYearBlock(delta: number): void {
  viewYear.value += delta * 12
}

function setPanel(next: CalendarPanel): void {
  nextTick(() => {
    panel.value = next
  })
}

function pickMonth(monthIndex: number): void {
  viewMonth.value = monthIndex
  setPanel('days')
}

function pickYear(year: number): void {
  viewYear.value = year
  setPanel('months')
}

function syncMenuPosition(): void {
  const el = triggerRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const viewportH = window.innerHeight
  const menuH = 460
  const spaceBelow = viewportH - rect.bottom
  const openUp = spaceBelow < menuH && rect.top > spaceBelow
  const rtl = getComputedStyle(el).direction === 'rtl'
  menuPosition.value = {
    top: openUp ? rect.top - 6 : rect.bottom + 6,
    start: rtl ? window.innerWidth - rect.right : rect.left,
    minWidth: Math.max(rect.width, 320),
    openUp,
    rtl,
  }
}

function bindPositionListeners(): void {
  window.addEventListener('scroll', syncMenuPosition, true)
  window.addEventListener('resize', syncMenuPosition)
}

function unbindPositionListeners(): void {
  window.removeEventListener('scroll', syncMenuPosition, true)
  window.removeEventListener('resize', syncMenuPosition)
}

function toggle(): void {
  if (props.disabled) return
  open.value = !open.value
}

function close(): void {
  open.value = false
  panel.value = 'days'
  unbindPositionListeners()
}

function confirmAndClose(): void {
  emitDraft()
  close()
}

function pick(iso: string): void {
  draftDate.value = iso
  emitDraft()
  setPanel('days')
}

function onHoursChange(event: Event): void {
  draftHours.value = Number((event.target as HTMLSelectElement).value)
  emitDraft()
}

function onMinutesChange(event: Event): void {
  draftMinutes.value = Number((event.target as HTMLSelectElement).value)
  emitDraft()
}

function clear(event: MouseEvent): void {
  event.preventDefault()
  event.stopPropagation()
  if (props.disabled) return
  draftDate.value = ''
  emit('update:modelValue', '')
}

function eventInsidePicker(event: Event): boolean {
  const path = event.composedPath()
  return path.some((node) => {
    if (!(node instanceof Element)) return false
    if (root.value && (node === root.value || root.value.contains(node))) return true
    return Boolean(node.closest('[data-app-datetime-menu]'))
  })
}

function onDocumentClick(event: MouseEvent): void {
  if (eventInsidePicker(event)) return
  if (draftDate.value) emitDraft()
  close()
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (!open.value) return
  if (event.key !== 'Escape') return
  event.preventDefault()
  if (panel.value === 'years') {
    panel.value = 'months'
    return
  }
  if (panel.value === 'months') {
    panel.value = 'days'
    return
  }
  close()
}

const menuStyle = computed((): CSSProperties => {
  const pos = menuPosition.value
  const style: CSSProperties = {
    position: 'fixed',
    minWidth: `${pos.minWidth}px`,
    width: 'min(22rem, calc(100vw - 1.5rem))',
    zIndex: 250,
  }
  if (pos.rtl) style.right = `${pos.start}px`
  else style.left = `${pos.start}px`
  if (pos.openUp) style.bottom = `${window.innerHeight - pos.top}px`
  else style.top = `${pos.top}px`
  return style
})

const triggerClass = computed(() => {
  if (props.disabled) {
    return 'cursor-not-allowed border-brand-border bg-brand-bg text-brand-text-muted opacity-70'
  }
  if (props.invalid) {
    return 'border-red-300 bg-brand-surface text-brand-text ring-2 ring-red-200'
  }
  if (open.value) {
    return 'border-brand-primary/50 bg-brand-surface text-brand-text ring-2 ring-brand-primary/15'
  }
  return 'border-brand-border bg-brand-surface text-brand-text hover:border-brand-primary/30'
})

function syncDesktopFlag(): void {
  isDesktop.value = window.matchMedia('(min-width: 768px)').matches
}

watch(open, async (isOpen) => {
  if (!isOpen) {
    panel.value = 'days'
    return
  }
  panel.value = 'days'
  syncDesktopFlag()
  syncDraftFromValue()
  await nextTick()
  if (isDesktop.value) {
    syncMenuPosition()
    bindPositionListeners()
  }
})

let listenersBound = false
watch(open, (isOpen) => {
  if (isOpen && !listenersBound) {
    document.addEventListener('pointerdown', onDocumentClick)
    document.addEventListener('keydown', onDocumentKeydown)
    listenersBound = true
  }
  if (!isOpen && listenersBound) {
    document.removeEventListener('pointerdown', onDocumentClick)
    document.removeEventListener('keydown', onDocumentKeydown)
    listenersBound = false
  }
})

onUnmounted(() => {
  unbindPositionListeners()
  if (listenersBound) {
    document.removeEventListener('pointerdown', onDocumentClick)
    document.removeEventListener('keydown', onDocumentKeydown)
  }
})
</script>

<template>
  <div ref="root" class="relative w-full min-w-[12rem]">
    <input v-if="name" type="hidden" :name="name" :value="value" />
    <button
      :id="id"
      ref="triggerRef"
      type="button"
      class="flex h-11 w-full items-center gap-2 rounded-xl border px-3 text-start text-base font-semibold transition md:text-sm"
      :class="triggerClass"
      :disabled="disabled"
      v-bind="$attrs"
      :aria-expanded="open"
      aria-haspopup="dialog"
      @click="toggle"
    >
      <CalendarClock class="h-4 w-4 shrink-0 text-brand-primary" :stroke-width="1.75" aria-hidden="true" />
      <span
        class="min-w-0 flex-1 truncate"
        :class="hasValue ? 'text-brand-text' : 'text-brand-text-muted'"
        dir="auto"
      >
        {{ displayLabel }}
      </span>
      <span
        v-if="clearable && hasValue && !disabled"
        class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-brand-text-muted hover:bg-brand-bg hover:text-brand-text"
        role="button"
        aria-label="مسح التاريخ والوقت"
        @click="clear"
      >
        <X class="h-3.5 w-3.5" :stroke-width="2.25" />
      </span>
    </button>

    <Teleport to="body">
      <div
        v-if="open && !isDesktop"
        class="fixed inset-0 z-[240] bg-[rgba(15,23,20,0.32)]"
        aria-hidden="true"
        @click="close"
      />
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 translate-y-2 md:-translate-y-1 md:scale-[0.98]"
        enter-to-class="opacity-100 translate-y-0 md:scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0 md:scale-100"
        leave-to-class="opacity-0 translate-y-2 md:-translate-y-1 md:scale-[0.98]"
      >
        <div
          v-if="open"
          data-app-datetime-menu
          class="overflow-hidden rounded-t-2xl border border-brand-border bg-brand-surface p-3 shadow-xl ring-1 ring-black/5 md:rounded-xl"
          :class="isDesktop ? 'z-[250]' : 'fixed inset-x-0 bottom-0 z-[250]'"
          :style="
            isDesktop
              ? menuStyle
              : { paddingBottom: 'max(12px, env(safe-area-inset-bottom, 0px))' }
          "
          role="dialog"
          aria-label="اختيار التاريخ والوقت"
          @click.stop
          @pointerdown.stop
        >
          <div class="mb-3 flex items-center justify-between gap-2">
            <button
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-brand-primary-dark hover:bg-brand-primary-soft"
              :aria-label="
                panel === 'days' ? 'الشهر التالي' : panel === 'months' ? 'السنة التالية' : 'الفترة التالية'
              "
              @click="panel === 'days' ? shiftMonth(1) : panel === 'months' ? shiftYear(1) : shiftYearBlock(1)"
            >
              <ChevronRight class="h-5 w-5" :stroke-width="2.25" />
            </button>
            <div class="flex min-w-0 flex-1 items-center justify-center gap-1">
              <button
                v-if="panel === 'days'"
                type="button"
                class="rounded-lg px-2 py-1 text-sm font-bold text-brand-text hover:bg-brand-primary-soft"
                aria-label="اختيار الشهر"
                @click="setPanel('months')"
              >
                {{ GREGORIAN_MONTHS_AR[viewMonth] }}
              </button>
              <button
                v-if="panel !== 'years'"
                type="button"
                class="rounded-lg px-2 py-1 text-sm font-bold text-brand-text hover:bg-brand-primary-soft"
                aria-label="اختيار السنة"
                @click="setPanel('years')"
              >
                {{ viewYear }}
              </button>
              <p v-else class="px-2 py-1 text-sm font-bold text-brand-text">
                {{ yearBlockLabel }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-brand-primary-dark hover:bg-brand-primary-soft"
              :aria-label="
                panel === 'days' ? 'الشهر السابق' : panel === 'months' ? 'السنة السابقة' : 'الفترة السابقة'
              "
              @click="panel === 'days' ? shiftMonth(-1) : panel === 'months' ? shiftYear(-1) : shiftYearBlock(-1)"
            >
              <ChevronLeft class="h-5 w-5" :stroke-width="2.25" />
            </button>
          </div>

          <div v-if="panel === 'days'" class="grid grid-cols-7 gap-1 text-center">
            <span
              v-for="day in WEEKDAYS"
              :key="day"
              class="py-1 text-[11px] font-bold text-brand-text-muted"
            >
              {{ day }}
            </span>
            <button
              v-for="cell in cells"
              :key="cell.iso"
              type="button"
              class="inline-flex h-10 items-center justify-center rounded-xl text-sm font-semibold transition"
              :class="
                cell.selected
                  ? 'bg-brand-primary-dark text-white'
                  : cell.today
                    ? 'ring-1 ring-brand-gold text-brand-primary-dark'
                    : cell.inMonth
                      ? 'text-brand-text hover:bg-brand-primary-soft'
                      : 'text-brand-text-muted/50 hover:bg-brand-bg'
              "
              :aria-current="cell.today ? 'date' : undefined"
              :aria-pressed="cell.selected"
              @click="pick(cell.iso)"
            >
              {{ cell.day }}
            </button>
          </div>

          <div v-else-if="panel === 'months'" class="grid grid-cols-3 gap-1.5">
            <button
              v-for="(monthName, monthIndex) in GREGORIAN_MONTHS_AR"
              :key="monthName"
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl text-sm font-semibold transition"
              :class="
                monthIndex === viewMonth
                  ? 'bg-brand-primary-dark text-white'
                  : monthIndex === currentMonth && viewYear === currentYear
                    ? 'ring-1 ring-brand-gold text-brand-primary-dark'
                    : 'text-brand-text hover:bg-brand-primary-soft'
              "
              :aria-pressed="monthIndex === viewMonth"
              @click="pickMonth(monthIndex)"
            >
              {{ monthName }}
            </button>
          </div>

          <div v-else class="grid grid-cols-3 gap-1.5">
            <button
              v-for="year in yearOptions"
              :key="year"
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl text-sm font-semibold transition"
              :class="
                year === viewYear
                  ? 'bg-brand-primary-dark text-white'
                  : year === currentYear
                    ? 'ring-1 ring-brand-gold text-brand-primary-dark'
                    : 'text-brand-text hover:bg-brand-primary-soft'
              "
              :aria-pressed="year === viewYear"
              @click="pickYear(year)"
            >
              {{ year }}
            </button>
          </div>

          <div class="mt-3 border-t border-brand-border pt-3">
            <p class="mb-2 text-xs font-semibold text-brand-text-secondary">الوقت</p>
            <div class="flex items-center gap-2" dir="ltr">
              <label class="min-w-0 flex-1">
                <span class="sr-only">الساعة</span>
                <select
                  class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                  :value="draftHours"
                  @change="onHoursChange"
                >
                  <option v-for="hour in HOURS" :key="hour" :value="hour">
                    {{ String(hour).padStart(2, '0') }}
                  </option>
                </select>
              </label>
              <span class="text-sm font-bold text-brand-text-muted" aria-hidden="true">:</span>
              <label class="min-w-0 flex-1">
                <span class="sr-only">الدقيقة</span>
                <select
                  class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                  :value="draftMinutes"
                  @change="onMinutesChange"
                >
                  <option v-for="minute in MINUTES" :key="minute" :value="minute">
                    {{ String(minute).padStart(2, '0') }}
                  </option>
                </select>
              </label>
            </div>
            <p v-if="draftValue" class="mt-2 text-center text-xs font-medium text-brand-text-muted" dir="auto">
              {{ formatDatetimeLocalAr(draftValue) }}
            </p>
            <button
              type="button"
              class="mt-3 inline-flex h-11 w-full items-center justify-center rounded-xl bg-brand-primary-dark text-sm font-semibold text-white transition hover:bg-brand-primary disabled:opacity-40"
              :disabled="!draftDate"
              @click="confirmAndClose"
            >
              تم
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
