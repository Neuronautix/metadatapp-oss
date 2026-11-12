import { useNavigate } from 'react-router-dom'
import { FileText, Plus, Search, Tag, Clock, ChevronRight, Settings, Network } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/PageHeader'

export function ProfilesPage() {
  const navigate = useNavigate()

  const profiles = [
    { id: '1', name: 'Mouse Colony - Standard', mappingCount: 12, lastUsed: '2 days ago', health: 98 },
    { id: '2', name: 'Zefix Export - Sanity', mappingCount: 8, lastUsed: '5 days ago', health: 92 },
    { id: '3', name: 'Lab-A Excel Profile', mappingCount: 15, lastUsed: '1 week ago', health: 100 },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <PageHeader 
          title="Import Profiles" 
          subtitle="Manage reusable mapping configurations and schema extensions."
        />
        <div className="flex items-center gap-2">
          <Button variant="outline" className="gap-2" onClick={() => navigate('mapp-view')}>
            <Network className="h-4 w-4" /> Explore MappView
          </Button>
          <Button className="gap-2" onClick={() => navigate('../import')}>
            <Plus className="h-4 w-4" /> New Import
          </Button>
        </div>
      </div>

      <div className="flex items-center gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <input 
            type="text" 
            placeholder="Search profiles..." 
            className="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500/10 outline-none"
          />
        </div>
        <Button variant="ghost" className="gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
          <Tag className="h-3 w-3" /> Taxonomy
        </Button>
      </div>

      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        {profiles.map(profile => (
          <Card key={profile.id} className="group hover:border-blue-200 hover:shadow-lg transition-all cursor-pointer overflow-hidden border-slate-200">
            <CardHeader className="bg-slate-50/50 group-hover:bg-blue-50/30 transition-colors">
              <div className="flex items-start justify-between">
                <div className="h-10 w-10 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-center text-blue-600 transition-transform group-hover:scale-110">
                  <FileText className="h-5 w-5" />
                </div>
                <div className="bg-green-100 text-green-700 font-black text-[10px] px-2 py-1 rounded-full uppercase tracking-tighter">
                  {profile.health}% Quality
                </div>
              </div>
              <CardTitle className="mt-4 text-lg font-bold text-slate-800">{profile.name}</CardTitle>
              <CardDescription className="flex items-center gap-1.5 text-xs">
                <Clock className="h-3 w-3" /> Used {profile.lastUsed}
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                <div className="p-4 flex items-center justify-between border-t border-slate-100">
                    <div className="flex items-center gap-4">
                        <div>
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Mappings</p>
                            <p className="text-xl font-bold text-slate-700">{profile.mappingCount}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="ghost" size="icon" className="h-8 w-8 text-slate-400 rounded-full hover:bg-slate-100">
                            <Settings className="h-4 w-4" />
                        </Button>
                        <Button size="icon" className="h-8 w-8 rounded-full bg-slate-900 shadow-md">
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
          </Card>
        ))}

        <Card className="border-dashed border-slate-300 bg-slate-50/30 hover:bg-slate-50 transition-colors flex flex-col items-center justify-center p-8 cursor-pointer" onClick={() => navigate('../import')}>
            <div className="h-12 w-12 rounded-2xl bg-white shadow-sm flex items-center justify-center text-slate-400 border border-slate-200 mb-4 transition-transform group-hover:scale-110">
                <Plus className="h-6 w-6" />
            </div>
            <p className="font-bold text-slate-800">New Import Profile</p>
            <p className="text-xs text-slate-500 mt-1">Start from a new data file.</p>
        </Card>
      </div>

      <div className="mt-12 space-y-6">
        <div className="flex items-center justify-between">
            <h3 className="text-xl font-bold text-slate-800">Schema Registry</h3>
            <span className="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">3 Entities · 16 Properties</span>
        </div>
        <Card className="border-slate-200 bg-[linear-gradient(135deg,#eff6ff,white_55%,#ecfeff)]">
          <CardContent className="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
            <div className="space-y-1">
              <p className="text-xs font-black uppercase tracking-[0.24em] text-sky-600">Schema Explorer</p>
              <p className="text-lg font-bold text-slate-900">Inspect live JSON-LD records with MappView</p>
              <p className="text-sm text-slate-600">
                Open any supported resource, expand nested relations, and export the current record as JSON-LD.
              </p>
            </div>
            <Button className="gap-2 self-start md:self-auto" onClick={() => navigate('mapp-view')}>
              <Network className="h-4 w-4" /> Open MappView
            </Button>
          </CardContent>
        </Card>
        <Card className="border-slate-200">
            <CardContent className="p-0">
                <div className="divide-y divide-slate-100">
                    <div className="grid grid-cols-4 p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/50">
                        <span className="col-span-1">Entity</span>
                        <span className="col-span-1">Mapping status</span>
                        <span className="col-span-1">Extensions</span>
                        <span className="col-span-1 text-right">Invariants</span>
                    </div>
                    <div className="grid grid-cols-4 p-4 items-center hover:bg-slate-50 transition-colors cursor-pointer">
                        <span className="col-span-1 font-bold text-sm text-slate-800 flex items-center gap-3">
                            <div className="h-2 w-2 rounded-full bg-blue-500" /> Subject
                        </span>
                        <span className="col-span-1 text-xs text-slate-500">8 of 8 properties mapped</span>
                        <span className="col-span-1 text-xs text-slate-400 font-medium">None</span>
                        <span className="col-span-1 text-right">
                             <span className="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded uppercase">Strong</span>
                        </span>
                    </div>
                    <div className="grid grid-cols-4 p-4 items-center hover:bg-slate-50 transition-colors cursor-pointer">
                        <span className="col-span-1 font-bold text-sm text-slate-800 flex items-center gap-3">
                            <div className="h-2 w-2 rounded-full bg-amber-500" /> Sample
                        </span>
                        <span className="col-span-1 text-xs text-slate-500">4 of 5 properties mapped</span>
                        <span className="col-span-1 text-xs text-slate-400 font-medium">None</span>
                        <span className="col-span-1 text-right">
                             <span className="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded uppercase">Partial</span>
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
      </div>
    </div>
  )
}
