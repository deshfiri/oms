<x-app-layout>
    @section('title', 'Stock counts')
    <div class="admin-page-header">
        <div><h1>Stock counts</h1><p class="sub">{{ $counts->total() }} count session(s).</p></div>
        <a href="{{ route('stock-counts.create') }}" class="btn btn-dark">+ Start count</a>
    </div>
    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="list" title="Count sessions"/></div>
        @if($counts->isEmpty())
            <x-admin.empty icon="list" title="No counts yet" :ctaUrl="route('stock-counts.create')" ctaLabel="Start a count"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>ID</th><th>Location</th><th>Started</th><th>Counted</th><th>Adjusted</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($counts as $c)
                        <tr>
                            <td>#{{ $c->id }}</td>
                            <td>{{ $c->location ?? '—' }}</td>
                            <td>{{ optional($c->started_at)->diffForHumans() }}</td>
                            <td>{{ $c->items_counted }}</td>
                            <td>{{ $c->items_adjusted }}</td>
                            <td><x-admin.pill :status="$c->status"/></td>
                            <td style="text-align:right"><a href="{{ route('stock-counts.show', $c) }}" class="btn btn-dark btn-sm">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $counts->links() }}</div>
        @endif
    </div>
</x-app-layout>
