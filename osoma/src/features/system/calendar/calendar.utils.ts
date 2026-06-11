export const startOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate())

export const endOfDay = (date: Date) =>
  new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59, 59, 999)

export const addDays = (date: Date, amount: number) => {
  const next = new Date(date)
  next.setDate(next.getDate() + amount)
  return next
}

export const addMonths = (date: Date, amount: number) => {
  const next = new Date(date)
  next.setMonth(next.getMonth() + amount)
  return next
}

export const startOfWeek = (date: Date, weekStartsOn = 1) => {
  const day = date.getDay()
  const diff = (day < weekStartsOn ? 7 : 0) + day - weekStartsOn
  return addDays(startOfDay(date), -diff)
}

export const endOfWeek = (date: Date, weekStartsOn = 1) => {
  const start = startOfWeek(date, weekStartsOn)
  return endOfDay(addDays(start, 6))
}

export const startOfMonth = (date: Date) => new Date(date.getFullYear(), date.getMonth(), 1)

export const endOfMonth = (date: Date) => new Date(date.getFullYear(), date.getMonth() + 1, 0)

export const isSameMonth = (a: Date, b: Date) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth()

export const isSameDay = (a: Date, b: Date) =>
  a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()

export const getMonthGrid = (date: Date, weekStartsOn = 1) => {
  const start = startOfWeek(startOfMonth(date), weekStartsOn)
  const end = endOfWeek(endOfMonth(date), weekStartsOn)
  const weeks: Date[][] = []

  let current = start
  while (current <= end) {
    const week: Date[] = []
    for (let i = 0; i < 7; i += 1) {
      week.push(current)
      current = addDays(current, 1)
    }
    weeks.push(week)
  }

  return weeks
}
