@php
    $counts = \Illuminate\Support\Facades\Cache::remember('topbar.counts.'.(session('active_store_id') ?? 0), 30, function () {
        $sid = session('active_store_id');
        $base = \App\Models\OrderMirror::query();
        if ($sid) $base->where('store_id', $sid);
        return [
            'inbox'   => (clone $base)->whereIn('status', ['confirmed','pending'])->count(),
            'picking' => \App\Models\PickingSession::where('status','open')->when($sid, fn($q)=>$q->where('store_id',$sid))->count(),
            'packing' => (clone $base)->where('status','processing')->count(),
            'returns' => \App\Models\RmaWorkflow::whereIn('status', ['requested','approved','received'])->when($sid, fn($q)=>$q->where('store_id',$sid))->count(),
        ];
    });
@endphp

<header class="bg-white border-b px-4 py-2 flex items-center justify-between sticky top-0 z-10">
    <div class="flex gap-4 text-sm">
        <span class="px-2 py-1 rounded bg-amber-100 text-amber-800">Inbox {{ $counts['inbox'] }}</span>
        <span class="px-2 py-1 rounded bg-sky-100 text-sky-800">Picking {{ $counts['picking'] }}</span>
        <span class="px-2 py-1 rounded bg-indigo-100 text-indigo-800">Packing {{ $counts['packing'] }}</span>
        <span class="px-2 py-1 rounded bg-rose-100 text-rose-800">Returns {{ $counts['returns'] }}</span>
    </div>
    <div class="flex items-center gap-3 text-sm">
        <a href="{{ route('profile.edit') }}" class="text-slate-600 hover:text-slate-900">Profile</a>
    </div>
</header>
