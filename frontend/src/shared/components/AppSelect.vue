<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch, type CSSProperties } from 'vue'
import { Check, ChevronDown, X } from 'lucide-vue-next'

export interface AppSelectOption {
  value: string | number
  label: string
  hint?: string
  disabled?: boolean
}

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    options?: AppSelectOption[]
    placeholder?: string
    disabled?: boolean
    searchable?: boolean
    searchPlaceholder?: string
    remote?: boolean
    loading?: boolean
    error?: boolean
    hasMore?: boolean
    selectedOption?: AppSelectOption | null
    clearable?: boolean
    size?: 'sm' | 'md'
    teleport?: boolean
  }>(),
  {
    modelValue: null,
    options: () => [],
    placeholder: 'اختر…',
    disabled: false,
    searchable: false,
    searchPlaceholder: 'بحث…',
    remote: false,
    loading: false,
    error: false,
    hasMore: false,
    selectedOption: null,
    clearable: false,
    size: 'md',
    teleport: true,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null]
  search: [value: string]
  'load-more': []
  retry: []
}>()

const root = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLButtonElement | null>(null)
const open = ref(false)
const search = ref('')
const remembered = ref<AppSelectOption | null>(null)
const activeIndex = ref(-1)
const menuPosition = ref({
  top: 0,
  start: 0,
  minWidth: 0,
  maxWidth: 560,
  openUp: false,
  rtl: false,
})

function matchesValue(option: AppSelectOption | null | undefined): boolean {
  return option != null && String(option.value) === String(props.modelValue)
}

const selected = computed(() => {
  const fromOptions = props.options.find((option) => matchesValue(option))
  if (fromOptions) return fromOptions
  if (matchesValue(props.selectedOption)) return props.selectedOption ?? null
  if (matchesValue(remembered.value)) return remembered.value
  return null
})

const filteredOptions = computed(() => {
  const query = search.value.trim().toLowerCase()
  const source = props.remote
    ? props.options
    : !props.searchable || !query
      ? props.options
      : props.options.filter((option) => {
          const label = String(option.label ?? '').toLowerCase()
          const hint = String(option.hint ?? '').toLowerCase()
          return label.includes(query) || hint.includes(query)
        })

  const current = selected.value
  if (!current || source.some((option) => String(option.value) === String(current.value))) {
    return source
  }
  return [current, ...source]
})

watch(search, (value) => {
  if (props.remote && open.value) emit('search', String(value).trim())
})

watch(
  () => props.modelValue,
  (value) => {
    if (value === '' || value == null) remembered.value = null
  },
)

const displayLabel = computed(() => selected.value?.label ?? props.placeholder)
const hasValue = computed(() => selected.value != null)

const triggerClass = computed(() => {
  const base = props.size === 'sm' ? 'h-9 text-xs' : 'h-11 text-sm'
  if (props.disabled) {
    return `${base} cursor-not-allowed border-brand-border bg-brand-bg text-brand-text-muted opacity-70`
  }
  if (open.value) {
    return `${base} border-brand-primary/50 bg-brand-surface text-brand-text ring-2 ring-brand-primary/15`
  }
  return `${base} border-brand-border bg-brand-surface text-brand-text hover:border-brand-primary/30`
})

