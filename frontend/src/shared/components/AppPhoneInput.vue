<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, useAttrs, watch, type CSSProperties } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronDown, Search } from 'lucide-vue-next'

import { POPOVER_Z_INDEX } from '@/shared/ui/overlayZ'
import {
  countryByIso,
  filterPhoneCountries,
  flagEmoji,
  formatE164,
  parsePhone,
  toAsciiDigits,
  type PhoneCountry,
} from '@/shared/utils/phoneInput'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    disabled?: boolean
    invalid?: boolean
    placeholder?: string
    id?: string
    name?: string
    autocomplete?: string
  }>(),
  {
    modelValue: '',
    disabled: false,
    invalid: false,
    placeholder: '5XXXXXXXX',
    id: undefined,
    name: undefined,
    autocomplete: 'tel-national',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const { t } = useI18n()
const attrs = useAttrs()
const root = ref<HTMLElement | null>(null)
const nationalRef = ref<HTMLInputElement | null>(null)
const searchRef = ref<HTMLInputElement | null>(null)
const open = ref(false)
const search = ref('')
const focused = ref(false)

const parsed = computed(() => parsePhone(props.modelValue))
const country = computed(() => countryByIso(parsed.value.iso))
const countries = computed(() => filterPhoneCountries(search.value))

const menuStyle = computed((): CSSProperties => {
  const el = root.value
  if (!el) return { display: 'none' }
  const rect = el.getBoundingClientRect()
  const width = Math.min(Math.max(rect.width, 280), window.innerWidth - 24)
  const left = Math.min(Math.max(12, rect.left), window.innerWidth - width - 12)
  return {
    top: `${rect.bottom + 6}px`,
    left: `${left}px`,
    width: `${width}px`,
    zIndex: POPOVER_Z_INDEX,
  }
})

const frameClass = computed(() => {
  if (props.disabled) {
    return 'border-brand-border bg-brand-bg opacity-70'
  }
  if (props.invalid) {
    return 'border-rose-500 bg-brand-surface ring-2 ring-rose-200'
  }
  if (open.value || focused.value) {
    return 'border-brand-primary/50 bg-brand-surface ring-2 ring-brand-primary/15'
  }
  return 'border-brand-border bg-brand-surface hover:border-brand-primary/30'
})

function emitValue(iso: string, national: string): void {
  emit('update:modelValue', formatE164(iso, national))
}

function onNationalInput(event: Event): void {
  const next = (event.target as HTMLInputElement).value
  if (next.includes('+') || next.startsWith('00') || /^0\d/.test(toAsciiDigits(next))) {
    const parsedNext = parsePhone(next, country.value.iso)
    emit('update:modelValue', formatE164(parsedNext.iso, parsedNext.national))
    return
  }
  emitValue(country.value.iso, next)
}

function pickCountry(row: PhoneCountry): void {
  emitValue(row.iso, parsed.value.national)
  open.value = false
  search.value = ''
  void nextTick(() => nationalRef.value?.focus())
}

function toggleOpen(): void {
  if (props.disabled) return
  open.value = !open.value
}

function close(): void {
  open.value = false
  search.value = ''
}

function onDocumentPointer(event: PointerEvent): void {
  const target = event.target
  if (!(target instanceof Node)) return
  if (root.value?.contains(target)) return
  if (target instanceof Element && target.closest('[data-app-phone-menu]')) return
  close()
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (!open.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    nationalRef.value?.focus()
  }
}

function focus(): void {
  nationalRef.value?.focus()
}

watch(open, async (isOpen) => {
  if (!isOpen) return
  await nextTick()
  searchRef.value?.focus()
})

let bound = false
watch(open, (isOpen) => {
  if (isOpen && !bound) {
    document.addEventListener('pointerdown', onDocumentPointer)
    document.addEventListener('keydown', onDocumentKeydown)
    bound = true
  }
  if (!isOpen && bound) {
    document.removeEventListener('pointerdown', onDocumentPointer)
    document.removeEventListener('keydown', onDocumentKeydown)
    bound = false
  }
})

onUnmounted(() => {
  if (!bound) return
  document.removeEventListener('pointerdown', onDocumentPointer)
  document.removeEventListener('keydown', onDocumentKeydown)
})

defineExpose({ focus })
</script>

<template>
  <div ref="root" class="relative w-full" :class="attrs.class" dir="ltr">
    <input v-if="name" type="hidden" :name="name" :value="modelValue ?? ''" />
    <div class="flex h-11 overflow-hidden rounded-xl border transition" :class="frameClass">
      <button
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 border-e border-brand-border bg-brand-bg/70 px-2.5 text-sm font-semibold text-brand-text transition hover:bg-brand-primary-soft disabled:cursor-not-allowed"
        :disabled="disabled"
        :aria-expanded="open"
        :aria-label="t('phoneInput.country')"
        @click="toggleOpen"
      >
        <span class="text-base leading-none" aria-hidden="true">{{ flagEmoji(country.iso) }}</span>
        <span dir="ltr">+{{ country.dial }}</span>
        <ChevronDown class="h-3.5 w-3.5 text-brand-text-muted" :stroke-width="2.25" />
      </button>
      <input
        :id="id"
        ref="nationalRef"
        :value="parsed.national"
        type="tel"
        inputmode="tel"
        :autocomplete="autocomplete"
        :placeholder="placeholder"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :aria-label="t('phoneInput.national')"
        class="min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-brand-text outline-none placeholder:text-brand-text-muted"
        @focus="focused = true"
        @blur="focused = false"
        @input="onNationalInput"
      />
    </div>

    <Teleport to="body">
      <div
        v-if="open"
        data-app-phone-menu
        class="fixed w-[min(20rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-brand-border bg-brand-surface shadow-xl ring-1 ring-black/5"
        :style="menuStyle"
        role="listbox"
        :aria-label="t('phoneInput.country')"
      >
        <div class="border-b border-brand-border p-2">
          <label class="relative block">
            <Search
              class="pointer-events-none absolute start-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-brand-text-muted"
            />
            <input
              ref="searchRef"
              v-model="search"
              type="search"
              class="h-9 w-full rounded-lg border border-brand-border bg-brand-bg pe-2.5 ps-8 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('phoneInput.search')"
            />
          </label>
        </div>
        <ul class="max-h-64 overflow-y-auto py-1" dir="rtl">
          <li v-if="countries.length === 0" class="px-3 py-2 text-sm text-brand-text-muted">
            {{ t('phoneInput.empty') }}
          </li>
          <li v-for="row in countries" :key="row.iso">
            <button
              type="button"
              class="flex w-full items-center gap-2 px-3 py-2 text-sm transition hover:bg-brand-primary-soft"
              :class="row.iso === country.iso ? 'bg-brand-primary-soft font-bold text-brand-primary-dark' : 'text-brand-text'"
              @click="pickCountry(row)"
            >
              <span class="text-base" aria-hidden="true">{{ flagEmoji(row.iso) }}</span>
              <span class="min-w-0 flex-1 truncate text-start">{{ row.name }}</span>
              <span class="shrink-0 font-semibold tabular-nums" dir="ltr">+{{ row.dial }}</span>
            </button>
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
