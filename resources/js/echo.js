import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Configure Pusher with optimizations for <300ms delivery
window.Pusher = Pusher;

// Check if Pusher credentials are available
const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1';

// Only initialize Echo if Pusher credentials are available
if (pusherKey) {
    // Create Echo instance with optimized settings
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${pusherCluster}.pusherapp.com`,
        wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
        wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        
        // Optimizations for real-time performance
        disableStats: true, // Disable stats for better performance
        enableLogging: import.meta.env.DEV, // Only log in development
        
        // Connection optimization
        activityTimeout: 30000, // 30 seconds
        pongTimeout: 6000, // 6 seconds
        unavailableTimeout: 10000, // 10 seconds
        
        // Authentication for private channels
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json',
            },
        },
        
        // Pusher-specific optimizations
        pusher: {
            // Enable compression for faster data transfer
            enabledTransports: ['ws', 'wss'],
            disabledTransports: ['xhr_polling', 'xhr_streaming', 'sockjs'],
            
            // Connection settings for low latency
            activityTimeout: 30000,
            pongTimeout: 6000,
            unavailableTimeout: 10000,
            
            // Cluster optimization
            cluster: pusherCluster,
            forceTLS: true,
        }
    });

    // Connection event handlers for monitoring
    window.Echo.connector.pusher.connection.bind('connected', () => {
        console.log('✅ Real-time connection established');
        // Trigger heartbeat to maintain connection
        if (window.chatHeartbeat) {
            window.chatHeartbeat.start();
        }
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
        console.warn('⚠️ Real-time connection lost');
        // Stop heartbeat when disconnected
        if (window.chatHeartbeat) {
            window.chatHeartbeat.stop();
        }
    });

    window.Echo.connector.pusher.connection.bind('error', (error) => {
        console.error('❌ Real-time connection error:', error);
    });

    // Reconnection handling
    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
        console.log(`🔄 Connection state: ${states.previous} → ${states.current}`);
        
        if (states.current === 'connected') {
            // Resubscribe to channels after reconnection
            if (window.resubscribeToChannels) {
                window.resubscribeToChannels();
            }
        }
    });

} else {
    // Create a mock Echo instance for development when Pusher is not configured
    console.warn('Pusher credentials not found. Real-time features will be disabled.');
    window.Echo = {
        channel: () => ({
            listen: () => {},
            stopListening: () => {},
        }),
        private: () => ({
            listen: () => {},
            stopListening: () => {},
            whisper: () => {},
        }),
        join: () => ({
            listen: () => {},
            stopListening: () => {},
            here: () => {},
            joining: () => {},
            leaving: () => {},
            whisper: () => {},
        }),
        leave: () => {},
        disconnect: () => {},
        connector: {
            pusher: {
                connection: {
                    bind: () => {},
                }
            }
        }
    };
}

// Global heartbeat manager for maintaining connection
window.chatHeartbeat = {
    interval: null,
    
    start() {
        if (this.interval) return;
        
        // Send heartbeat every 30 seconds
        this.interval = setInterval(async () => {
            try {
                await fetch('/api/realtime-chat/heartbeat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    },
                });
            } catch (error) {
                console.warn('Heartbeat failed:', error);
            }
        }, 30000);
    },
    
    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }
};

export default window.Echo;