function syncMenuPosition(): void {
  const el = triggerRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const viewportH = window.innerHeight
  const viewportW = window.innerWidth
  const menuMaxH = 320
  const spaceBelow = viewportH - rect.bottom
  const openUp = spaceBelow < menuMaxH && rect.top > spaceBelow
  const rtl = getComputedStyle(el).direction === 'rtl'

  // Anchor to the trigger's start edge and let the menu grow toward the viewport.
  const available = rtl ? rect.right - 12 : viewportW - rect.left - 12
  const maxWidth = Math.min(560, Math.max(available, 160))
  const minWidth = Math.min(Math.max(rect.width, 160), maxWidth)

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

function pick(option: AppSelectOption): void {
  if (option.disabled) return
  remembered.value = option
  emit('update:modelValue', option.value)
  close()
}

function clear(event: MouseEvent): void {
  event.preventDefault()
  event.stopPropagation()
  if (props.disabled || !props.clearable) return
  remembered.value = null
  emit('update:modelValue', null)
}

function loadMore(): void {
  if (props.remote && props.hasMore && !props.loading) emit('load-more')
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target as HTMLElement | null
  if (!root.value?.contains(target) && !target?.closest?.('[data-app-select-menu]')) {
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

  const list = filteredOptions.value.filter((option) => !option.disabled)
  if (!list.length) return

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeIndex.value = (activeIndex.value + 1) % list.length
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeIndex.value = (activeIndex.value - 1 + list.length) % list.length
  } else if (event.key === 'Enter' && activeIndex.value >= 0) {
    event.preventDefault()
    pick(list[activeIndex.value]!)
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
    zIndex: 250,
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

watch(open, async (isOpen) => {
  if (!isOpen) return
  activeIndex.value = Math.max(
    0,
    filteredOptions.value.findIndex(
      (option) => String(option.value) === String(props.modelValue) && !option.disabled,
    ),
  )
  await nextTick()
  syncMenuPosition()
  bindPositionListeners()
  root.value?.querySelector<HTMLInputElement>('[data-select-search]')?.focus()
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
    <button
      ref="triggerRef"
      type="button"
      class="flex w-full items-center justify-between gap-2 rounded-xl border px-3 font-semibold transition"
      :class="triggerClass"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="toggle"
    >
      <span
        class="min-w-0 truncate text-start"
        :class="hasValue ? 'text-brand-text' : 'text-brand-text-muted'"
      >
        {{ displayLabel }}
      </span>
      <button
        v-if="clearable && hasValue && !disabled"
        type="button"
        class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-brand-text-muted hover:bg-brand-bg hover:text-brand-text"
        aria-label="مسح الاختيار"
        @click="clear"
      >
        <X class="h-3.5 w-3.5" :stroke-width="2.25" />
      </button>
      <ChevronDown
        class="h-4 w-4 shrink-0 text-brand-text-muted transition"
        :class="open ? 'rotate-180 text-brand-primary' : ''"
        :stroke-width="2.25"
      />
    </button>

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
          class="overflow-hidden rounded-xl border border-brand-border bg-brand-surface shadow-xl ring-1 ring-black/5"
          :class="teleport ? '' : 'absolute start-0 z-[250] mt-1.5 min-w-full w-max max-w-[min(35rem,calc(100vw-1.5rem))]'"
          :style="menuStyle"
          role="listbox"
        >
          <div v-if="searchable || remote" class="border-b border-brand-border p-2">
            <input
              v-model="search"
              data-select-search
              type="search"
              :placeholder="searchPlaceholder"
              class="h-9 w-full rounded-lg border border-brand-border bg-brand-bg px-3 text-sm text-brand-text outline-none focus:border-brand-primary/40 focus:ring-2 focus:ring-brand-primary/15"
              @click.stop
            />
          </div>

          <ul class="max-h-72 overflow-y-auto p-1.5">
            <li v-if="error" class="px-3 py-6 text-center text-xs font-semibold text-red-700">
              <p>تعذر تحميل الخيارات</p>
              <button type="button" class="mt-2 underline" data-select-retry @click="emit('retry')">إعادة المحاولة</button>
            </li>
            <li v-else-if="loading && !filteredOptions.length" class="px-3 py-6 text-center text-xs font-semibold text-brand-text-muted">
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
              :key="String(option.value)"
              role="option"
              :aria-selected="String(option.value) === String(modelValue)"
            >
              <button
                type="button"
                class="flex w-full items-start gap-2.5 rounded-lg px-3 py-2.5 text-start text-sm transition"
                :class="
                  option.disabled
                    ? 'cursor-not-allowed opacity-45'
                    : String(option.value) === String(modelValue)
                      ? 'bg-brand-primary-soft text-brand-primary-dark'
                      : index === activeIndex
                        ? 'bg-brand-bg text-brand-text'
                        : 'text-brand-text hover:bg-brand-bg'
                "
                :disabled="option.disabled"
                @click="pick(option)"
              >
                <span
                  class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border"
                  :class="
                    String(option.value) === String(modelValue)
                      ? 'border-brand-primary bg-brand-primary text-white'
                      : 'border-brand-border bg-brand-surface'
                  "
                >
                  <Check
                    v-if="String(option.value) === String(modelValue)"
                    class="h-3 w-3"
                    :stroke-width="3"
                  />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block whitespace-normal break-words font-bold leading-snug">
                    {{ option.label }}
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
                data-select-load-more
                @click="loadMore"
              >
                {{ loading ? 'جارٍ التحميل…' : 'تحميل المزيد' }}
              </button>
            </li>
          </ul>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
