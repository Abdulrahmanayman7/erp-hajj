<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch, type CSSProperties } from 'vue'
import { Check, ChevronDown, X } from 'lucide-vue-next'

import { requestSubmitFromControl } from '@/shared/utils/formKeyboard'
import { POPOVER_Z_INDEX } from '@/shared/ui/overlayZ'

import type { AppSelectOption } from './AppSelect.vue'

export interface AppListSelectOption extends AppSelectOption {
  badge?: string
  tone?: 'default' | 'gold'
}

const MAX_VISIBLE_CHIPS = 3

const props = withDefaults(
  defineProps<{
    modelValue?: Array<string | number>
    options?: AppListSelectOption[]
    selectedOptions?: AppListSelectOption[]
    placeholder?: string
    disabled?: boolean
    searchable?: boolean
    searchPlaceholder?: string
    remote?: boolean
    loading?: boolean
    error?: boolean
    hasMore?: boolean
    clearable?: boolean
    size?: 'sm' | 'md'
    teleport?: boolean
  }>(),
  {
    modelValue: () => [],
    options: () => [],
    selectedOptions: () => [],
    placeholder: 'اختر…',
    disabled: false,
    searchable: true,
    searchPlaceholder: '',
    remote: false,
    loading: false,
    error: false,
    hasMore: false,
    clearable: true,
    size: 'md',
    teleport: true,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: Array<string | number>]
  search: [value: string]
  'load-more': []
  retry: []
}>()

const root = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLElement | null>(null)
const open = ref(false)
const search = ref('')
const remembered = ref<AppListSelectOption[]>([])
const activeIndex = ref(-1)
const menuPosition = ref({
  top: 0,
  start: 0,
  minWidth: 0,
  maxWidth: 560,
  openUp: false,
  rtl: false,
})

function valueKey(value: string | number): string {
  return String(value)
}

const selectedKeys = computed(() => new Set((props.modelValue ?? []).map(valueKey)))

const optionCatalog = computed(() => {
  const map = new Map<string, AppListSelectOption>()
  for (const option of [...remembered.value, ...props.selectedOptions, ...props.options]) {
    map.set(valueKey(option.value), option)
  }
  return map
})

const selectedItems = computed(() =>
  (props.modelValue ?? [])
    .map((value) => optionCatalog.value.get(valueKey(value)))
    .filter((option): option is AppListSelectOption => option != null),
)

const visibleChips = computed(() => selectedItems.value.slice(0, MAX_VISIBLE_CHIPS))
const hiddenChipCount = computed(() => Math.max(0, selectedItems.value.length - MAX_VISIBLE_CHIPS))
const selectedCount = computed(() => props.modelValue?.length ?? 0)
const hasValue = computed(() => selectedCount.value > 0)

const filteredOptions = computed(() => {
  const query = search.value.trim().toLowerCase()
  const source = props.remote
    ? props.options
    : !props.searchable || !query
      ? props.options
      : props.options.filter((option) => {
          const label = String(option.label ?? '').toLowerCase()
          const hint = String(option.hint ?? '').toLowerCase()
          const badge = String(option.badge ?? '').toLowerCase()
          return label.includes(query) || hint.includes(query) || badge.includes(query)
        })

  const missing = selectedItems.value.filter(
    (item) => !source.some((option) => valueKey(option.value) === valueKey(item.value)),
  )
  return [...missing, ...source]
})

watch(search, (value) => {
  if (props.remote && open.value) emit('search', String(value).trim())
})

watch(
  () => props.modelValue,
  (values) => {
    const keys = new Set((values ?? []).map(valueKey))
    remembered.value = remembered.value.filter((option) => keys.has(valueKey(option.value)))
  },
)

const triggerClass = computed(() => {
  const base = props.size === 'sm' ? 'min-h-9 py-1 text-xs' : 'min-h-11 py-1.5 text-sm'
  if (props.disabled) {
    return `${base} cursor-not-allowed border-brand-border bg-brand-bg text-brand-text-muted opacity-70`
  }
  if (open.value) {
    return `${base} border-brand-primary/50 bg-brand-surface text-brand-text ring-2 ring-brand-primary/15`
  }
  return `${base} border-brand-border bg-brand-surface text-brand-text hover:border-brand-primary/30`
})

