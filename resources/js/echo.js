import Echo from 'laravel-echo';

import Pusher from 'pusher-js';

window.Pusher = Pusher;


const csrf = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    wsPath: import.meta.env.VITE_REVERB_SERVER, // z.B. "/reverb/app"

    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            fetch('/broadcasting/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ?? '',
                },
                credentials: 'include',
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: channel.name,
                }),
            })
                .then(async (res) =>
                    res.ok ? callback(null, await res.json())
                        : callback(new Error('Auth failed: ' + res.status), null)
                )
                .catch((err) => callback(err, null));
        },
    }),
});
