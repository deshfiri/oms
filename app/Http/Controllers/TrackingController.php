<?php

namespace App\Http\Controllers;

use App\Models\CourierConsignment;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function index(Request $r)
    {
        $inTransit = CourierConsignment::with('order.store:id,dfid,business_name,name')
            ->whereIn('latest_status', ['booked','picked_up','in_transit','hub_received','out_for_delivery'])
            ->latest('booked_at')->paginate(40);

        $exceptions = CourierConsignment::with('order.store:id,dfid,business_name,name')
            ->whereIn('latest_status', ['delivery_failed','return_initiated','returned','lost'])
            ->latest('booked_at')->limit(50)->get();

        return view('tracking.index', compact('inTransit','exceptions'));
    }

    public function show(CourierConsignment $consignment)
    {
        $consignment->load('order.store', 'events');
        return view('tracking.show', compact('consignment'));
    }
}