function isSelected(option: AppListSelectOption): boolean {
  return selectedKeys.value.has(valueKey(option.value))
}

function syncMenuPosition(): void {
  const el = triggerRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const viewportH = window.innerHeight
  const viewportW = window.innerWidth
  const menuMaxH = 360
  const spaceBelow = viewportH - rect.bottom
  const openUp = spaceBelow < menuMaxH && rect.top > spaceBelow
  const rtl = getComputedStyle(el).direction === 'rtl'
  const available = rtl ? rect.right - 12 : viewportW - rect.left - 12
  const maxWidth = Math.min(560, Math.max(available, 220))
  const minWidth = Math.min(Math.max(rect.width, 220), maxWidth)

  menuPosition.value = {
    top: openUp ? rect.top - 6 : rect.bottom + 6,
    start: rtl ? viewportW - rect.right : rect.left,
    minWidth,
    maxWidth,
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
  if (open.value) close()
  else open.value = true
}

function onTriggerKeydown(event: KeyboardEvent): void {
  if (props.disabled) return
  if (event.key === 'ArrowDown' || event.key === ' ') {
    event.preventDefault()
    if (!open.value) open.value = true
    return
  }
  if (event.key !== 'Enter' || open.value) return
  if (requestSubmitFromControl(root.value)) {
    event.preventDefault()
    event.stopPropagation()
  }
}

function close(): void {
  if (!open.value) return
  if (props.remote && search.value !== '') {
    search.value = ''
    emit('search', '')
  }
  open.value = false
  search.value = ''
  activeIndex.value = -1
  unbindPositionListeners()
}

function remember(option: AppListSelectOption): void {
  if (remembered.value.some((item) => valueKey(item.value) === valueKey(option.value))) return
  remembered.value = [...remembered.value, option]
}

function emitValues(next: Array<string | number>): void {
  emit('update:modelValue', next)
}

function toggleOption(option: AppListSelectOption): void {
  if (option.disabled) return
  remember(option)
  if (isSelected(option)) {
    emitValues((props.modelValue ?? []).filter((value) => valueKey(value) !== valueKey(option.value)))
    return
  }
  emitValues([...(props.modelValue ?? []), option.value])
}

function removeChip(option: AppListSelectOption, event: Event): void {
  event.preventDefault()
  event.stopPropagation()
  if (props.disabled) return
  emitValues((props.modelValue ?? []).filter((value) => valueKey(value) !== valueKey(option.value)))
}

function clear(event: Event): void {
  event.preventDefault()
  event.stopPropagation()
  if (props.disabled || !props.clearable) return
  remembered.value = []
  emitValues([])
}

function loadMore(): void {
  if (props.remote && props.hasMore && !props.loading) emit('load-more')
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target as HTMLElement | null
  if (!root.value?.contains(target) && !target?.closest?.('[data-app-list-select-menu]')) {
    close()
  }
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (!open.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    moveActive(1)
    return
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault()
    moveActive(-1)
    return
  }
  if ((event.key === 'Enter' || event.key === ' ') && activeIndex.value >= 0) {
    event.preventDefault()
    const option = filteredOptions.value[activeIndex.value]
    if (option) toggleOption(option)
  }
}

const menuStyle = computed((): CSSProperties | undefined => {
  if (!props.teleport) return undefined
  const pos = menuPosition.value
  const style: CSSProperties = {
    position: 'fixed',
    minWidth: `${pos.minWidth}px`,
    maxWidth: `${pos.maxWidth}px`,
    width: 'max-content',
    zIndex: POPOVER_Z_INDEX,
  }

  if (pos.rtl) {
    style.right = `${pos.start}px`
  } else {
    style.left = `${pos.start}px`
  }

  if (pos.openUp) {
    style.bottom = `${window.innerHeight - pos.top}px`
  } else {
    style.top = `${pos.top}px`
  }

  return style
})

function enabledIndexes(): number[] {
  return filteredOptions.value
    .map((option, index) => (option.disabled ? -1 : index))
    .filter((index) => index >= 0)
}

function moveActive(delta: number): void {
  const indexes = enabledIndexes()
  if (!indexes.length) return
  const currentPos = indexes.indexOf(activeIndex.value)
  const nextPos =
    currentPos < 0 ? 0 : (currentPos + delta + indexes.length) % indexes.length
  activeIndex.value = indexes[nextPos]!
}

watch(open, async (isOpen) => {
  if (!isOpen) return
  const indexes = enabledIndexes()
  const selectedIndex = indexes.find((index) => isSelected(filteredOptions.value[index]!))
  activeIndex.value = selectedIndex ?? indexes[0] ?? 0
  await nextTick()
  syncMenuPosition()
  bindPositionListeners()
  root.value?.querySelector<HTMLInputElement>('[data-list-select-search]')?.focus()
})

let listenersBound = false
watch(open, (isOpen) => {
  if (isOpen && !listenersBound) {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onDocumentKeydown)
    listenersBound = true
  }
  if (!isOpen && listenersBound) {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onDocumentKeydown)
    listenersBound = false
  }
})

