import Papa from 'papaparse'

// Define the shape of our row data
export interface ParsedMetricData {
    timestamp: string
    rawTime: number
    cage: string
    group?: string
    [metric: string]: any
}

// Internal State
let workerData: ParsedMetricData[] = []
let workerMetrics: string[] = []
let workerCages: string[] = []

export type WorkerMessage =
    | { type: 'PARSE_FILE', file: File }
    | { type: 'GET_DATA_INFO' }
    | { type: 'GET_BINNED_DATA', binning: 'raw' | '15m' | '1h' | '1d', metrics: string[] }
    | { type: 'SET_CUSTOM_GROUPS', customGroups: Record<string, string> }
    | { type: 'GET_GROUP_STATISTICS', binning: 'raw' | '15m' | '1h' | '1d', metric: string }
    | { type: 'GET_PCA', binning: 'raw' | '15m' | '1h' | '1d', metric: string }
    | { type: 'GET_COSINOR', binning: 'raw' | '15m' | '1h' | '1d', metric: string }
    | { type: 'GET_PERIODOGRAM', binning: 'raw' | '15m' | '1h' | '1d', metric: string }
    | { type: 'GET_CORRELATION', binning: 'raw' | '15m' | '1h' | '1d', metric1: string, metric2: string }
    | { type: 'GET_ANOVA', binning: 'raw' | '15m' | '1h' | '1d', metric: string }
    | { type: 'GET_CIRCADIAN', metric: string, maxDays: number | null, virtualControlCages: string[] }

export type WorkerResponse =
    | { type: 'PARSE_SUCCESS', metrics: string[], cages: string[], rowCount: number }
    | { type: 'PARSE_ERROR', error: string }
    | { type: 'DATA_INFO', metrics: string[], cages: string[], rowCount: number }
    | { type: 'BINNED_DATA', data: ParsedMetricData[] }
    | { type: 'GROUPS_UPDATED' }
    | { type: 'GROUP_STATISTICS_DATA', data: any }
    | { type: 'PCA_DATA', data: any }
    | { type: 'COSINOR_DATA', data: any }
    | { type: 'PERIODOGRAM_DATA', data: any }
    | { type: 'CORRELATION_DATA', data: any }
    | { type: 'ANOVA_DATA', data: any }
    | { type: 'CIRCADIAN_DATA', data: any }

self.onmessage = async (e: MessageEvent<WorkerMessage>) => {
    const msg = e.data

    switch (msg.type) {
        case 'PARSE_FILE':
            try {
                await parseFile(msg.file)
                self.postMessage({
                    type: 'PARSE_SUCCESS',
                    metrics: workerMetrics,
                    cages: workerCages,
                    rowCount: workerData.length
                } as WorkerResponse)
            } catch (err: any) {
                self.postMessage({ type: 'PARSE_ERROR', error: err.message } as WorkerResponse)
            }
            break

        case 'GET_DATA_INFO':
            self.postMessage({
                type: 'DATA_INFO',
                metrics: workerMetrics,
                cages: workerCages,
                rowCount: workerData.length
            } as WorkerResponse)
            break

        case 'GET_BINNED_DATA':
            const binned = getBinnedData(msg.binning, msg.metrics)
            self.postMessage({
                type: 'BINNED_DATA',
                data: binned
            } as WorkerResponse)
            break

        case 'SET_CUSTOM_GROUPS':
            applyCustomGroups(msg.customGroups)
            self.postMessage({ type: 'GROUPS_UPDATED' } as WorkerResponse)
            break

        case 'GET_GROUP_STATISTICS':
            const stats = getGroupStatistics(msg.binning, msg.metric)
            self.postMessage({
                type: 'GROUP_STATISTICS_DATA',
                data: stats
            } as WorkerResponse)
            break

        case 'GET_PCA':
            const pcaData = getPCA(msg.binning, msg.metric)
            self.postMessage({
                type: 'PCA_DATA',
                data: pcaData
            } as WorkerResponse)
            break

        case 'GET_COSINOR':
            const cosinorData = getCosinorAnalysis(msg.binning, msg.metric)
            self.postMessage({
                type: 'COSINOR_DATA',
                data: cosinorData
            } as WorkerResponse)
            break

        case 'GET_PERIODOGRAM':
            const periData = getPeriodogramAnalysis(msg.binning, msg.metric)
            self.postMessage({
                type: 'PERIODOGRAM_DATA',
                data: periData
            } as WorkerResponse)
            break

        case 'GET_CORRELATION':
            const corrData = getCorrelationAnalysis(msg.binning, msg.metric1, msg.metric2)
            self.postMessage({
                type: 'CORRELATION_DATA',
                data: corrData
            } as WorkerResponse)
            break

        case 'GET_ANOVA':
            const anovaData = getRMAnovaAnalysis(msg.binning, msg.metric)
            self.postMessage({
                type: 'ANOVA_DATA',
                data: anovaData
            } as WorkerResponse)
            break

        case 'GET_CIRCADIAN':
            const circadianData = getCircadianData(msg.metric, msg.maxDays, msg.virtualControlCages)
            self.postMessage({
                type: 'CIRCADIAN_DATA',
                data: circadianData
            } as WorkerResponse)
            break
    }
}

