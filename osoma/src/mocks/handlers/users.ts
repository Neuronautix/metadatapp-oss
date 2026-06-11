import { http, HttpResponse } from 'msw'
import { users } from '@/mocks/data/users.ts'
import type { User } from '@/domain/resources.ts'
import { matches, paginate, withLatency } from './utils.ts'

export const usersHandlers = [
  http.get('/api/users', async ({ request }) => {
    await withLatency()
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('pageSize') ?? 7)
    const search = url.searchParams.get('search')
    const status = url.searchParams.get('status')
    const accessLevel = url.searchParams.get('accessLevel')
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'User directory is degraded.' }, { status: 503 })
    }

    let filtered = [...users]

    if (search) {
      filtered = filtered.filter(
        (user) => matches(user.name, search) || matches(user.role, search) || matches(user.lab, search)
      )
    }

    if (status) {
      filtered = filtered.filter((user) => user.status === status)
    }

    if (accessLevel) {
      filtered = filtered.filter((user) => user.accessLevel === accessLevel)
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({
      'hydra:member': data,
      'hydra:totalItems': total,
      'hydra:view': {
        '@id': `/users?page=${page}&pageSize=${pageSize}`,
        'hydra:first': `/users?page=1&pageSize=${pageSize}`,
        'hydra:last': `/users?page=${Math.ceil(total / pageSize)}&pageSize=${pageSize}`,
        'hydra:next': page < Math.ceil(total / pageSize) ? `/users?page=${page + 1}&pageSize=${pageSize}` : undefined,
      },
    })
  }),

  http.get('/api/users/:userId', async ({ params, request }) => {
    await withLatency()
    const url = new URL(request.url)
    const error = url.searchParams.get('error')

    if (error === 'true') {
      return HttpResponse.json({ message: 'User record unavailable.' }, { status: 500 })
    }

    const user = users.find((record) => record.id === params.userId)
    if (!user) {
      return HttpResponse.json({ message: 'User not found.' }, { status: 404 })
    }

    return HttpResponse.json(user)
  }),

  http.patch('/api/users/:userId', async ({ request, params }) => {
    await withLatency()
    const payload = (await request.json()) as Partial<User>
    const index = users.findIndex((record) => record.id === params.userId)

    if (index === -1) {
      return HttpResponse.json({ message: 'User not found.' }, { status: 404 })
    }

    users[index] = {
      ...users[index],
      ...payload,
      lastActive: new Date().toISOString(),
    }

    return HttpResponse.json(users[index])
  }),

  http.post('/api/users', async ({ request }) => {
    await withLatency()
    const payload = (await request.json()) as Partial<User>

    const nextId = `USR-${1000 + users.length + 1}`
    const user: User = {
      id: nextId,
      name: payload.name ?? 'New User',
      email: payload.email,
      role: payload.role ?? 'Research Operator',
      lab: payload.lab ?? 'General',
      accessLevel: payload.accessLevel ?? 'viewer',
      accessAreas: payload.accessAreas,
      status: payload.status ?? 'invited',
      lastActive: new Date().toISOString(),
    }

    users.unshift(user)

    return HttpResponse.json(user, { status: 201 })
  }),
]
