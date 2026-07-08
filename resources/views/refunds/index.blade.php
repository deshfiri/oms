<x-app-layout>
    @section('title', 'Refunds')

    <div class="admin-page-header">
        <div>
            <h1>Refunds</h1>
            <p class="sub">Track and manage customer refund requests</p>
        </div>
    </div>

    {{-- Status chips --}}
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        @php
            $statuses = [
                ''           => ['All', $counts->sum()],
                'pending'    => ['Pending', $counts['pending'] ?? 0],
                'refunded'   => ['Refunded', $counts['refunded'] ?? 0],
                'failed'     => ['Failed', $counts['failed'] ?? 0],
                'cancelled'  => ['Cancelled', $counts['cancelled'] ?? 0],
            ];
        @endphp
        @foreach($statuses as $val => $c)
            @php $active = (string)request('status') === (string)$val; @endphp
            <a href="{{ route('refunds.index', $val !== '' ? ['status' => $val] : []) }}"
               style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $active ? 'var(--a-accent)' : 'var(--a-border)' }};background:{{ $active ? 'var(--a-accent)' : '#fff' }};color:{{ $active ? '#fff' : 'var(--a-text-2)' }}">
                {{ $c[0] }}
                <span style="font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:{{ $active ? 'rgba(255,255,255,.25)' : 'var(--a-surface-2)' }}">{{ $c[1] }}</span>
            </a>
        @endforeach
    </div>

    <div class="admin-card">
        <div class="admin-card-head">
            <x-admin.section-head icon="reports" title="Refund log" :description="$refunds->total().' result(s)'"/>
        </div>

        @if($refunds->isEmpty())
            <x-admin.empty icon="reports" title="No refunds found"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Store</th>
                            <th>Customer</th>
                            <th style="text-align:right">Grand total</th>
                            <th style="text-align:right">Refund amount</th>
                            <th>Status</th>
                            <th>Note</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($refunds as $o)
                        @php
                            $badgeColor = match($o->refund_status) {
                                'refunded'  => ['#d1fae5','#065f46'],
                                'failed'    => ['#fee2e2','#991b1b'],
                                'pending'   => ['#fef3c7','#92400e'],
                                'cancelled' => ['#f3f4f6','#6b7280'],
                                default     => ['#f3f4f6','#6b7280'],
                            };
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('orders.show', $o) }}"
                                   style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a>
                                <div style="font-size:11px;color:var(--a-text-3)">{{ $o->statusLabel() }}</div>
                            </td>
                            <td><strong>{{ optional($o->store)->dfid ?? '—' }}</strong></td>
                            <td>
                                {{ $o->customer_name }}
                                <div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div>
                            </td>
                            <td style="text-align:right">৳{{ number_format($o->grand_total, 2) }}</td>
                            <td style="text-align:right;font-weight:700">
                                ৳{{ number_format($o->refund_amount ?? 0, 2) }}
                            </td>
                            <td>
                                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;background:{{ $badgeColor[0] }};color:{{ $badgeColor[1] }}">
                                    {{ ucfirst($o->refund_status) }}
                                </span>
                            </td>
                            <td style="font-size:12px;color:var(--a-text-2);max-width:180px">{{ $o->refund_note ?? '—' }}</td>
                            <td style="font-size:12px;color:var(--a-text-3);white-space:nowrap">
                                {{ $o->refunded_at?->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                @if($o->refund_status === 'failed')
                                    <form method="POST" action="{{ route('refunds.retry', $o) }}" style="display:inline">
                                        @csrf
                                        <button class="btn btn-dark btn-sm">Retry</button>
                                    </form>
                                @endif
                                @if($o->refund_status === 'pending')
                                    <form method="POST" action="{{ route('refunds.cancel', $o) }}" style="display:inline"
                                          onsubmit="return confirm('Cancel this pending refund?')">
                                        @csrf
                                        <button class="btn btn-outline btn-sm" style="color:#991b1b;border-color:#fca5a5">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $refunds->links() }}</div>
        @endif
    </div>
</x-app-layout>
