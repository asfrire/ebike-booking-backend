// Simple WebSocket client without Laravel Echo
class SimpleWebSocket {
    constructor() {
        this.socket = null;
        this.isConnected = false;
    }

    connect() {
        console.log('🔌 Attempting to connect to WebSocket server...');
        console.log('📡 Server URL: http://localhost:6004');
        
        this.socket = io('http://localhost:6004', {
            transports: ['websocket', 'polling'],
            timeout: 5000
        });
        
        this.socket.on('connect', () => {
            this.isConnected = true;
            console.log('✅ WebSocket Connected!');
            console.log('🔌 Socket ID:', this.socket.id);
            
            // Safely update UI elements
            const riderStatus = document.getElementById('riderStatus');
            const customerStatus = document.getElementById('customerStatus');
            const adminStatus = document.getElementById('adminStatus');
            
            if (riderStatus) riderStatus.classList.remove('hidden');
            if (customerStatus) customerStatus.classList.remove('hidden');
            if (adminStatus) adminStatus.classList.remove('hidden');
        });

        this.socket.on('connect_error', (error) => {
            console.error('❌ WebSocket Connection Error:', error);
            console.log('🔍 Possible causes:');
            console.log('  - WebSocket server not running on port 6004');
            console.log('  - CORS issues');
            console.log('  - Network connectivity problems');
        });

        this.socket.on('disconnect', () => {
            this.isConnected = false;
            console.log('❌ WebSocket Disconnected');
        });

        // Listen for events
        this.socket.on('rider.position.updated', (data) => {
            console.log('📍 Rider position updated:', data);
            if (window.updateRiderQueue) {
                window.updateRiderQueue(data);
            }
        });

        this.socket.on('booking.assigned', (data) => {
            console.log('🏍️ Booking assigned:', data);
            if (window.onBookingAssigned) {
                window.onBookingAssigned(data);
            }
        });

        this.socket.on('booking.status.updated', (data) => {
            console.log('📊 Booking status updated:', data);
            if (window.updateBookingStatus) {
                window.updateBookingStatus(data);
            }
        });
    }

    subscribeToRider(riderId) {
        if (this.isConnected) {
            this.socket.emit('subscribe-rider', { riderId });
        }
    }

    subscribeToBooking(bookingId) {
        if (this.isConnected) {
            this.socket.emit('subscribe-booking', { bookingId });
        }
    }

    subscribeToAdmin() {
        if (this.isConnected) {
            this.socket.emit('subscribe-admin');
        }
    }
}

// Initialize simple WebSocket
window.simpleWS = new SimpleWebSocket();

// Override connection functions
window.connectAsRider = function() {
    const riderId = document.getElementById('riderId')?.value;
    if (riderId && window.simpleWS) {
        window.simpleWS.subscribeToRider(riderId);
        const riderStatus = document.getElementById('riderStatus');
        const customerStatus = document.getElementById('customerStatus');
        const adminStatus = document.getElementById('adminStatus');
        
        if (riderStatus) riderStatus.classList.remove('hidden');
        if (customerStatus) customerStatus.classList.add('hidden');
        if (adminStatus) adminStatus.classList.add('hidden');
    }
};

window.connectAsCustomer = function() {
    const bookingId = document.getElementById('bookingId')?.value;
    if (bookingId && window.simpleWS) {
        window.simpleWS.subscribeToBooking(bookingId);
        const riderStatus = document.getElementById('riderStatus');
        const customerStatus = document.getElementById('customerStatus');
        const adminStatus = document.getElementById('adminStatus');
        
        if (customerStatus) customerStatus.classList.remove('hidden');
        if (riderStatus) riderStatus.classList.add('hidden');
        if (adminStatus) adminStatus.classList.add('hidden');
    }
};

window.connectAsAdmin = function() {
    if (window.simpleWS) {
        window.simpleWS.subscribeToAdmin();
        const riderStatus = document.getElementById('riderStatus');
        const customerStatus = document.getElementById('customerStatus');
        const adminStatus = document.getElementById('adminStatus');
        
        if (adminStatus) adminStatus.classList.remove('hidden');
        if (riderStatus) riderStatus.classList.add('hidden');
        if (customerStatus) customerStatus.classList.add('hidden');
    }
};

// Auto-connect
window.simpleWS.connect();
