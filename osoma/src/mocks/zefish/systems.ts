import type {
    RoomEnvironmentRecord,
    SensorRecord,
    ZefishLocation,
    ZefishRoom,
    ZefishSystem,
} from '@/domain/zefish/zefish.types.ts'

const now = new Date()
const minutesAgo = (minutes: number) => new Date(now.getTime() - minutes * 60_000).toISOString()

export const zefishFacilityName = 'Facility A'

export const zefishRooms: ZefishRoom[] = [
    {
        roomID: 'ROOM-201',
        roomName: 'Room 201',
        facility: zefishFacilityName,
        lightCycle: '14h light / 10h dark',
    },
    {
        roomID: 'ROOM-203',
        roomName: 'Room 203',
        facility: zefishFacilityName,
        lightCycle: '14h light / 10h dark',
    },
    {
        roomID: 'ROOM-205',
        roomName: 'Room 205',
        facility: zefishFacilityName,
        lightCycle: '12h light / 12h dark',
    },
]

export const zefishSystems: ZefishSystem[] = [
    {
        systemID: 'SYS-01',
        manufacturer: 'Tecniplast',
        model: 'ZebTEC Active Blue',
        roomID: 'ROOM-201',
        roomName: 'Room 201',
        waterTemperature: 28.2,
        pH: 7.2,
        conductivity: 520,
        oxygen: 7.4,
        waterFlow: 3.2,
        filtrationStatus: 'OK',
    },
    {
        systemID: 'SYS-02',
        manufacturer: 'Pentair Aquatic Eco-Systems',
        model: 'ZFS-400',
        roomID: 'ROOM-203',
        roomName: 'Room 203',
        waterTemperature: 30.4,
        pH: 6.8,
        conductivity: 540,
        oxygen: 6.9,
        waterFlow: 2.8,
        filtrationStatus: 'OK',
    },
    {
        systemID: 'SYS-03',
        manufacturer: 'AquaMedic',
        model: 'LifeBreeder Pro',
        roomID: 'ROOM-203',
        roomName: 'Room 203',
        waterTemperature: 28,
        pH: 7,
        conductivity: 510,
        oxygen: 7.1,
        waterFlow: 3,
        filtrationStatus: 'OK',
    },
    {
        systemID: 'SYS-04',
        manufacturer: 'Tecniplast',
        model: 'ZebTEC Multilinking',
        roomID: 'ROOM-205',
        roomName: 'Room 205',
        waterTemperature: 27.8,
        pH: 7.3,
        conductivity: 505,
        oxygen: 7.6,
        waterFlow: 3.4,
        filtrationStatus: 'OK',
    },
    {
        systemID: 'SYS-05',
        manufacturer: 'AquaSchwarz',
        model: 'ZB-Flow 8',
        roomID: 'ROOM-205',
        roomName: 'Room 205',
        waterTemperature: 26.4,
        pH: 7.6,
        conductivity: 498,
        oxygen: 7.8,
        waterFlow: 2,
        filtrationStatus: 'MAINTENANCE',
    },
]

const tankCodes = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3']

export const zefishLocations: ZefishLocation[] = zefishSystems.flatMap((system) =>
    Array.from({ length: 4 }).flatMap((_, rackIndex) =>
        tankCodes.map((tankCode) => ({
            locationId: `LOC-${system.systemID}-R${rackIndex + 1}-${tankCode}`,
            facility: zefishFacilityName,
            roomID: system.roomID,
            roomName: system.roomName,
            systemID: system.systemID,
            rack: `Rack ${rackIndex + 1}`,
            tank: tankCode,
        }))
    )
)

export const sensorRecords: SensorRecord[] = zefishSystems.flatMap((system, systemIndex) =>
    Array.from({ length: 48 }).map((_, index) => {
        const drift = system.systemID === 'SYS-02' ? Math.max(0, index - 34) * 0.09 : 0
        const baselineTemp = 27.9 + Math.sin((index + systemIndex) / 4) * 0.25
        const timestamp = minutesAgo((47 - index) * 30)

        return {
            recordID: `SREC-${system.systemID}-${index + 1}`,
            systemID: system.systemID,
            timestamp,
            waterTemperature: Number((baselineTemp + drift).toFixed(2)),
            pH: Number((7.1 + Math.cos((index + systemIndex) / 6) * 0.14 - drift * 0.04).toFixed(2)),
            conductivity: Number((512 + Math.sin(index / 5) * 16 + systemIndex * 5).toFixed(1)),
            oxygen: Number((7.3 - drift * 0.03 + Math.cos(index / 7) * 0.18).toFixed(2)),
            waterFlow: Number((3 + Math.sin(index / 8) * 0.25 - drift * 0.06).toFixed(2)),
            filtrationStatus: system.systemID === 'SYS-05' && index > 36 ? 'MAINTENANCE' : 'OK',
        }
    })
)

export const roomEnvironmentRecords: RoomEnvironmentRecord[] = zefishRooms.flatMap((room, roomIndex) =>
    Array.from({ length: 36 }).map((_, index) => ({
        recordID: `RREC-${room.roomID}-${index + 1}`,
        roomID: room.roomID,
        timestamp: minutesAgo((35 - index) * 40),
        roomTemperature: Number((24.5 + roomIndex * 0.4 + Math.sin(index / 4) * 0.6).toFixed(2)),
        humidity: Number((49 + roomIndex * 3 + Math.cos(index / 6) * 4).toFixed(2)),
        lightCycle: room.lightCycle,
        noiseLevel: Number((47 + roomIndex * 2 + Math.sin(index / 5) * 3.2).toFixed(2)),
    }))
)