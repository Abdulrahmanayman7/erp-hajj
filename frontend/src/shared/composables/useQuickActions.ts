import { computed, type ComputedRef } from 'vue'
import {
  Archive,
  CalendarDays,
  Gavel,
  IdCard,
  ListTodo,
  type LucideIcon,
} from 'lucide-vue-next'

import { usePermissions } from '@/shared/composables/usePermissions'

export interface QuickActionItem {
  key: string
  labelKey: string
  icon: LucideIcon
  /** In-app route to open create UX (drawer pages open via query or dedicated path). */
  to: string
  permission: string
}

/**
 * Global quick-create actions for mobile (+) — permission-gated only.
 */
export function useQuickActions(): {
  actions: ComputedRef<QuickActionItem[]>
} {
  const { can } = usePermissions()

  const actions = computed(() => {
    const items: QuickActionItem[] = []

    if (can('tasks.create')) {
      items.push({
        key: 'task',
        labelKey: 'quickActions.addTask',
        icon: ListTodo,
        to: '/app/tasks?create=1',
        permission: 'tasks.create',
      })
    }
    if (can('meetings.create')) {
      items.push({
        key: 'meeting',
        labelKey: 'quickActions.addMeeting',
        icon: CalendarDays,
        to: '/app/meetings?create=1',
        permission: 'meetings.create',
      })
    }
    if (can('decisions.create')) {
      items.push({
        key: 'decision',
        labelKey: 'quickActions.addDecision',
        icon: Gavel,
        to: '/app/decisions?create=1',
        permission: 'decisions.create',
      })
    }
    if (can('employees.create')) {
      items.push({
        key: 'employee',
        labelKey: 'quickActions.addEmployee',
        icon: IdCard,
        to: '/app/employees?create=1',
        permission: 'employees.create',
      })
    }
    if (can('documents.create')) {
      items.push({
        key: 'document',
        labelKey: 'quickActions.uploadDocument',
        icon: Archive,
        to: '/app/documents?create=1',
        permission: 'documents.create',
      })
    }

    return items
  })

  return { actions }
}
