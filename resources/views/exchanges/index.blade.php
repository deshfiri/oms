<x-app-layout>
    @section('title', 'Exchanges')
    <div class="admin-page-header"><div><h1>Exchanges</h1><p class="sub">{{ $exchanges->total() }} request(s).</p></div></div>
    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="rotate" title="Exchange requests"/></div>
        @if($exchanges->isEmpty())
            <x-admin.empty icon="rotate" title="No exchanges yet"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>When</th><th>Original</th><th>Replacement</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach($exchanges as $ex)
                            <tr>
                                <td>{{ $ex->requested_at->diffForHumans() }}</td>
                                <td>{{ optional($ex->original)->order_number }}</td>
                                <td>{{ optional($ex->replacement)->order_number ?? '—' }}</td>
                                <td>{{ ucwords(str_replace('_',' ', $ex->reason)) }}</td>
                                <td><x-admin.pill :status="$ex->status"/></td>
                                <td style="text-align:right"><a href="{{ route('exchanges.show', $ex) }}" class="btn btn-outline btn-sm">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $exchanges->links() }}</div>
        @endif
    </div>
</x-app-layout>