const applyCustomGroups = (customGroups: Record<string, string>) => {
    // Override group directly in the in-memory dataset
    workerData = workerData.map(row => {
        if (customGroups[row.cage] !== undefined) {
            return { ...row, group: customGroups[row.cage] }
        }
        return row
    })
}

// Normalises DVC dt_start timestamps to a precise ISO UTC string with ms zeroed.
// Input:  "2025-05-08 10:00:00.164 UTC"  (or any variation)
// Output: "2025-05-08T10:00:00.000Z"
const normalizeDtStart = (raw: string | undefined | null): string | null => {
    if (!raw) return null
    // Replace space-before-UTC timezone designator: "... UTC" → "Z"
    // Also handles "+00:00", "GMT", etc. as a fallback
    let s = raw.trim()
    s = s.replace(/\s+UTC$/i, 'Z')
    s = s.replace(/\s+GMT$/i, 'Z')
    // Replace separating space between date and time
    s = s.replace(/^(\d{4}-\d{2}-\d{2})\s(\d{2}:\d{2}:\d{2})/, '$1T$2')
    // Strip sub-second fractions: "T10:00:00.164Z" → "T10:00:00Z"
    s = s.replace(/(\d{2}:\d{2}:\d{2})\.\d+/, '$1')
    // Ensure Z suffix present if no timezone info
    if (!s.endsWith('Z') && !s.match(/[+-]\d{2}:\d{2}$/)) s += 'Z'
    return s
}

// The heavy lifting (Smart Importer logic moved here)
const parseFile = (file: File): Promise<void> => {
    return new Promise((resolve, reject) => {
        workerData = []
        workerMetrics = []
        workerCages = []

        Papa.parse(file, {
            header: true,
            skipEmptyLines: true,
            complete: (results) => {
                try {
                    const rows = results.data as any[]
                    if (rows.length === 0) throw new Error('CSV file is empty.')

                    const firstRow = rows[0]
                    const hasTimestamp = 'timestamp' in firstRow || 'dt_start' in firstRow
                    if (!hasTimestamp) throw new Error('CSV must contain a timestamp column ("timestamp" or "dt_start").')

                    const isLongFormat = 'metric' in firstRow && 'value' in firstRow
                    let metricsSet = new Set<string>()
                    const cageSet = new Set<string>()

                    if (isLongFormat) {
                        const pivotMap = new Map<string, any>()

                        rows.forEach(row => {
                            const ts = normalizeDtStart(row.dt_start) || row.timestamp
                            const cage = row.cage || row.cage_name || row.cage_uuid || 'Unknown'
                            if (!ts) return

                            const key = `${ts}_${cage}`
                            if (!pivotMap.has(key)) {
                                pivotMap.set(key, {
                                    timestamp: ts,
                                    rawTime: new Date(ts).getTime(),
                                    cage: String(cage),
                                    group: row.group ? String(row.group) : undefined
                                })
                            }

                            const entry = pivotMap.get(key)
                            const m = row.metric
                            const val = parseFloat(row.value)

                            // It's possible for raw files to have overlapping timestamps 
                            // (e.g., fractional seconds differing but parsed as same second if truncated).
                            // If metric already exists, average them to prevent discontinuity or skipping.
                            if (m && !isNaN(val)) {
                                if (entry[m] !== undefined) {
                                    // Keep a running average if duplicate timestamps exist
                                    entry[`_count_${m}`] = (entry[`_count_${m}`] || 1) + 1
                                    entry[m] = entry[m] + (val - entry[m]) / entry[`_count_${m}`]
                                } else {
                                    entry[m] = val
                                }
                                metricsSet.add(m)
                            }
                            cageSet.add(String(cage))
                        })

                        // Clean up temp pivot count keys
                        workerData = Array.from(pivotMap.values()).map(obj => {
                            Object.keys(obj).forEach(k => {
                                if (k.startsWith('_count_')) delete obj[k]
                            })
                            return obj
                        })
                    } else {
                        // Wide Format
                        const excludeColumns = [
                            'day', 'hour', 'minute', 'relativeTime', 'timestamp', 'dt_start',
                            'group', 'cage', 'cage_name', 'cage_uuid', 'cage_position', 'samples',
                            'month', 'year'
                        ]

                        const baseMetrics = Object.keys(firstRow).filter(k => !excludeColumns.includes(k))
                        const hasV_Electrodes = baseMetrics.some(m => m.startsWith('v_'))
                        const hasE_Electrodes = baseMetrics.some(m => m.match(/^e\d+$/))
                        const hasGlobalActivity = baseMetrics.includes('Global_Activity')

                        const calculateAverage = (hasV_Electrodes || hasE_Electrodes) && !hasGlobalActivity
                        if (calculateAverage) metricsSet.add('average_activation')

                        baseMetrics.forEach(m => metricsSet.add(m))

                        rows.forEach(row => {
                            const ts = normalizeDtStart(row.dt_start) || row.timestamp
                            let cageRaw = row.cage || row.cage_name || row.cage_uuid || 'Global_Cage'
                            if (!ts) return

                            cageSet.add(String(cageRaw))

                            const parsedRow: any = {
                                timestamp: ts,
                                rawTime: new Date(ts).getTime(),
                                cage: String(cageRaw),
                            }
                            if (row.group) parsedRow.group = String(row.group)

                            let totalSensed = 0
                            let validMetricsCount = 0

                            baseMetrics.forEach(m => {
                                const val = parseFloat(row[m])
                                if (!isNaN(val)) {
                                    parsedRow[m] = val
                                    if (calculateAverage && (m.startsWith('v_') || m.match(/^e\d+$/))) {
                                        totalSensed += val
                                        validMetricsCount++
                                    }
                                }
                            })

                            if (calculateAverage) {
                                parsedRow['average_activation'] = validMetricsCount > 0 ? totalSensed / validMetricsCount : 0
                            }

                            workerData.push(parsedRow as ParsedMetricData)
                        })
                    }

                    // Promote important metrics
                    let finalMetrics = Array.from(metricsSet)
                    const promoMetrics = ['average_activation', 'Global_Activity', 'activity', 'ACTIVATION']
                    const promoFound: string[] = []
                    const others: string[] = []

                    finalMetrics.forEach(m => {
                        if (promoMetrics.includes(m)) promoFound.push(m)
                        else others.push(m)
                    })

                    promoFound.sort((a, b) => promoMetrics.indexOf(a) - promoMetrics.indexOf(b))
                    workerMetrics = [...promoFound, ...others.sort()]
                    workerCages = Array.from(cageSet).sort()
                    workerData.sort((a, b) => a.rawTime - b.rawTime)

                    // ============================================================
                    // CRITICAL: Compute per-cage relative time offsets
                    // Different cages may have been recorded at completely different
                    // calendar periods. We MUST use relative time (ms since each
                    // cage's first recorded point) so all cages can be displayed
                    // on the same timeline without artificial gaps.
                    // ============================================================
                    const cageOrigin: Record<string, number> = {}
                    // First pass: find each cage's earliest rawTime
                    workerData.forEach(row => {
                        const cage = row.cage
                        if (cageOrigin[cage] === undefined || row.rawTime < cageOrigin[cage]) {
                            cageOrigin[cage] = row.rawTime
                        }
                    })
                    // Second pass: attach relTimeMs / relTimeH to every row
                    workerData.forEach(row => {
                        row.relTimeMs = row.rawTime - cageOrigin[row.cage]
                        row.relTimeH = row.relTimeMs / 3_600_000    // hours
                    })

                    resolve()
                } catch (e: any) {
                    reject(e)
                }
            },
            error: (err) => reject(err)
        })
    })
}

