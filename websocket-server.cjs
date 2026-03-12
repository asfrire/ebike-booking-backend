const http = require('http');
const { Server } = require('socket.io');
const fs = require('fs');
const path = require('path');

// Laravel logging function
function logToLaravel(message, level = 'info') {
    const timestamp = new Date().toISOString();
    const logMessage = `[${timestamp}] ${level.toUpperCase()}: ${message}\n`;
    
    // Append to Laravel log file
    const logPath = path.join(__dirname, 'storage/logs/laravel.log');
    fs.appendFileSync(logPath, logMessage);
    
    // Also console log
    console.log(logMessage.trim());
}

const server = http.createServer();
const io = new Server(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// Store connected clients
const connectedClients = new Map();

io.on('connection', (socket) => {
    logToLaravel(`🔌 Client connected: ${socket.id}`, 'info');
    
    // Handle authentication
    socket.on('authenticate', (data) => {
        logToLaravel(`🔐 Authentication attempt: ${data.token ? 'Token provided' : 'No token'} (Role: ${data.role || 'guest'})`, 'info');
        
        // Store client info
        connectedClients.set(socket.id, {
            authenticated: !!data.token,
            role: data.role || 'guest',
            userId: data.userId || null,
            socket: socket
        });
        
        socket.emit('authenticated', {
            success: !!data.token,
            message: !!data.token ? 'Authentication successful' : 'Authentication failed'
        });
    });

    // Handle rider subscriptions
    socket.on('subscribe-rider', (data) => {
        const client = connectedClients.get(socket.id);
        if (client && client.authenticated && client.role === 'rider' && client.userId === data.riderId) {
            socket.join(`rider-${data.riderId}`);
            logToLaravel(`🏍️ Rider ${data.riderId} subscribed to channel: rider-${data.riderId}`, 'info');
        } else {
            logToLaravel(`❌ Unauthorized rider subscription attempt: ${JSON.stringify(data)}`, 'warning');
        }
    });

    // Handle customer subscriptions
    socket.on('subscribe-booking', (data) => {
        const client = connectedClients.get(socket.id);
        if (client && client.authenticated && client.role === 'customer') {
            socket.join(`booking-${data.bookingId}`);
            logToLaravel(`📊 Customer subscribed to booking: ${data.bookingId}`, 'info');
        } else {
            logToLaravel(`❌ Unauthorized booking subscription attempt: ${JSON.stringify(data)}`, 'warning');
        }
    });

    // Handle admin subscription
    socket.on('subscribe-admin', () => {
        const client = connectedClients.get(socket.id);
        if (client && client.authenticated && client.role === 'admin') {
            socket.join('admin-dashboard');
            logToLaravel(`👨‍💼 Admin subscribed to dashboard`, 'info');
        } else {
            logToLaravel(`❌ Unauthorized admin subscription attempt`, 'warning');
        }
    });

    // Handle disconnection
    socket.on('disconnect', () => {
        logToLaravel(`❌ Client disconnected: ${socket.id}`, 'info');
        connectedClients.delete(socket.id);
    });

    // Handle test messages
    socket.on('test-message', (data) => {
        logToLaravel(`📨 Test message received: ${JSON.stringify(data)}`, 'info');
        
        // Echo back to all clients
        io.emit('test-message', {
            ...data,
            echo: true,
            serverTime: new Date().toISOString()
        });
    });
});

// Broadcast functions (these would be called from your Laravel backend)
function broadcastToRider(riderId, event, data) {
    io.to(`rider-${riderId}`).emit(event, data);
    logToLaravel(`📡 Sent to rider ${riderId}: ${event} - ${JSON.stringify(data)}`, 'info');
}

function broadcastToBooking(bookingId, event, data) {
    io.to(`booking-${bookingId}`).emit(event, data);
    logToLaravel(`📡 Sent to booking ${bookingId}: ${event} - ${JSON.stringify(data)}`, 'info');
}

function broadcastToAdmin(event, data) {
    io.to('admin-dashboard').emit(event, data);
    logToLaravel(`📡 Sent to admin: ${event} - ${JSON.stringify(data)}`, 'info');
}

function broadcastToRiderQueue(event, data) {
    io.emit(event, data);
    logToLaravel(`📡 Sent to rider queue: ${event} - ${JSON.stringify(data)}`, 'info');
}

server.on('request', (req, res) => {
    if (req.method === 'POST' && req.url === '/emit') {
        io.emit('riders-updated');
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true }));
    } else {
        res.writeHead(404);
        res.end();
    }
});

const PORT = process.env.PORT || 6004;
server.listen(PORT, () => {
    console.log(`🚀 WebSocket Server running on port ${PORT}`);
    console.log(`📡 Server URL: http://localhost:${PORT}`);
    console.log('📡 Ready for real-time connections!');
    console.log('🔍 Test with: http://localhost:8000/simple-websocket-demo');
    console.log('🌐 CORS enabled for all origins');
    console.log('📝 Logs will be written to: storage/logs/laravel.log');
    
    // Test log function
    logToLaravel('🚀 WebSocket server started successfully', 'info');
});
