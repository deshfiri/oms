<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CourierConsignment;
use App\Models\OrderMirror;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

/**
 * Unified scan resolver — one entry point used by the search bar baked into
 * Processing / Dispatch / Returns. Looks up by order_number, tracking_code,
 * or consignment_id and applies the right transition for the current context.
 *
 * Each route returns JSON so the page can update inline without a full reload,
 * and also gracefully falls back to a redirect for non-JS clients.
 */
class ScanController extends Controller
{
    /** Pack scan: must be in Processing, flips → Packed. */
    public function pack(Request $r, OrderStateMachine $sm)
    {
        return $this->oneOrBulk($r, $sm, OrderStateMachine::PACKED,
            [OrderStateMachine::PROCESSING], 'pack');
    }

    /** Dispatch scan: must be Packed, flips → Dispatched. */
    public function dispatch(Request $r, OrderStateMachine $sm)
    {
        return $this->oneOrBulk($r, $sm, OrderStateMachine::DISPATCHED,
            [OrderStateMachine::PACKED], 'dispatch');
    }

    /** Return intake scan: must be Return Pending / Awaiting Return Product, flips → Returned. */
    public function returnIntake(Request $r, OrderStateMachine $sm)
    {
        return $this->oneOrBulk($r, $sm, OrderStateMachine::RETURNED,
            [OrderStateMachine::RETURN_PENDING, OrderStateMachine::AWAITING_RETURN_PRODUCT], 'return');
    }

    /** Just look up an order by any of: order_number, consignment_id, tracking_code. */
    public function lookup(Request $r)
    {
        $code = trim((string) $r->input('code'));
        if (! $code) return response()->json(['ok' => false, 'error' => 'empty']);
        $order = $this->resolve($code);
        if (! $order) return response()->json(['ok' => false, 'error' => 'not_found']);
        return response()->json([
            'ok'   => true,
            'href' => route('orders.show', $order),
            'order'=> [
                'id'     => $order->id,
                'number' => $order->order_number,
                'status' => $order->status,
                'label'  => $order->statusLabel(),
            ],
        ]);
    }

    protected function oneOrBulk(Request $r, OrderStateMachine $sm, string $to, array $allowed, string $kind)
    {
        $codes = collect(preg_split('/[\s,]+/', (string) $r->input('codes', $r->input('code', ''))))
            ->map(fn ($x) => trim($x))->filter()->unique();

        if ($codes->isEmpty()) {
            return back()->with('error', 'No codes to scan.');
        }

        $ok = []; $skipped = []; $missing = [];
        foreach ($codes as $code) {
            $order = $this->resolve($code);
            if (! $order) { $missing[] = $code; continue; }
            if (! in_array($order->status, $allowed, true)) {
                $skipped[] = "$code ({$order->statusLabel()})";
                continue;
            }
            try { $sm->transition($order, $to, ['note' => "Scanned at $kind"]); $ok[] = $order->order_number; }
            catch (\Throwable $e) { $skipped[] = "$code ({$e->getMessage()})"; }
        }
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => "scan.$kind",
            'before_json'=> ['codes' => $codes->values()->all()],
            'after_json' => compact('ok','skipped','missing'),
            'ip'         => $r->ip(),
            'user_agent' => $r->userAgent(),
        ]);

        if ($r->wantsJson()) {
            return response()->json([
                'ok'      => count($ok) > 0,
                'kind'    => $kind,
                'ok_list' => $ok,
                'skipped' => $skipped,
                'missing' => $missing,
                'summary' => sprintf('%d %s · %d skipped · %d unknown',
                    count($ok), $kind === 'pack' ? 'packed' : ($kind === 'dispatch' ? 'dispatched' : 'received'),
                    count($skipped), count($missing),
                ),
            ]);
        }
        return back()->with('status', sprintf('%d %s, %d skipped, %d unknown.',
            count($ok), $kind === 'pack' ? 'packed' : ($kind === 'dispatch' ? 'dispatched' : 'received'),
            count($skipped), count($missing),
        ));
    }

    /** Find an order by order_number, tracking_code, or consignment_id. */
    protected function resolve(string $code): ?OrderMirror
    {
        // Direct order number
        $order = OrderMirror::where('order_number', $code)->first();
        if ($order) return $order;

        // Via consignment / tracking
        $cons = CourierConsignment::where('tracking_code', $code)
            ->orWhere('consignment_id', $code)->first();
        return $cons?->order;
    }
}