// Downsampling logic moved to worker
const getBinnedData = (binning: 'raw' | '15m' | '1h' | '1d', metrics: string[]): ParsedMetricData[] => {
    if (binning === 'raw') {
        return workerData
    }

    const bins: Record<string, any> = {}

    workerData.forEach(row => {
        const d = new Date(row.timestamp)
        if (binning === '15m') d.setMinutes(Math.floor(d.getMinutes() / 15) * 15, 0, 0)
        else if (binning === '1h') d.setMinutes(0, 0, 0)
        else if (binning === '1d') d.setHours(0, 0, 0, 0)

        const binKey = `${d.getTime()}_${row.cage}_${row.group || ''}`

        if (!bins[binKey]) {
            bins[binKey] = {
                timestamp: d.toISOString(),
                rawTime: d.getTime(),
                relTimeMs: row.relTimeMs ?? 0,
                relTimeH: row.relTimeH ?? 0,
                cage: row.cage,
                group: row.group,
                _count: 1,
            }
            metrics.forEach(m => {
                const val = parseFloat(row[m])
                if (!isNaN(val)) bins[binKey][`_sum_${m}`] = val
            })
            // Persist electrode data for spatial heatmap if raw is not selected
            for (let i = 1; i <= 12; i++) {
                if (row[`v_${i}`] !== undefined) bins[binKey][`_sum_v_${i}`] = parseFloat(row[`v_${i}`])
                if (row[`e${i}`] !== undefined) bins[binKey][`_sum_e${i}`] = parseFloat(row[`e${i}`])
            }
        } else {
            bins[binKey]._count++
            metrics.forEach(m => {
                const val = parseFloat(row[m])
                if (!isNaN(val)) bins[binKey][`_sum_${m}`] = (bins[binKey][`_sum_${m}`] || 0) + val
            })
            for (let i = 1; i <= 12; i++) {
                const vVal = parseFloat(row[`v_${i}`])
                const eVal = parseFloat(row[`e${i}`])
                if (!isNaN(vVal)) bins[binKey][`_sum_v_${i}`] = (bins[binKey][`_sum_v_${i}`] || 0) + vVal
                if (!isNaN(eVal)) bins[binKey][`_sum_e${i}`] = (bins[binKey][`_sum_e${i}`] || 0) + eVal
            }
        }
    })

    return Object.values(bins).map(b => {
        metrics.forEach(m => {
            if (b[`_sum_${m}`] !== undefined) {
                b[m] = b[`_sum_${m}`] / b._count
                delete b[`_sum_${m}`]
            }
        })
        for (let i = 1; i <= 12; i++) {
            if (b[`_sum_v_${i}`] !== undefined) b[`v_${i}`] = b[`_sum_v_${i}`] / b._count
            if (b[`_sum_e${i}`] !== undefined) b[`e${i}`] = b[`_sum_e${i}`] / b._count
            delete b[`_sum_v_${i}`]
            delete b[`_sum_e${i}`]
        }
        delete b._count
        return b as ParsedMetricData
    }).sort((a, b) => a.rawTime - b.rawTime)
}

