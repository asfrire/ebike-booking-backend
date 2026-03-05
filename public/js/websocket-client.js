// E-Bike Booking WebSocket Client
class EBookingWebSocket {
    constructor() {
        this.echo = null;
        this.riderId = null;
        this.bookingId = null;
        this.isConnected = false;
        this.init();
    }

    init() {
        // Initialize Laravel Echo
        this.echo = new Echo({
            broadcaster: 'socket.io',
            host: window.location.hostname + ':6004',
            auth: {
                headers: {
                    Authorization: 'Bearer ' + this.getAuthToken()
                }
            }
        });

        this.setupEventListeners();
        this.connect();
    }

    getAuthToken() {
        return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    }

    connect() {
        if (!this.echo.connector.socket) {
            console.log('🔌 Connecting to WebSocket...');
            this.echo.connector.socket.on('connect', () => {
                this.isConnected = true;
                console.log('✅ WebSocket Connected');
                this.onConnected();
            });

            this.echo.connector.socket.on('disconnect', () => {
                this.isConnected = false;
                console.log('❌ WebSocket Disconnected');
                this.onDisconnected();
            });

            this.echo.connector.socket.on('reconnecting', () => {
                console.log('🔄 WebSocket Reconnecting...');
            });
        }
    }

    setupEventListeners() {
        // Rider queue updates
        this.echo.channel('rider-queue')
            .listen('rider.position.updated', (e) => {
                this.onRiderPositionUpdated(e);
            });

        // Admin dashboard updates
        this.echo.channel('admin-dashboard')
            .listen('booking.status.updated', (e) => {
                this.onBookingStatusUpdated(e);
            });
    }

    // Rider-specific methods
    subscribeToRiderChannel(riderId) {
        this.riderId = riderId;
        
        this.echo.private(`rider.${riderId}`)
            .listen('booking.assigned', (e) => {
                this.onBookingAssigned(e);
            })
            .listen('booking.expired', (e) => {
                this.onBookingExpired(e);
            });
    }

    // Customer-specific methods
    subscribeToBookingChannel(bookingId) {
        this.bookingId = bookingId;
        
        this.echo.private(`booking.${bookingId}`)
            .listen('booking.status.updated', (e) => {
                this.onBookingStatusUpdated(e);
            });
    }

    // Event handlers
    onConnected() {
        console.log('🎉 WebSocket connection established');
        if (window.onWebSocketConnected) {
            window.onWebSocketConnected();
        }
    }

    onDisconnected() {
        console.log('⚠️ WebSocket connection lost');
        if (window.onWebSocketDisconnected) {
            window.onWebSocketDisconnected();
        }
    }

    onRiderPositionUpdated(data) {
        console.log('📍 Rider position updated:', data);
        
        // Update rider queue UI
        if (window.updateRiderQueue) {
            window.updateRiderQueue(data);
        }
        
        // Show notification for position change
        if (data.old_position && data.new_position > data.old_position) {
            this.showNotification('Queue Position Updated', 
                `Moved from position ${data.old_position} to ${data.new_position}`, 
                'info');
        }
    }

    onBookingAssigned(data) {
        console.log('🏍️ New booking assigned:', data);
        
        // Show booking notification
        this.showNotification('New Booking Assignment!', 
            `${data.booking.pax} passengers - ${data.booking.pickup_location} to ${data.booking.dropoff_location}`, 
            'success');
        
        // Start acceptance countdown
        this.startAcceptanceTimer(data.booking.id, data.booking.expires_at);
        
        // Update UI
        if (window.onBookingAssigned) {
            window.onBookingAssigned(data);
        }
    }

    onBookingExpired(data) {
        console.log('⏰ Booking expired:', data);
        
        this.showNotification('Booking Expired', 
            'The booking assignment has expired', 
            'warning');
        
        if (window.onBookingExpired) {
            window.onBookingExpired(data);
        }
    }

    onBookingStatusUpdated(data) {
        console.log('📊 Booking status updated:', data);
        
        // Update booking UI
        if (window.updateBookingStatus) {
            window.updateBookingStatus(data);
        }
        
        // Show status change notification
        if (data.message) {
            this.showNotification('Booking Update', data.message, 'info');
        }
    }

    startAcceptanceTimer(bookingId, expiresAt) {
        const expiryTime = new Date(expiresAt);
        const updateInterval = setInterval(() => {
            const now = new Date();
            const timeLeft = expiryTime - now;
            
            if (timeLeft <= 0) {
                clearInterval(updateInterval);
                if (window.onAcceptanceTimerExpired) {
                    window.onAcceptanceTimerExpired(bookingId);
                }
                return;
            }
            
            const minutes = Math.floor(timeLeft / 60000);
            const seconds = Math.floor((timeLeft % 60000) / 1000);
            
            if (window.updateAcceptanceTimer) {
                window.updateAcceptanceTimer(bookingId, `${minutes}:${seconds.toString().padStart(2, '0')}`);
            }
        }, 1000);
    }

    showNotification(title, message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <strong>${title}</strong>
                <p>${message}</p>
                <button class="notification-close" onclick="this.parentElement.remove()">×</button>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
        
        // Add styles if not already added
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 9999;
                    min-width: 300px;
                    max-width: 400px;
                    animation: slideIn 0.3s ease-out;
                }
                .notification-success { border-left: 4px solid #10b981; }
                .notification-warning { border-left: 4px solid #f59e0b; }
                .notification-error { border-left: 4px solid #ef4444; }
                .notification-info { border-left: 4px solid #3b82f6; }
                .notification-content {
                    padding: 16px;
                    position: relative;
                }
                .notification-close {
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    background: none;
                    border: none;
                    font-size: 18px;
                    cursor: pointer;
                    color: #6b7280;
                }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
    }

    disconnect() {
        if (this.echo) {
            this.echo.disconnect();
            this.isConnected = false;
        }
    }
}

// Initialize WebSocket client
window.eBookingWS = new EBookingWebSocket();

// Global helper functions
window.subscribeToRiderChannel = (riderId) => {
    window.eBookingWS.subscribeToRiderChannel(riderId);
};

window.subscribeToBookingChannel = (bookingId) => {
    window.eBookingWS.subscribeToBookingChannel(bookingId);
};

window.showWebSocketNotification = (title, message, type) => {
    window.eBookingWS.showNotification(title, message, type);
};
