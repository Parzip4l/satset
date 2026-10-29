@auth
    @if(config('satset.web_push.enabled') && config('satset.web_push.public_key') && config('satset.web_push.private_key'))
        @php
            $satsetWebPushConfig = [
                'publicKey' => config('satset.web_push.public_key'),
                'subscribeUrl' => route('web-push.subscriptions.store'),
                'serviceWorkerUrl' => asset('satset-sw.js'),
                'appName' => config('app.name', 'SatSet'),
            ];
        @endphp
        <script>
            window.SatsetWebPushConfig = {{ Illuminate\Support\Js::from($satsetWebPushConfig) }};
        </script>
        <script src="{{ asset('assets/js/satset-web-push.js') }}?v={{ filemtime(public_path('assets/js/satset-web-push.js')) }}"></script>
    @endif
@endauth