// Advanced Statistical Group Aggregation
// Needs to compute Mean, SD, SEM, 95% CI and P-values via simple-statistics
import * as ss from 'simple-statistics'

const getGroupStatistics = (binning: 'raw' | '15m' | '1h' | '1d', metric: string) => {
    // 1. Get binned data (only need the requested metric)
    const binned = getBinnedData(binning, [metric])

    // 2. Determine bin size for snapping relative time
    const binMs: Record<string, number> = { raw: 60_000, '15m': 900_000, '1h': 3_600_000, '1d': 86_400_000 }
    const snap = binMs[binning] ?? 3_600_000

    // 3. Group values by RELATIVE time slot (not absolute), then by group
    //    This allows cages recorded in different calendar periods to be
    //    compared on the same X axis (hours since recording start).
    const timeGroups: Record<string, { relSlotMs: number, relLabel: string, rawTime: number, groups: Record<string, number[]> }> = {}

    binned.forEach(row => {
        if (!row.group) return

        const relMs = row.relTimeMs ?? 0
        const slotMs = Math.round(relMs / snap) * snap
        const slotKey = String(slotMs)

        if (!timeGroups[slotKey]) {
            const h = slotMs / 3_600_000
            const label = h < 24
                ? `${h.toFixed(0)}h`
                : `Day ${Math.floor(h / 24) + 1} ${String(Math.floor(h % 24)).padStart(2, '0')}:00`
            timeGroups[slotKey] = { relSlotMs: slotMs, relLabel: label, rawTime: row.rawTime, groups: {} }
        }

        const grp = row.group
        if (!timeGroups[slotKey].groups[grp]) {
            timeGroups[slotKey].groups[grp] = []
        }

        const val = parseFloat(row[metric])
        if (!isNaN(val)) {
            timeGroups[slotKey].groups[grp].push(val)
        }
    })

    // 4. Compute Mean, SD, SEM, CI, and P-Values per timepoint
    const result = Object.entries(timeGroups).map(([, val]) => {
        const rowEntry: any = { timestamp: val.relLabel, relSlotMs: val.relSlotMs, rawTime: val.rawTime }
        const groupsAvailable = Object.keys(val.groups)

        groupsAvailable.forEach(grp => {
            const values = val.groups[grp]
            if (values.length === 0) return

            const n = values.length
            const mean = ss.mean(values)

            // Standard Deviation
            const sd = values.length > 1 ? ss.standardDeviation(values) : 0

            // Standard Error of the Mean
            const sem = values.length > 1 ? sd / Math.sqrt(n) : 0

            // 95% Confidence Interval ≈ 1.96 * SEM
            const ci95 = 1.96 * sem

            rowEntry[`${grp}_mean`] = parseFloat(mean.toFixed(2))

            // Error Bands format: [lower, upper]
            rowEntry[`${grp}_range_sd`] = [Math.max(0, mean - sd), mean + sd]
            rowEntry[`${grp}_range_sem`] = [Math.max(0, mean - sem), mean + sem]
            rowEntry[`${grp}_range_ci95`] = [Math.max(0, mean - ci95), mean + ci95]
        })

        // Quick Welch's t-test approximation if exactly 2 groups
        if (groupsAvailable.length === 2) {
            const [g1, g2] = groupsAvailable
            const v1 = val.groups[g1]
            const v2 = val.groups[g2]

            // We need at least 2 points per group to compute variance
            if (v1.length >= 2 && v2.length >= 2) {
                const funcTTest = ss.tTestTwoSample(v1, v2)

                if (funcTTest !== null) {
                    // Welch-Satterthwaite equation for degrees of freedom
                    const var1 = ss.variance(v1)
                    const var2 = ss.variance(v2)
                    const n1 = v1.length
                    const n2 = v2.length

                    const dfNumerator = Math.pow((var1 / n1) + (var2 / n2), 2)
                    const dfDenominator = (Math.pow(var1 / n1, 2) / (n1 - 1)) + (Math.pow(var2 / n2, 2) / (n2 - 1))
                    const df = dfNumerator / dfDenominator

                    // We use df here theoretically although for broad approximations n>10 is assumed 
                    // for these critical values. We explicitly read df to satisfy TS and context.
                    const isLargeSample = df >= 10

                    const t = Math.abs(funcTTest)
                    if (isLargeSample && t >= 3.29) rowEntry.significance = '***'
                    else if (isLargeSample && t >= 2.58) rowEntry.significance = '**'
                    else if (isLargeSample && t >= 1.96) rowEntry.significance = '*'
                    else rowEntry.significance = null

                    rowEntry.tStat = parseFloat(funcTTest.toFixed(2))
                    rowEntry.df = parseFloat(df.toFixed(2))

                    // Approximate p-value based on threshold flags
                    if (rowEntry.significance === '***') rowEntry.pVal = '< 0.001'
                    else if (rowEntry.significance === '**') rowEntry.pVal = '< 0.01'
                    else if (rowEntry.significance === '*') rowEntry.pVal = '< 0.05'
                    else rowEntry.pVal = 'n.s.'
                }
            }
        }

        return rowEntry
    })

    return result.sort((a, b) => (a.relSlotMs ?? a.rawTime) - (b.relSlotMs ?? b.rawTime))
}