onUnmounted(() => {
  unbindPositionListeners()
  if (listenersBound) {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onDocumentKeydown)
  }
})
</script>

<template>
  <div ref="root" class="relative min-w-[11rem]">
    <div
      ref="triggerRef"
      class="flex w-full items-center justify-between gap-2 rounded-xl border px-2.5 font-semibold transition"
      :class="triggerClass"
      role="combobox"
      :tabindex="disabled ? -1 : 0"
      :aria-disabled="disabled || undefined"
      :aria-expanded="open"
      aria-haspopup="listbox"
      aria-multiselectable="true"
      @click="toggle"
      @keydown="onTriggerKeydown"
    >
      <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
        <template v-if="hasValue">
          <span
            v-for="chip in visibleChips"
            :key="valueKey(chip.value)"
            class="inline-flex max-w-full items-center gap-1 rounded-lg px-2 py-0.5 text-[11px] font-bold leading-5"
            :class="
              chip.tone === 'gold'
                ? 'bg-brand-gold-soft text-[#8A6A2E] ring-1 ring-brand-gold/35'
                : 'bg-brand-primary-soft text-brand-primary-dark'
            "
          >
            <span class="min-w-0 truncate">{{ chip.label }}</span>
            <button
              v-if="!disabled"
              type="button"
              class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full hover:bg-black/10"
              :aria-label="`إزالة ${chip.label}`"
              @click="removeChip(chip, $event)"
            >
              <X class="h-3 w-3" :stroke-width="2.5" />
            </button>
          </span>
          <span
            v-if="hiddenChipCount > 0"
            class="inline-flex items-center rounded-lg bg-brand-bg px-2 py-0.5 text-[11px] font-bold text-brand-text-secondary"
          >
            +{{ hiddenChipCount }}
          </span>
        </template>
        <span v-else class="truncate text-brand-text-muted">{{ placeholder }}</span>
      </div>
      <button
        v-if="clearable && hasValue && !disabled"
        type="button"
        class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-brand-text-muted hover:bg-brand-bg hover:text-brand-text"
        aria-label="مسح الكل"
        @click="clear"
      >
        <X class="h-3.5 w-3.5" :stroke-width="2.25" />
      </button>
      <ChevronDown
        class="h-4 w-4 shrink-0 text-brand-text-muted transition"
        :class="open ? 'rotate-180 text-brand-primary' : ''"
        :stroke-width="2.25"
      />
    </div>

    <Teleport to="body" :disabled="!teleport">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-1 scale-[0.98]"
      >
        <div
          v-if="open"
          data-app-select-menu
          data-app-list-select-menu
          class="overflow-hidden rounded-xl border border-brand-border bg-brand-surface shadow-xl ring-1 ring-black/5"
          :class="
            teleport
              ? ''
              : 'absolute start-0 z-[490] mt-1.5 min-w-full w-max max-w-[min(35rem,calc(100vw-1.5rem))]'
          "
          :style="menuStyle"
          role="listbox"
          aria-multiselectable="true"
        >
          <div v-if="searchable || remote" class="border-b border-brand-border p-2">
            <input
              v-model="search"
              data-list-select-search
              type="search"
              :placeholder="searchPlaceholder || 'بحث…'"
              class="h-9 w-full rounded-lg border border-brand-border bg-brand-bg px-3 text-sm text-brand-text outline-none focus:border-brand-primary/40 focus:ring-2 focus:ring-brand-primary/15"
              @click.stop
            />
          </div>

          <ul class="max-h-72 overflow-y-auto p-1.5">
            <li v-if="error" class="px-3 py-6 text-center text-xs font-semibold text-red-700">
              <p>تعذر تحميل الخيارات</p>
              <button type="button" class="mt-2 underline" data-list-select-retry @click="emit('retry')">
                إعادة المحاولة
              </button>
            </li>
            <li
              v-else-if="loading && !filteredOptions.length"
              class="px-3 py-6 text-center text-xs font-semibold text-brand-text-muted"
            >
              جارٍ التحميل…
            </li>
            <li
              v-else-if="!filteredOptions.length"
              class="px-3 py-6 text-center text-xs font-semibold text-brand-text-muted"
            >
              لا توجد نتائج
            </li>
            <li
              v-for="(option, index) in filteredOptions"
              :key="valueKey(option.value)"
              role="option"
              :aria-selected="isSelected(option)"
            >
              <button
                type="button"
                class="flex w-full items-start gap-2.5 rounded-lg px-3 py-2.5 text-start text-sm transition"
                :class="
                  option.disabled
                    ? 'cursor-not-allowed opacity-45'
                    : isSelected(option)
                      ? option.tone === 'gold'
                        ? 'bg-brand-gold-soft text-[#8A6A2E]'
                        : 'bg-brand-primary-soft text-brand-primary-dark'
                      : index === activeIndex
                        ? 'bg-brand-bg text-brand-text'
                        : 'text-brand-text hover:bg-brand-bg'
                "
                :disabled="option.disabled"
                @click="toggleOption(option)"
              >
                <span
                  class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border"
                  :class="
                    isSelected(option)
                      ? option.tone === 'gold'
                        ? 'border-brand-gold bg-brand-gold text-white'
                        : 'border-brand-primary bg-brand-primary text-white'
                      : 'border-brand-border bg-brand-surface'
                  "
                >
                  <Check v-if="isSelected(option)" class="h-3 w-3" :stroke-width="3" />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="flex flex-wrap items-center gap-2">
                    <span class="whitespace-normal break-words font-bold leading-snug">
                      {{ option.label }}
                    </span>
                    <span
                      v-if="option.badge"
                      class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold"
                      :class="
                        option.tone === 'gold'
                          ? 'bg-brand-gold-soft text-[#8A6A2E] ring-1 ring-brand-gold/35'
                          : 'bg-brand-bg text-brand-text-secondary'
                      "
                    >
                      {{ option.badge }}
                    </span>
                  </span>
                  <span
                    v-if="option.hint"
                    class="mt-0.5 block whitespace-normal break-words text-[11px] font-semibold leading-snug text-brand-text-muted"
                  >
                    {{ option.hint }}
                  </span>
                </span>
              </button>
            </li>
            <li v-if="remote && hasMore && !error" class="px-1 pt-1">
              <button
                type="button"
                class="w-full rounded-lg px-3 py-2 text-xs font-bold text-brand-primary-dark hover:bg-brand-primary-soft disabled:opacity-60"
                :disabled="loading"
                data-list-select-load-more
                @click="loadMore"
              >
                {{ loading ? 'جارٍ التحميل…' : 'تحميل المزيد' }}
              </button>
            </li>
          </ul>

          <div class="flex items-center justify-between gap-2 border-t border-brand-border px-3 py-2">
            <span class="text-[11px] font-semibold text-brand-text-muted">
              {{ selectedCount }} محدد
            </span>
            <button
              type="button"
              class="rounded-lg px-2.5 py-1 text-xs font-bold text-brand-primary-dark hover:bg-brand-primary-soft"
              data-list-select-done
              @click="close"
            >
              تم
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
