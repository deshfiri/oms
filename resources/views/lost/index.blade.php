<x-app-layout>
    @section('title', 'Lost')
    <div class="admin-page-header">
        <div><h1>Lost products</h1><p class="sub">{{ $items->total() }} record(s).</p></div>
        <a href="{{ route('lost.create') }}" class="btn btn-dark">+ Record lost</a>
    </div>
    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="warning" title="Loss log"/></div>
        @if($items->isEmpty())
            <x-admin.empty icon="warning" title="No losses recorded"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>When</th><th>Store</th><th>SKU</th><th>Qty</th><th>Party</th><th>Reason</th><th style="text-align:right">Compensation</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($items as $l)
                            <tr>
                                <td>{{ $l->recorded_at->diffForHumans() }}</td>
                                <td><strong>{{ optional($l->store)->dfid }}</strong></td>
                                <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $l->sku }}</td>
                                <td>{{ $l->qty }}</td>
                                <td><x-admin.pill :status="$l->responsible_party"/></td>
                                <td>{{ $l->reason }}</td>
                                <td style="text-align:right">৳{{ number_format($l->compensation_amount, 2) }}</td>
                                <td><x-admin.pill :status="$l->compensation_status"/></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $items->links() }}</div>
        @endif
    </div>
</x-app-layout>
