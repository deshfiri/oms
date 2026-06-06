<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Picqer\Barcode\BarcodeGeneratorPNG;

class LabelController extends Controller
{
    public function single(OrderMirror $order)
    {
        return $this->render(collect([$order]));
    }

    public function batch(Request $r)
    {
        $ids = array_filter(explode(',', (string) $r->query('ids')));
        $orders = OrderMirror::with('store','items','consignments')
            ->whereIn('id', $ids)->get();
        return $this->render($orders);
    }

    protected function render($orders)
    {
        $bg = new BarcodeGeneratorPNG();
        $labels = $orders->map(function (OrderMirror $o) use ($bg) {
            $cons   = $o->consignments()->latest('booked_at')->first();
            // Barcode encodes the consignment ID (courier scan key); QR encodes
            // the ORDER ID so anyone scanning it lands on the order.
            $code   = $cons?->consignment_id ?? $o->order_number;
            $barB64 = base64_encode($bg->getBarcode($code, $bg::TYPE_CODE_128, 2, 60));
            $qrB64  = base64_encode(
                (new Builder(
                    writer: new PngWriter(),
                    data: $o->order_number,
                    size: 120,
                    margin: 0,
                ))->build()->getString()
            );
            return [
                'order'       => $o,
                'cons'        => $cons,
                'barcode_b64' => $barB64,
                'qr_b64'      => $qrB64,
            ];
        });
        return view('labels.print', ['labels' => $labels]);
    }
}
