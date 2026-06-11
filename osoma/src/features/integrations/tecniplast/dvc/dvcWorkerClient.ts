import type { WorkerMessage, WorkerResponse, ParsedMetricData } from './dvcDataWorker.ts'

class DVCWorkerClient {
    private worker: Worker | null = null
    private resolvers: Map<string, (value: any) => void> = new Map()
    private rejecters: Map<string, (error: any) => void> = new Map()

    init() {
        if (!this.worker) {
            // In Vite, we import workers with ?worker suffix
            this.worker = new Worker(new URL('./dvcDataWorker.ts', import.meta.url), { type: 'module' })
            this.worker.onmessage = this.handleMessage.bind(this)
        }
    }

    private handleMessage(event: MessageEvent<WorkerResponse>) {
        const msg = event.data
        if (msg.type === 'PARSE_SUCCESS' || msg.type === 'PARSE_ERROR') {
            const res = this.resolvers.get('parse')
            const rej = this.rejecters.get('parse')
            if (msg.type === 'PARSE_SUCCESS' && res) res({ metrics: msg.metrics, cages: msg.cages, rowCount: msg.rowCount })
            if (msg.type === 'PARSE_ERROR' && rej) rej(new Error(msg.error))
            this.resolvers.delete('parse')
            this.rejecters.delete('parse')
        }
        else if (msg.type === 'DATA_INFO') {
            const res = this.resolvers.get('info')
            if (res) res({ metrics: msg.metrics, cages: msg.cages, rowCount: msg.rowCount })
            this.resolvers.delete('info')
        }
        else if (msg.type === 'BINNED_DATA') {
            const res = this.resolvers.get('data')
            if (res) res(msg.data)
            this.resolvers.delete('data')
        }
        else if (msg.type === 'GROUPS_UPDATED') {
            const res = this.resolvers.get('setGroups')
            if (res) res(undefined)
            this.resolvers.delete('setGroups')
        }
        else if (msg.type === 'GROUP_STATISTICS_DATA') {
            const res = this.resolvers.get('groupStats')
            if (res) res(msg.data)
            this.resolvers.delete('groupStats')
        }
        else if (msg.type === 'PCA_DATA') {
            const res = this.resolvers.get('pca')
            if (res) res(msg.data)
            this.resolvers.delete('pca')
        }
        else if (msg.type === 'COSINOR_DATA') {
            const res = this.resolvers.get('cosinor')
            if (res) res(msg.data)
            this.resolvers.delete('cosinor')
        }
        else if (msg.type === 'CORRELATION_DATA') {
            const res = this.resolvers.get('correlation')
            if (res) res(msg.data)
            this.resolvers.delete('correlation')
        }
        else if (msg.type === 'PERIODOGRAM_DATA') {
            const res = this.resolvers.get('periodogram')
            if (res) res(msg.data)
            this.resolvers.delete('periodogram')
        }
        else if (msg.type === 'ANOVA_DATA') {
            const res = this.resolvers.get('anova')
            if (res) res(msg.data)
            this.resolvers.delete('anova')
        }
        else if (msg.type === 'CIRCADIAN_DATA') {
            const res = this.resolvers.get('circadian')
            if (res) res(msg.data)
            this.resolvers.delete('circadian')
        }
    }

    async parseFile(file: File): Promise<{ metrics: string[], cages: string[], rowCount: number }> {
        // Terminate any existing worker to clear old array memory states before parsing a new file
        this.terminate()
        this.init()

        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('parse', resolve)
            this.rejecters.set('parse', reject)
            this.worker.postMessage({ type: 'PARSE_FILE', file } as WorkerMessage)
        })
    }

    async getInfo(): Promise<{ metrics: string[], cages: string[], rowCount: number }> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('info', resolve)
            this.worker.postMessage({ type: 'GET_DATA_INFO' } as WorkerMessage)
        })
    }

    async getBinnedData(binning: 'raw' | '15m' | '1h' | '1d', metrics: string[]): Promise<ParsedMetricData[]> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('data', resolve)
            this.worker.postMessage({ type: 'GET_BINNED_DATA', binning, metrics } as WorkerMessage)
        })
    }

    async setCustomGroups(customGroups: Record<string, string>): Promise<void> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('setGroups', resolve)
            this.worker.postMessage({ type: 'SET_CUSTOM_GROUPS', customGroups } as WorkerMessage)
        })
    }

    async getGroupStatistics(binning: 'raw' | '15m' | '1h' | '1d', metric: string): Promise<any> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('groupStats', resolve)
            this.worker.postMessage({ type: 'GET_GROUP_STATISTICS', binning, metric } as WorkerMessage)
        })
    }

    async getPCA(binning: 'raw' | '15m' | '1h' | '1d', metric: string): Promise<any> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('pca', resolve)
            this.worker.postMessage({ type: 'GET_PCA', binning, metric } as WorkerMessage)
        })
    }

    async getCosinor(binning: 'raw' | '15m' | '1h' | '1d', metric: string): Promise<any> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('cosinor', resolve)
            this.worker.postMessage({ type: 'GET_COSINOR', binning, metric } as WorkerMessage)
        })
    }

    async getCorrelation(binning: 'raw' | '15m' | '1h' | '1d', metric1: string, metric2: string): Promise<any> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('correlation', resolve)
            this.worker.postMessage({ type: 'GET_CORRELATION', binning, metric1, metric2 } as WorkerMessage)
        })
    }

    async getPeriodogram(binning: 'raw' | '15m' | '1h' | '1d', metric: string): Promise<any> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('periodogram', resolve)
            this.worker.postMessage({ type: 'GET_PERIODOGRAM', binning, metric } as WorkerMessage)
        })
    }

    async getANOVA(binning: 'raw' | '15m' | '1h' | '1d', metric: string): Promise<any> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('anova', resolve)
            this.worker.postMessage({ type: 'GET_ANOVA', binning, metric } as WorkerMessage)
        })
    }

    async getCircadianData(metric: string, maxDays: number | null, virtualControlCages: string[]): Promise<any> {
        this.init()
        return new Promise((resolve, reject) => {
            if (!this.worker) return reject(new Error("Worker not initialized"))
            this.resolvers.set('circadian', resolve)
            this.worker.postMessage({ type: 'GET_CIRCADIAN', metric, maxDays, virtualControlCages } as WorkerMessage)
        })
    }

    terminate() {
        if (this.worker) {
            this.worker.terminate()
            this.worker = null
        }
    }
}

export const dvcWorker = new DVCWorkerClient()
