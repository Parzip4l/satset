@auth
    @if(config('satset.web_push.enabled') && config('satset.web_push.public_key') && config('satset.web_push.private_key'))
        @php
            $satsetWebPushConfig = [
                'publicKey' => config('satset.web_push.public_key'),
                'subscribeUrl' => route('web-push.subscriptions.store'),
                'unsubscribeUrl' => route('web-push.subscriptions.destroy'),
                'serviceWorkerUrl' => asset('satset-sw.js'),
                'appName' => config('app.name', 'SatSet'),
                'subscriptionVersion' => '2026-10-29-2',
            ];
        @endphp
        <script>
            window.SatsetWebPushConfig = {{ Illuminate\Support\Js::from($satsetWebPushConfig) }};
        </script>
        <script src="{{ asset('assets/js/satset-web-push.js') }}?v={{ filemtime(public_path('assets/js/satset-web-push.js')) }}"></script>
    @endif
@endauth
