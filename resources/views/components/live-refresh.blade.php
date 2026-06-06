@props([
    'scope' => 'all',   // which queue this page shows
    'poll'  => 2,       // seconds between change-checks
])
{{-- Invisible live sync: polls a tiny signature and reloads the page only when
     this queue actually changed. No badge, always on. --}}
<div x-data="liveSync('{{ $scope }}', {{ $poll }})" style="display:none"></div>
@push('scripts')
<script defer src="/js/alpine.min.js"></script>
<script>
function liveSync(scope, poll) {
    return {
        baseline: null,
        timer: null,
        url: '{{ route('poll.signature') }}?scope=' + encodeURIComponent(scope),
        async init() {
            this.baseline = await this.fetchSig();   // queue state as the page rendered
            this.start();
        },
        async fetchSig() {
            try {
                const r = await fetch(this.url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                const j = await r.json();
                return j.sig;
            } catch (e) { return null; }
        },
        start() {
            this.timer = setInterval(async () => {
                if (document.hidden) return;          // pause while tab is backgrounded
                const sig = await this.fetchSig();
                if (sig && this.baseline && sig !== this.baseline) {
                    clearInterval(this.timer);
                    location.reload();                // reload ONLY when the queue changed
                }
            }, poll * 1000);
        },
    };
}
</script>
@endpush