// --- ADVANCED ANALYSIS ---
import { PCA } from 'ml-pca'
import { Matrix, solve } from 'ml-matrix'

const getPCA = (binning: 'raw' | '15m' | '1h' | '1d', metric: string) => {
    const binned = getBinnedData(binning, [metric])

    // We want a matrix where rows are Cages, and columns are Timepoints
    // 1. Find all unique timepoints
    const timepoints = new Set<number>()
    binned.forEach(r => timepoints.add(r.rawTime))
    const sortedTimes = Array.from(timepoints).sort((a, b) => a - b)

    // 2. Build map of Cage -> Array of values (imputing missing with cage mean or 0)
    const cageData: Record<string, { group: string, values: Record<number, number> }> = {}

    binned.forEach(r => {
        if (!r.cage) return
        if (!cageData[r.cage]) {
            cageData[r.cage] = { group: r.group || 'Unknown', values: {} }
        }

        const val = parseFloat(r[metric])
        if (!isNaN(val)) {
            cageData[r.cage].values[r.rawTime] = val
        }
    })

    const cagesList = Object.keys(cageData)
    if (cagesList.length < 3) return null // Need at least 3 cages for a meaningful 2D PCA

    const matrixData: number[][] = []

    for (const cage of cagesList) {
        const rowData = []
        let cageSum = 0; let cageCount = 0;

        // Calculate mean for imputation
        for (const t of sortedTimes) {
            if (cageData[cage].values[t] !== undefined) {
                cageSum += cageData[cage].values[t]
                cageCount++
            }
        }
        const cageMean = cageCount > 0 ? cageSum / cageCount : 0

        for (const t of sortedTimes) {
            rowData.push(cageData[cage].values[t] !== undefined ? cageData[cage].values[t] : cageMean)
        }
        matrixData.push(rowData)
    }

    try {
        const pca = new PCA(matrixData)
        // Get the first two principal components
        const scores = pca.predict(matrixData).to2DArray()
        const explainedVariance = pca.getExplainedVariance()

        const points = cagesList.map((cage, i) => ({
            cage,
            group: cageData[cage].group,
            pc1: scores[i][0],
            pc2: scores[i][1]
        }))

        return {
            points,
            variance: {
                pc1: explainedVariance[0],
                pc2: explainedVariance[1]
            }
        }
    } catch (err) {
        console.error("PCA failed:", err)
        return null
    }
}

const getCosinorAnalysis = (binning: 'raw' | '15m' | '1h' | '1d', metric: string) => {
    // Fits Y = M + A * cos(2pi * t / 24) + B * sin(2pi * t / 24)
    // To solve using Multiple Linear Regression: β = (X^T X)^-1 X^T Y
    const binned = getBinnedData(binning, [metric])

    const cageData: Record<string, { group: string, t: number[], y: number[] }> = {}

    binned.forEach(r => {
        if (!r.cage || !r.timestamp) return
        const val = parseFloat(r[metric])
        if (isNaN(val)) return

        if (!cageData[r.cage]) {
            cageData[r.cage] = { group: r.group || 'Unknown', t: [], y: [] }
        }

        // Convert timestamp to hours
        const date = new Date(r.timestamp)
        const hours = date.getHours() + (date.getMinutes() / 60)

        cageData[r.cage].t.push(hours)
        cageData[r.cage].y.push(val)
    })

    const period = 24
    const results: any[] = []

    Object.entries(cageData).forEach(([cage, data]) => {
        if (data.y.length < 5) return // Need sufficient points

        const XArr = []
        const YArr = []

        for (let i = 0; i < data.t.length; i++) {
            const time = data.t[i]
            const y = data.y[i]

            const x1 = Math.cos(2 * Math.PI * time / period)
            const x2 = Math.sin(2 * Math.PI * time / period)

            XArr.push([1, x1, x2])
            YArr.push([y])
        }

        try {
            const X = new Matrix(XArr)
            const Y = new Matrix(YArr)

            // beta = solve(X, Y) => (X^T X)^-1 X^T Y
            const beta = solve(X, Y).to1DArray()

            const M = beta[0]
            const A_coef = beta[1]
            const B_coef = beta[2]

            const Amplitude = Math.sqrt(A_coef * A_coef + B_coef * B_coef)
            let Acrophase = Math.atan2(-B_coef, A_coef) * (period / (2 * Math.PI))
            if (Acrophase < 0) Acrophase += period // Normalize to 0-24

            results.push({
                cage,
                group: data.group,
                mesor: parseFloat(M.toFixed(2)),
                amplitude: parseFloat(Amplitude.toFixed(2)),
                acrophase: parseFloat(Acrophase.toFixed(2))
            })
        } catch (err) {
            console.error(`Cosinor failed for ${cage}:`, err)
        }
    })

    return results
}

