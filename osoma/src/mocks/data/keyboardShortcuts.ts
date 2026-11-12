import type { KeyboardShortcut } from '@/features/core/investigations/investigation.types.ts'

export const keyboardShortcuts: KeyboardShortcut[] = [
  { id: 'kb-1', keys: '↑ / ↓', label: 'Move roster focus' },
  { id: 'kb-2', keys: 'Enter', label: 'Open focused animal' },
  { id: 'kb-3', keys: 'f', label: 'Toggle favorite' },
]
