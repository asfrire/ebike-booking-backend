<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Bike Booking - Simple WebSocket Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.socket.io/4.7.4/socket.io.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center mb-8 text-blue-600">
            🏍️ E-Bike Booking - Simple WebSocket Demo
        </h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Rider Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">🏃 Rider Dashboard</h2>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rider ID</label>
                    <input type="number" id="riderId" class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                           placeholder="Enter rider ID" value="1">
                </div>
                
                <div class="mb-4">
                    <button onclick="connectAsRider()" 
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                        🔄 Connect as Rider
                    </button>
                </div>
                
                <div id="riderStatus" class="hidden">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        ✅ Connected to real-time updates
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-4">
                        📍 Queue Position: <span id="queuePosition">-</span>
                    </div>
                </div>
            </div>
            
            <!-- Customer Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">👤 Customer Dashboard</h2>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Booking ID</label>
                    <input type="number" id="bookingId" class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                           placeholder="Enter booking ID" value="1">
                </div>
                
                <div class="mb-4">
                    <button onclick="connectAsCustomer()" 
                            class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                        🔄 Connect as Customer
                    </button>
                </div>
                
                <div id="customerStatus" class="hidden">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        ✅ Connected to real-time updates
                    </div>
                    
                    <div class="bg-purple-50 border border-purple-200 text-purple-700 px-4 py-3 rounded mb-4">
                        📊 Booking Status: <span id="bookingStatus">-</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Connection Status -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">🔌 Connection Status</h2>
            <div id="connectionStatus" class="bg-gray-100 border border-gray-200 text-gray-700 px-4 py-3 rounded">
                ⏳ Connecting to WebSocket...
            </div>
        </div>
        
        <!-- Laravel Logs -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">📋 Laravel Logs</h2>
            <div class="mb-4">
                <button onclick="refreshLogs()" 
                        class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                    🔄 Refresh Logs
                </button>
                <button onclick="clearLogs()" 
                        class="bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition ml-2">
                    🗑️ Clear Logs
                </button>
            </div>
            <div id="logContainer" class="bg-gray-900 text-green-400 p-4 rounded-md font-mono text-sm max-h-96 overflow-y-auto">
                <div class="text-gray-400">Loading logs...</div>
            </div>
        </div>
    </div>

    <script src="/js/simple-websocket.js"></script>
    <script>
        // Log viewing functions
        function refreshLogs() {
            fetch('/api/logs')
                .then(response => response.json())
                .then(data => {
                    const logContainer = document.getElementById('logContainer');
                    if (data.logs && data.logs.length > 0) {
                        logContainer.innerHTML = data.logs.map(log => 
                            `<div class="mb-1">${log}</div>`
                        ).join('');
                    } else {
                        logContainer.innerHTML = '<div class="text-gray-400">No logs found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching logs:', error);
                    document.getElementById('logContainer').innerHTML = 
                        '<div class="text-red-400">Error loading logs</div>';
                });
        }

        function clearLogs() {
            if (confirm('Are you sure you want to clear all logs?')) {
                fetch('/api/logs', { method: 'DELETE' })
                    .then(() => {
                        document.getElementById('logContainer').innerHTML = 
                            '<div class="text-gray-400">Logs cleared</div>';
                    })
                    .catch(error => {
                        console.error('Error clearing logs:', error);
                    });
            }
        }

        // Auto-refresh logs every 5 seconds
        setInterval(refreshLogs, 5000);

        // Initial log load
        refreshLogs();
        
        // Update connection status
        window.simpleWS.socket.on('connect', () => {
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                connectionStatus.innerHTML = 
                    '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">✅ WebSocket Connected Successfully!</div>';
            }
        });

        window.simpleWS.socket.on('disconnect', () => {
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                connectionStatus.innerHTML = 
                    '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">❌ WebSocket Disconnected</div>';
            }
        });
    </script>
</body>
</html>