// ==========================================
// CORRELATION ANALYSIS
// ==========================================
const getCorrelationAnalysis = (binning: 'raw' | '15m' | '1h' | '1d', metric1: string, metric2: string) => {
    const data = getBinnedData(binning, [metric1, metric2])
    const groupsToAnalyze = Array.from(new Set(data.map(d => d.group).filter(Boolean))) as string[]

    // If no groups, analyze as 'All Cages'
    if (groupsToAnalyze.length === 0) {
        groupsToAnalyze.push('All Cages')
        data.forEach(d => { d.group = 'All Cages' })
    }

    const results = groupsToAnalyze.map(grp => {
        const groupData = data.filter(d => d.group === grp)
        const points: [number, number][] = []
        const scatterPoints: { x: number, y: number, cage: string, timestamp: string }[] = []

        groupData.forEach(d => {
            const val1 = d[metric1]
            const val2 = d[metric2]
            if (typeof val1 === 'number' && !isNaN(val1) && typeof val2 === 'number' && !isNaN(val2)) {
                points.push([val1, val2])
                scatterPoints.push({ x: val1, y: val2, cage: d.cage, timestamp: d.timestamp })
            }
        })

        if (points.length < 3) return null

        try {
            // Calculate regression line
            const regression = ss.linearRegression(points)
            const line = ss.linearRegressionLine(regression)

            // Calculate r and r^2
            const r = ss.sampleCorrelation(points.map(p => p[0]), points.map(p => p[1]))
            const r2 = r * r

            // Create a small subset of points for the drawn line
            const xCoords = points.map(p => p[0])
            const minX = Math.min(...xCoords)
            const maxX = Math.max(...xCoords)

            const linePoints = [
                { x: minX, y: line(minX) },
                { x: maxX, y: line(maxX) }
            ]

            return {
                group: grp,
                points: scatterPoints,
                regressionLine: linePoints,
                equation: { m: regression.m, b: regression.b },
                stats: {
                    r: parseFloat(r.toFixed(3)),
                    r2: parseFloat(r2.toFixed(3)),
                    n: points.length
                }
            }
        } catch (e) {
            console.error(e)
            return null
        }
    }).filter(Boolean)

    return results
}

// ==========================================
// PERIODOGRAM ANALYSIS (Iterative Cosinor)
// ==========================================
const getPeriodogramAnalysis = (binning: 'raw' | '15m' | '1h' | '1d', metric: string) => {
    const binned = getBinnedData(binning, [metric])
    const cageData: Record<string, { group: string, t: number[], y: number[] }> = {}

    // Grouping logic (same as Cosinor)
    binned.forEach(r => {
        if (!r.cage || !r.timestamp) return
        const val = parseFloat(r[metric])
        if (isNaN(val)) return

        if (!cageData[r.cage]) {
            cageData[r.cage] = { group: r.group || 'Unknown', t: [], y: [] }
        }

        // Convert timestamp to accumulated hours from start
        const timeOffsetHours = (r.rawTime - binned[0].rawTime) / (1000 * 60 * 60)
        cageData[r.cage].t.push(timeOffsetHours)
        cageData[r.cage].y.push(val)
    })

    const periodsToTest = Array.from({ length: 101 }, (_, i) => 20 + i * 0.1) // 20.0 to 30.0 hours
    const results: any[] = []

    Object.entries(cageData).forEach(([cage, data]) => {
        if (data.y.length < 10) return

        const powers = periodsToTest.map(period => {
            const XArr = []
            const YArr = []

            for (let i = 0; i < data.t.length; i++) {
                const time = data.t[i]
                const y = data.y[i]
                XArr.push([1, Math.cos(2 * Math.PI * time / period), Math.sin(2 * Math.PI * time / period)])
                YArr.push([y])
            }

            try {
                const X = new Matrix(XArr)
                const Y = new Matrix(YArr)
                const beta = solve(X, Y).to1DArray()

                const A_coef = beta[1]
                const B_coef = beta[2]

                // Power is represented as amplitude here
                const Amplitude = Math.sqrt(A_coef * A_coef + B_coef * B_coef)
                return { period: parseFloat(period.toFixed(1)), power: parseFloat(Amplitude.toFixed(3)) }
            } catch {
                return { period: parseFloat(period.toFixed(1)), power: 0 }
            }
        })

        results.push({
            cage,
            group: data.group,
            spectrum: powers,
            dominantPeriod: powers.reduce((max, p) => p.power > max.power ? p : max, powers[0]).period
        })
    })

    return results
}

