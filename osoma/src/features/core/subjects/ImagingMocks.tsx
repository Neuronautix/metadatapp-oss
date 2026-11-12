import { useState } from 'react';

interface ImageRun {
    id: string;
    name: string;
    modality: 'Confocal' | 'Brightfield' | 'Fluorescence' | 'LightSheet';
    date: string;
    thumbnailColor: string;
    meta: {
        magnification: string;
        exposure?: string;
        wavelength?: string;
        zStack?: boolean;
    };
}

const MOCK_IMAGES: ImageRun[] = [
    {
        id: 'IMG-001',
        name: 'Cranial Development - 24hpf',
        modality: 'Confocal',
        date: '2026-01-27 10:30',
        thumbnailColor: 'bg-indigo-900',
        meta: { magnification: '63x', wavelength: '488nm/561nm', zStack: true }
    },
    {
        id: 'IMG-002',
        name: 'Whole Body Phenotype',
        modality: 'Brightfield',
        date: '2026-01-27 14:15',
        thumbnailColor: 'bg-amber-100',
        meta: { magnification: '10x', exposure: '50ms' }
    },
    {
        id: 'IMG-003',
        name: 'GFP Vascularization',
        modality: 'Fluorescence',
        date: '2026-01-28 09:00',
        thumbnailColor: 'bg-green-900',
        meta: { magnification: '20x', wavelength: '488nm' }
    },
];

export function ImagingMocks() {
    const [images] = useState<ImageRun[]>(MOCK_IMAGES);

    return (
        <div className="p-6 bg-white rounded-xl shadow-sm border border-slate-200 mt-6">
            <h2 className="text-xl font-semibold text-slate-800 mb-4">Imaging & Microscopy Data</h2>

            <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                            <th className="pb-3 pl-2">Preview</th>
                            <th className="pb-3">Run Name</th>
                            <th className="pb-3">Modality</th>
                            <th className="pb-3">Metadata</th>
                            <th className="pb-3">Date</th>
                            <th className="pb-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                        {images.map((img) => (
                            <tr key={img.id} className="hover:bg-slate-50 transition-colors group">
                                <td className="py-3 pl-2 w-16">
                                    <div className={`w-12 h-12 rounded-lg ${img.thumbnailColor} opacity-80 group-hover:opacity-100 transition-opacity border border-slate-200`}></div>
                                </td>
                                <td className="py-3">
                                    <div className="font-medium text-slate-800">{img.name}</div>
                                    <div className="text-xs text-slate-400 font-mono">{img.id}</div>
                                </td>
                                <td className="py-3">
                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800">
                                        {img.modality}
                                    </span>
                                </td>
                                <td className="py-3">
                                    <div className="text-xs text-slate-600 space-y-0.5">
                                        <div>Mag: {img.meta.magnification}</div>
                                        {img.meta.wavelength && <div>λ: {img.meta.wavelength}</div>}
                                        {img.meta.zStack && <div className="text-indigo-600 font-medium">Z-Stack MIP</div>}
                                    </div>
                                </td>
                                <td className="py-3 text-sm text-slate-500">
                                    {img.date.split(' ')[0]} <span className="text-xs text-slate-400">{img.date.split(' ')[1]}</span>
                                </td>
                                <td className="py-3">
                                    <button className="text-blue-600 hover:text-blue-800 text-sm font-medium">View</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
