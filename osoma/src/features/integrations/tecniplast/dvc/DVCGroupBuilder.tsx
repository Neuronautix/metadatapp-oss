import { useState, useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card.tsx'
import { Plus, Trash2, Edit2, Users } from 'lucide-react'

interface DVCGroupBuilderProps {
    cages: string[]
    currentGroups: Record<string, string> // cage -> groupName
    onGroupsChange: (newGroups: Record<string, string>) => void
}

export function DVCGroupBuilder({ cages, currentGroups, onGroupsChange }: DVCGroupBuilderProps) {
    const [isEditing, setIsEditing] = useState(false)
    const [draftGroups, setDraftGroups] = useState<Record<string, string>>(currentGroups)
    const [newGroupName, setNewGroupName] = useState('')

    // Aggregate cages by their current draft group
    const groupsToCages = useMemo(() => {
        const map: Record<string, string[]> = {}
        Object.entries(draftGroups).forEach(([cage, group]) => {
            if (!map[group]) map[group] = []
            map[group].push(cage)
        })
        return map
    }, [draftGroups])

    const unassignedCages = cages.filter(c => !draftGroups[c])

    const handleAssign = (cage: string, group: string) => {
        setDraftGroups(prev => ({ ...prev, [cage]: group }))
    }

    const handleUnassign = (cage: string) => {
        setDraftGroups(prev => {
            const next = { ...prev }
            delete next[cage]
            return next
        })
    }

    const handleAddGroup = () => {
        if (!newGroupName.trim()) return
        // Just add an empty array to the map implicitly by rendering, or we can just ensure it exists
        // Actually, the map is derived from draftGroups. To create an empty group, we just need the UI to know it exists.
        // We can manage a separate list of available group names.
        setAvailableGroups(prev => Array.from(new Set([...prev, newGroupName.trim()])))
        setNewGroupName('')
    }

    const derivedGroups = Object.keys(groupsToCages)
    const [availableGroups, setAvailableGroups] = useState<string[]>(derivedGroups)

    const handleApply = () => {
        onGroupsChange(draftGroups)
        setIsEditing(false)
    }

    const handleCancel = () => {
        setDraftGroups(currentGroups)
        setAvailableGroups(Object.values(currentGroups).filter((v, i, a) => a.indexOf(v) === i))
        setIsEditing(false)
    }

    if (!isEditing) {
        const activeGroupCount = Object.keys(groupsToCages).length
        return (
            <div className="flex items-center gap-3">
                <div className="text-sm text-slate-600">
                    <Users className="w-4 h-4 inline-block mr-1 text-indigo-500" />
                    <span className="font-semibold text-slate-800">{activeGroupCount}</span> active groups
                </div>
                <button
                    onClick={() => setIsEditing(true)}
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold bg-white text-indigo-600 border border-indigo-200 hover:bg-indigo-50 transition-colors shadow-sm"
                >
                    <Edit2 className="w-3.5 h-3.5" /> Override Groups
                </button>
            </div>
        )
    }

    return (
        <Card className="mt-4 border-indigo-300 shadow-md bg-indigo-50/30 animate-in fade-in slide-in-from-top-2">
            <CardHeader className="pb-2">
                <CardTitle className="text-lg text-indigo-900 flex items-center gap-2">
                    <Users className="w-5 h-5" /> Cohort Builder
                </CardTitle>
                <CardDescription>
                    Assign cages to analytical groups. This overrides any groups defined in the CSV.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col md:flex-row gap-6 mt-2">

                    {/* Unassigned Cages */}
                    <div className="flex-1 bg-white p-4 rounded-xl border border-slate-200 shadow-sm min-h-[200px]">
                        <h4 className="text-sm font-bold text-slate-700 mb-3 flex justify-between items-center">
                            Unassigned Cages
                            <span className="bg-slate-100 text-slate-500 py-0.5 px-2 rounded-full text-xs">{unassignedCages.length}</span>
                        </h4>
                        <div className="flex flex-col gap-2">
                            {unassignedCages.length === 0 && <span className="text-xs text-slate-400 italic">All cages assigned.</span>}
                            {unassignedCages.map(cage => (
                                <div key={cage} className="flex items-center justify-between bg-slate-50 border border-slate-200 pr-1 pl-3 py-1.5 rounded-lg text-sm font-medium text-slate-700">
                                    <span>{cage}</span>
                                    <div className="flex gap-1 flex-wrap justify-end max-w-[60%]">
                                        {availableGroups.map(g => (
                                            <button
                                                key={g}
                                                onClick={() => handleAssign(cage, g)}
                                                className="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white transition-colors"
                                                title={`Add to ${g}`}
                                            >
                                                {g.slice(0, 3)} +
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Groups */}
                    <div className="flex-[2] flex flex-col gap-4">
                        <div className="flex items-center gap-2 bg-white p-2 rounded-lg border border-slate-200 shadow-sm">
                            <input
                                type="text"
                                value={newGroupName}
                                onChange={e => setNewGroupName(e.target.value)}
                                placeholder="New Group Name (e.g., Control, Knockout)"
                                className="flex-1 text-sm bg-transparent border-none outline-none px-2 focus:ring-0"
                                onKeyDown={e => e.key === 'Enter' && handleAddGroup()}
                            />
                            <button
                                onClick={handleAddGroup}
                                disabled={!newGroupName.trim()}
                                className="px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors flex items-center gap-1"
                            >
                                <Plus className="w-3.5 h-3.5" /> Create
                            </button>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {availableGroups.map(group => (
                                <div key={group} className="bg-white p-3 rounded-xl border border-indigo-100 shadow-sm">
                                    <h4 className="text-sm font-bold text-indigo-900 mb-2 border-b border-indigo-50 pb-1">{group}</h4>
                                    <div className="flex flex-wrap gap-1.5 min-h-[40px]">
                                        {groupsToCages[group]?.length ? (
                                            groupsToCages[group].map(cage => (
                                                <div key={cage} className="flex items-center gap-1 bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded text-xs">
                                                    {cage}
                                                    <button onClick={() => handleUnassign(cage)} className="hover:text-red-500 ml-1">
                                                        <Trash2 className="w-3 h-3" />
                                                    </button>
                                                </div>
                                            ))
                                        ) : (
                                            <span className="text-xs text-slate-400 italic self-center">Empty group</span>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {availableGroups.length === 0 && (
                                <div className="col-span-full p-4 text-center border-2 border-dashed border-slate-200 rounded-xl text-slate-500 text-sm">
                                    No custom groups created yet.
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3 border-t border-indigo-100 pt-4">
                    <button
                        onClick={handleCancel}
                        className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleApply}
                        className="px-6 py-2 bg-indigo-600 text-white text-sm font-bold rounded-lg shadow-sm hover:bg-indigo-700 hover:shadow transition-all"
                    >
                        Apply Groups
                    </button>
                </div>
            </CardContent>
        </Card>
    )
}