// ==========================================
// REPEATED MEASURES ANOVA (Group x Time)
// ==========================================
// Helper for approximate F p-value (conservative approximation using F-dist properties or just return significance thresholds)
function getFSignificance(F: number, _df1: number, _df2: number): string {
    // Simple lookup approximation for alpha = 0.05, 0.01, 0.001
    // A robust JS F-distribution CDF is large, so we use thresholds for large df2.
    // Assuming mostly large df2 (>50) in timeseries
    if (F > 11.0) return '***' // approx p < 0.001
    if (F > 6.9) return '**'   // approx p < 0.01
    if (F > 3.9) return '*'    // approx p < 0.05
    return 'n.s.'
}

const getRMAnovaAnalysis = (binning: 'raw' | '15m' | '1h' | '1d', metric: string) => {
    const binned = getBinnedData(binning, [metric])

    // We need complete Time x Cage matrix. Subsetting to common timepoints
    const timepoints = new Set<number>()
    binned.forEach(d => { if (!isNaN(parseFloat(d[metric]))) timepoints.add(d.rawTime) })
    const validTimes = Array.from(timepoints).sort((a, b) => a - b)
    const t = validTimes.length
    if (t < 2) return null

    // Parse data into structures
    const cages: Record<string, { group: string, values: number[] }> = {}

    binned.forEach(d => {
        const val = parseFloat(d[metric])
        if (isNaN(val) || !d.cage) return

        if (!cages[d.cage]) {
            cages[d.cage] = { group: d.group || 'Unknown', values: new Array(t).fill(NaN) }
        }

        const tIdx = validTimes.indexOf(d.rawTime)
        cages[d.cage].values[tIdx] = val
    })

    const allCages = Object.keys(cages)
    const N = allCages.length

    // Group structures
    const groupCages: Record<string, string[]> = {}
    allCages.forEach(c => {
        const g = cages[c].group
        if (!groupCages[g]) groupCages[g] = []
        groupCages[g].push(c)
    })

    const k = Object.keys(groupCages).length
    if (k < 2 || N <= k) return null // Need at least 2 groups and df > 0

    // IMPUTATION: RM ANOVA requires no missing data. We impute NaNs with the Group x Time Mean.
    const GT_Means: Record<string, number[]> = {}
    Object.keys(groupCages).forEach(g => {
        GT_Means[g] = new Array(t).fill(0)
        for (let j = 0; j < t; j++) {
            let sum = 0, count = 0
            groupCages[g].forEach(c => {
                if (!isNaN(cages[c].values[j])) { sum += cages[c].values[j]; count++ }
            })
            GT_Means[g][j] = count > 0 ? sum / count : 0 // Fallback to 0 if entire group is missing
        }
    })

    let grandSum = 0, grandCount = 0
    allCages.forEach(c => {
        const g = cages[c].group
        for (let j = 0; j < t; j++) {
            if (isNaN(cages[c].values[j])) {
                cages[c].values[j] = GT_Means[g][j] // Impute
            }
            grandSum += cages[c].values[j]
            grandCount++
        }
    })

    const GM = grandSum / grandCount // Grand Mean

    // Subject Means (Shi)
    const S_hi: Record<string, number> = {}
    allCages.forEach(c => {
        S_hi[c] = cages[c].values.reduce((a, b) => a + b, 0) / t
    })

    // Group Means (Gi)
    const G_i: Record<string, number> = {}
    Object.keys(groupCages).forEach(g => {
        let sum = 0
        groupCages[g].forEach(c => { sum += S_hi[c] })
        G_i[g] = sum / groupCages[g].length
    })

    // Time Means (Tj)
    const T_j = new Array(t).fill(0)
    for (let j = 0; j < t; j++) {
        let sum = 0
        allCages.forEach(c => { sum += cages[c].values[j] })
        T_j[j] = sum / N
    }

    // CALCULATE SUM OF SQUARES
    let SS_Total = 0
    let SS_Time = 0
    let SS_Group = 0
    let SS_SubjGroup = 0
    let SS_GroupTime = 0
    let SS_ErrorWithin = 0

    // SS_Group
    Object.keys(groupCages).forEach(g => {
        const ni = groupCages[g].length
        SS_Group += t * ni * Math.pow(G_i[g] - GM, 2)
    })

    // SS_SubjGroup
    Object.keys(groupCages).forEach(g => {
        groupCages[g].forEach(c => {
            SS_SubjGroup += t * Math.pow(S_hi[c] - G_i[g], 2)
        })
    })

    // SS_Time
    for (let j = 0; j < t; j++) {
        SS_Time += N * Math.pow(T_j[j] - GM, 2)
    }

    // SS_GroupTime
    Object.keys(groupCages).forEach(g => {
        const ni = groupCages[g].length
        for (let j = 0; j < t; j++) {
            let sum = 0
            groupCages[g].forEach(c => { sum += cages[c].values[j] })
            const gtMean = sum / ni
            SS_GroupTime += ni * Math.pow(gtMean - G_i[g] - T_j[j] + GM, 2)
        }
    })

    // Total and Error Within
    allCages.forEach(c => {
        const g = cages[c].group
        for (let j = 0; j < t; j++) {
            const Y = cages[c].values[j]
            SS_Total += Math.pow(Y - GM, 2)

            let sumGT = 0
            groupCages[g].forEach(cg => { sumGT += cages[cg].values[j] })
            const gtMean = sumGT / groupCages[g].length

            // Residual = Y_hij - S_hi - GT_ij + G_i
            SS_ErrorWithin += Math.pow(Y - S_hi[c] - gtMean + G_i[g], 2)
        }
    })

    // DEGREES OF FREEDOM
    const df_Group = k - 1
    const df_SubjGroup = N - k
    const df_Time = t - 1
    const df_GroupTime = (k - 1) * (t - 1)
    const df_ErrorWithin = (N - k) * (t - 1)

    // MEAN SQUARES
    const MS_Group = SS_Group / df_Group
    const MS_SubjGroup = SS_SubjGroup / df_SubjGroup
    const MS_Time = SS_Time / df_Time
    const MS_GroupTime = SS_GroupTime / df_GroupTime
    const MS_ErrorWithin = SS_ErrorWithin / df_ErrorWithin

    // F-RATIOS
    const F_Group = MS_Group / MS_SubjGroup
    const F_Time = MS_Time / MS_ErrorWithin
    const F_Interaction = MS_GroupTime / MS_ErrorWithin

    return {
        mainGroup: { F: parseFloat(F_Group.toFixed(3)), df1: df_Group, df2: df_SubjGroup, sig: getFSignificance(F_Group, df_Group, df_SubjGroup) },
        mainTime: { F: parseFloat(F_Time.toFixed(3)), df1: df_Time, df2: df_ErrorWithin, sig: getFSignificance(F_Time, df_Time, df_ErrorWithin) },
        interaction: { F: parseFloat(F_Interaction.toFixed(3)), df1: df_GroupTime, df2: df_ErrorWithin, sig: getFSignificance(F_Interaction, df_GroupTime, df_ErrorWithin) },
        method: "Two-Way Repeated Measures ANOVA (Type III SS Approximation)"
    }
}

