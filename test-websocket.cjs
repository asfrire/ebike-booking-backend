const io = require('socket.io-client');

// Connect to WebSocket server
const socket = io('http://localhost:6004');

socket.on('connect', () => {
    console.log('✅ Test client connected');
    
    // Simulate rider position update
    socket.emit('test-event', {
        message: 'WebSocket is working!',
        timestamp: new Date().toISOString()
    });
});

socket.on('test-event', (data) => {
    console.log('📡 Received test event:', data);
});

socket.on('disconnect', () => {
    console.log('❌ Test client disconnected');
});
