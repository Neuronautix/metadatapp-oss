import type { TaskState } from './dvc.integration.api.ts'

export const findTaskStateById = (states: TaskState[], taskId: number) => {
  return states.find((state) => state.id === taskId) ?? states[0] ?? null
}