// ==========================================
// CIRCADIAN RHYTHM — fold to 24h average
// 30-min resolution (48 slots, 0h–23:30h)
// ==========================================
const getCircadianData = (metric: string, maxDays: number | null, virtualControlCages: string[]) => {
    if (workerData.length === 0) return null

    const minRawTime = workerData.reduce((m, r) => Math.min(m, r.rawTime), Infinity)
    const cutoffMs = maxDays != null ? minRawTime + maxDays * 86_400_000 : Infinity

    const SLOTS = 48 // 30-min slots
    const cageSlots: Record<string, number[][]> = {}

    workerData.forEach(row => {
        if (row.rawTime > cutoffMs) return
        const val = parseFloat(row[metric])
        if (isNaN(val)) return
        const d = new Date(row.timestamp)
        const minuteOfDay = d.getHours() * 60 + d.getMinutes()
        const slot = Math.floor(minuteOfDay / 30)
        if (!cageSlots[row.cage]) {
            cageSlots[row.cage] = Array.from({ length: SLOTS }, () => [])
        }
        cageSlots[row.cage][slot].push(val)
    })

    const perCage = Object.keys(cageSlots).map(cage => {
        const points = cageSlots[cage].map((vals, slot) => {
            const h = slot * 0.5
            const xLabel = `${String(Math.floor(h)).padStart(2, '0')}:${slot % 2 === 0 ? '00' : '30'}`
            if (vals.length === 0) return { hour: h, xLabel, mean: null, sem: null }
            const n = vals.length
            const mean = vals.reduce((s, v) => s + v, 0) / n
            const sd = n > 1 ? Math.sqrt(vals.reduce((s, v) => s + (v - mean) ** 2, 0) / (n - 1)) : 0
            const sem = n > 1 ? sd / Math.sqrt(n) : 0
            return { hour: h, xLabel, mean: parseFloat(mean.toFixed(3)), sem: parseFloat(sem.toFixed(3)) }
        })
        return { cage, points }
    })

    let virtualGroup: any = null
    if (virtualControlCages.length > 0) {
        const vcSlots: number[][] = Array.from({ length: SLOTS }, () => [])
        virtualControlCages.forEach(cage => {
            if (!cageSlots[cage]) return
            cageSlots[cage].forEach((vals, slot) => vals.forEach(v => vcSlots[slot].push(v)))
        })
        const points = vcSlots.map((vals, slot) => {
            const h = slot * 0.5
            const xLabel = `${String(Math.floor(h)).padStart(2, '0')}:${slot % 2 === 0 ? '00' : '30'}`
            if (vals.length === 0) return { hour: h, xLabel, mean: null, sem: null }
            const n = vals.length
            const mean = vals.reduce((s, v) => s + v, 0) / n
            const sd = n > 1 ? Math.sqrt(vals.reduce((s, v) => s + (v - mean) ** 2, 0) / (n - 1)) : 0
            const sem = n > 1 ? sd / Math.sqrt(n) : 0
            return { hour: h, xLabel, mean: parseFloat(mean.toFixed(3)), sem: parseFloat(sem.toFixed(3)) }
        })
        virtualGroup = { cage: 'Virtual Control', points }
    }

    return { perCage, virtualGroup }
}
