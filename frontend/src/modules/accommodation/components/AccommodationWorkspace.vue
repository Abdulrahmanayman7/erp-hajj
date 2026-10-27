<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Building2, BedDouble, CalendarCheck2, ShieldCheck } from 'lucide-vue-next'

const route = useRoute()
const { t } = useI18n()
const section = computed(() => route.path.includes('/drafts') ? 'stays' : route.path.includes('/allocation') ? 'setup' : route.path.includes('/scopes') ? 'team' : route.path.includes('/facilities/') ? 'details' : 'places')
const icons = { places: Building2, details: Building2, setup: CalendarCheck2, stays: BedDouble, team: ShieldCheck }
</script>

<template>
  <section class="accommodation-intro">
    <div class="accommodation-intro-icon"><component :is="icons[section]" :size="26" :stroke-width="1.6" /></div>
    <div class="min-w-0 flex-1">
      <p class="mb-1 text-xs font-semibold tracking-wide text-brand-primary">{{ t('accommodation.experience.eyebrow') }}</p>
      <h2 class="text-lg font-bold sm:text-xl">{{ t(`accommodation.experience.${section}.title`) }}</h2>
      <p class="mt-2 line-clamp-2 max-w-3xl text-sm leading-7 text-brand-text-secondary sm:line-clamp-none">{{ t(`accommodation.experience.${section}.hint`) }}</p>
    </div>
    <span class="accommodation-intro-tag">{{ t('accommodation.experience.seasonWorkspace') }}</span>
  </section>
</template>

<style>
.accommodation-page { --accommodation-accent: var(--color-brand-primary); }
.accommodation-intro { display: flex; align-items: center; gap: 1rem; padding: 1.5rem; border: 1px solid var(--color-brand-border); border-radius: 1.25rem; background: linear-gradient(115deg, var(--color-brand-surface) 55%, var(--color-brand-primary-soft)); }
.accommodation-intro-icon { display: grid; place-items: center; flex-shrink: 0; width: 3.5rem; height: 3.5rem; background: var(--color-brand-primary-soft); color: var(--color-brand-primary); border-radius: 1rem; }
.accommodation-intro-tag { padding: .5rem .85rem; border: 1px solid var(--color-brand-border); border-radius: 999px; font-size: .7rem; color: var(--color-brand-primary); background: var(--color-brand-surface); white-space: nowrap; }
.accommodation-stats { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: .85rem; }
.accommodation-stat { border: 1px solid var(--color-brand-border); background: var(--color-brand-surface); border-radius: 1rem; padding: 1rem 1.25rem; }
.accommodation-stat strong { display: block; margin-top: .5rem; font-size: 1.65rem; line-height: 1.3; font-variant-numeric: tabular-nums; }
.accommodation-stat span { color: var(--color-brand-text-secondary); font-size: .8rem; }
.accommodation-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; padding: 1rem; border: 1px solid var(--color-brand-border); border-radius: 1rem; background: var(--color-brand-surface); }
.accommodation-toolbar input { min-height: 2.75rem; border: 1px solid var(--color-brand-border); border-radius: .75rem; padding: .5rem .9rem; background: var(--color-brand-surface); }
.accommodation-table-surface { background: var(--color-brand-surface); box-shadow: 0 1px 2px rgba(23,32,29,.03); }
.accommodation-page table { width: 100%; border-collapse: separate; border-spacing: 0; }
.accommodation-page table thead th { padding: .875rem 1.25rem; border-bottom: 1px solid var(--color-brand-border); background: #F4F6F5; color: var(--color-brand-text); text-align: center; font-size: .75rem; font-weight: 700; letter-spacing: .025em; white-space: nowrap; }
.accommodation-page table tbody > tr:nth-child(even) { background: #FAFBFA; }
.accommodation-page table tbody > tr:nth-child(odd) { background: var(--color-brand-surface); }
.accommodation-page table tbody td { padding: .875rem 1.25rem; border-bottom: 1px solid var(--color-brand-border); text-align: center; vertical-align: middle; transition: background-color .15s; }
.accommodation-page table tbody tr:last-child > td { border-bottom: 0; }
.accommodation-page table tbody td:first-child { border-inline-start: 3px solid transparent; }
.accommodation-page table tbody tr:hover > td { background: #EDF6F1; }
.accommodation-page table tbody tr:hover > td:first-child { border-inline-start-color: var(--color-brand-primary); }
.accommodation-page table tbody td:last-child > div.flex { justify-content: center; }
.accommodation-page table tbody td:last-child button { display: inline-flex; min-height: 2.25rem; min-width: 2.25rem; align-items: center; justify-content: center; gap: .375rem; border-radius: .5rem; padding: .375rem .625rem; border: 1px solid var(--color-brand-border); background: var(--color-brand-surface); color: var(--color-brand-primary); }
.accommodation-page table tbody td:last-child button.text-red-700, .accommodation-page table tbody td:last-child button.text-red-800 { color: var(--color-brand-danger); }
.accommodation-page table tbody td:last-child button:hover { border-color: var(--color-brand-primary); }
.accommodation-page button { cursor: pointer; }
.accommodation-page button:disabled { cursor: not-allowed; }
.accommodation-page button:focus-visible, .accommodation-page a:focus-visible, .accommodation-page input:focus-visible { outline: 2px solid var(--color-brand-primary); outline-offset: 3px; }
.accommodation-drawer form, .accommodation-drawer form > div { min-height: 0; }
.accommodation-drawer header { background: var(--color-brand-surface); }
.accommodation-drawer footer { background: var(--color-brand-surface); }
.accommodation-form-step { display: flex; align-items: center; gap: .6rem; padding-top: .75rem; color: var(--color-brand-primary); font-size: .85rem; font-weight: 700; }
.accommodation-form-step span { display: grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: .5rem; background: var(--color-brand-primary-soft); }
@media(max-width:640px) { .accommodation-intro { align-items: flex-start; padding: 1.1rem; } .accommodation-intro-tag { display: none; } .accommodation-stats { gap: .5rem; } .accommodation-stat { padding: .8rem; } .accommodation-stat strong { font-size: 1.3rem; } .accommodation-stat span { font-size: .7rem; } }
@media(max-width:767px) {
  .accommodation-page .accommodation-stays-table { display: block; min-width: 0; }
  .accommodation-stays-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
  .accommodation-stays-table tbody { display: block; padding: .75rem; }
  .accommodation-stays-table tbody tr { display: block; border: 1px solid var(--color-brand-border); border-radius: 1rem; margin-bottom: .75rem; padding-block: .4rem; }
  .accommodation-page .accommodation-stays-table td { display: flex; flex-wrap: wrap; align-items: start; gap: .5rem; padding: .6rem .85rem; overflow-wrap: anywhere; }
  .accommodation-stays-table td[data-label]::before { content: attr(data-label); display: block; width: 100%; font-size: .7rem; font-weight: 500; color: var(--color-brand-text-secondary); }
  .accommodation-stays-table td:last-child { border-top: 1px solid var(--color-brand-border); margin-top: .5rem; padding-top: 1rem; }
  .accommodation-stays-table td:last-child button { min-height: 2.5rem; padding-inline: .7rem; }
}
@media(prefers-reduced-motion:reduce) { .accommodation-page * { transition: none !important; } }
</style>
