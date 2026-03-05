const http = require('http');

// Simple test to check if WebSocket server is responding
const options = {
    hostname: 'localhost',
    port: 6004,
    path: '/socket.io/',
    method: 'GET',
    headers: {
        'Connection': 'Upgrade',
        'Upgrade': 'websocket'
    }
};

const req = http.request(options, (res) => {
    console.log('✅ WebSocket server is responding!');
    console.log('Status:', res.statusCode);
    console.log('Headers:', res.headers);
    
    if (res.statusCode === 400 || res.headers.upgrade === 'websocket') {
        console.log('🎉 WebSocket server is working correctly!');
    }
});

req.on('error', (err) => {
    console.log('❌ Cannot connect to WebSocket server:', err.message);
    console.log('💡 Make sure WebSocket server is running: node websocket-server.cjs');
});

req.end();
