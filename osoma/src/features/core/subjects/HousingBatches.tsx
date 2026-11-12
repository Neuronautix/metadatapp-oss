import { useState } from 'react';

type FishStage = 'Adult' | 'Larvae';

interface Batch {
    id: string;
    tankId: string;
    stage: FishStage;
    count: number;
    volumeL: number;
    dob: string;
}

interface WaterQuality {
    temp: number;
    conductivity: number;
    ph: number;
}

const MOCK_BATCHES: Batch[] = [
    { id: 'B-101', tankId: 'T-A1', stage: 'Adult', count: 45, volumeL: 5, dob: '2025-10-15' },
    { id: 'B-102', tankId: 'T-A2', stage: 'Adult', count: 8, volumeL: 1.5, dob: '2025-11-20' },
    { id: 'B-205', tankId: 'T-L1', stage: 'Larvae', count: 120, volumeL: 3, dob: '2026-01-10' },
];

const MOCK_WATER: WaterQuality = {
    temp: 28.5,
    conductivity: 1250,
    ph: 7.4,
};

export function HousingBatches() {
    const [batches] = useState<Batch[]>(MOCK_BATCHES);
    const [water] = useState<WaterQuality>(MOCK_WATER);

    const getDensity = (batch: Batch) => (batch.count / batch.volumeL).toFixed(1);

    const getStatusColor = (batch: Batch) => {
        const density = batch.count / batch.volumeL;
        if (batch.stage === 'Adult' && density > 10) return 'text-red-500 font-bold';
        if (batch.stage === 'Larvae' && density > 50) return 'text-red-500 font-bold';
        return 'text-green-600';
    };

    return (
        <div className="p-6 bg-white rounded-xl shadow-sm border border-slate-200">
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-xl font-semibold text-slate-800">Housing Batches (AquaSystem)</h2>
                <div className="flex gap-4 text-sm bg-slate-50 px-3 py-1 rounded-lg border border-slate-100">
                    <span className="flex items-center gap-1">
                        <span className="w-2 h-2 rounded-full bg-green-500"></span>
                        Temp: <b>{water.temp}°C</b>
                    </span>
                    <span className="flex items-center gap-1">
                        <span className="w-2 h-2 rounded-full bg-green-500"></span>
                        Cond: <b>{water.conductivity} µS</b>
                    </span>
                    <span className="flex items-center gap-1">
                        <span className="w-2 h-2 rounded-full bg-green-500"></span>
                        pH: <b>{water.ph}</b>
                    </span>
                </div>
            </div>

            <div className="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                {batches.map((batch) => (
                    <div key={batch.id} className="border border-slate-200 rounded-lg p-4 hover:border-blue-400 transition-colors cursor-pointer group">
                        <div className="flex justify-between mb-2">
                            <span className="font-mono text-sm text-slate-500">{batch.tankId}</span>
                            <span className={`text-xs px-2 py-0.5 rounded-full ${batch.stage === 'Adult' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'}`}>
                                {batch.stage}
                            </span>
                        </div>
                        <div className="text-3xl font-bold text-slate-800 mb-1">
                            {batch.count} <span className="text-sm font-normal text-slate-500">fish</span>
                        </div>

                        <div className="flex justify-between items-end text-sm mt-3">
                            <div className="text-slate-500">
                                Density: <span className={getStatusColor(batch)}>{getDensity(batch)}/L</span>
                            </div>
                            <div className="text-xs text-slate-400">
                                Vol: {batch.volumeL}L
                            </div>
                        </div>
                    </div>
                ))}

                {/* Add Batch Button Mock */}
                <div className="border border-dashed border-slate-300 rounded-lg p-4 flex flex-col items-center justify-center text-slate-400 hover:text-blue-500 hover:border-blue-300 cursor-pointer h-full min-h-[140px]">
                    <span className="text-2xl mb-1">+</span>
                    <span className="text-sm font-medium">Add Batch</span>
                </div>
            </div>
        </div>
    );
}
