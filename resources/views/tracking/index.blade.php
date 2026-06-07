<x-app-layout>
    @section('title', 'Tracking')
    <div class="admin-page-header"><div><h1>Tracking</h1><p class="sub">Live courier statuses from webhooks across every store. Tracked by Consignment ID.</p></div></div>

    <div id="tracking-live-region">
        @include('tracking._tables')
    </div>

    <x-order-sync
        scope="tracking"
        :rows-url="route('tracking.rows')"
        mode="region"
        region-id="tracking-live-region"/>
</x-app-layout>
