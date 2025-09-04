// Pusher Beams initialization - Step 3 from documentation
// Using dynamic import for better compatibility
let beamsClient = null;
let isInitialized = false;

/**
 * Initialize Pusher Beams client
 */
export const initializePusherBeams = async () => {
    if (isInitialized || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return beamsClient;
    }

    try {
        // Get configuration from backend
        const configResponse = await fetch('/api/push-notifications/config');
        if (!configResponse.ok) {
            throw new Error('Failed to get Pusher Beams configuration');
        }
        
        const config = await configResponse.json();
        
        // Register service worker
        const serviceWorkerRegistration = await navigator.serviceWorker.register('/service-worker.js');
        
        // Dynamic import for better compatibility
        const PusherPushNotifications = await import('@pusher/push-notifications-web');
        
        // Create Beams client with instance ID from your dashboard
        beamsClient = new PusherPushNotifications.Client({
            instanceId: config.instance_id,
            serviceWorkerRegistration: serviceWorkerRegistration
        });

        // Start the client
        await beamsClient.start();
        
        // Add device interest as shown in documentation
        await beamsClient.addDeviceInterest('hello');
        
        console.log('Successfully registered and subscribed!');
        isInitialized = true;
        
        // Try to set user ID for authenticated notifications
        try {
            const tokenResponse = await fetch('/api/push-notifications/user-token');
            if (tokenResponse.ok) {
                const tokenData = await tokenResponse.json();
                await beamsClient.setUserId(tokenData.user_id, tokenData.token);
                console.log('User authenticated for push notifications');
            }
        } catch (userError) {
            console.warn('Could not authenticate user for push notifications:', userError);
            // Continue with device-only notifications
        }
        
        return beamsClient;
    } catch (error) {
        console.error('Error initializing Pusher Beams:', error);
        return null;
    }
};

/**
 * Get the current Beams client instance
 */
export const getBeamsClient = () => {
    return beamsClient;
};

/**
 * Check if Beams is initialized
 */
export const isBeamsInitialized = () => {
    return isInitialized;
};

/**
 * Subscribe to an interest
 */
export const subscribeToInterest = async (interest) => {
    if (!beamsClient) {
        console.warn('Beams client not initialized');
        return false;
    }
    
    try {
        await beamsClient.addDeviceInterest(interest);
        console.log(`Subscribed to interest: ${interest}`);
        return true;
    } catch (error) {
        console.error(`Failed to subscribe to interest ${interest}:`, error);
        return false;
    }
};

/**
 * Unsubscribe from an interest
 */
export const unsubscribeFromInterest = async (interest) => {
    if (!beamsClient) {
        console.warn('Beams client not initialized');
        return false;
    }
    
    try {
        await beamsClient.removeDeviceInterest(interest);
        console.log(`Unsubscribed from interest: ${interest}`);
        return true;
    } catch (error) {
        console.error(`Failed to unsubscribe from interest ${interest}:`, error);
        return false;
    }
};

/**
 * Get all device interests
 */
export const getDeviceInterests = async () => {
    if (!beamsClient) {
        console.warn('Beams client not initialized');
        return [];
    }
    
    try {
        const interests = await beamsClient.getDeviceInterests();
        return interests;
    } catch (error) {
        console.error('Failed to get device interests:', error);
        return [];
    }
};

/**
 * Clear all device interests
 */
export const clearAllInterests = async () => {
    if (!beamsClient) {
        console.warn('Beams client not initialized');
        return false;
    }
    
    try {
        await beamsClient.clearDeviceInterests();
        console.log('Cleared all device interests');
        return true;
    } catch (error) {
        console.error('Failed to clear device interests:', error);
        return false;
    }
};

// Auto-initialize when module is imported (optional)
// You can comment this out if you want manual initialization
if (typeof window !== 'undefined') {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            // Small delay to ensure everything is loaded
            setTimeout(initializePusherBeams, 1000);
        });
    } else {
        // DOM is already ready
        setTimeout(initializePusherBeams, 1000);
    }
}

export default {
    initializePusherBeams,
    getBeamsClient,
    isBeamsInitialized,
    subscribeToInterest,
    unsubscribeFromInterest,
    getDeviceInterests,
    clearAllInterests
};
