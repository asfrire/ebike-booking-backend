<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Bike Booking - Real-Time Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.socket.io/4.7.4/socket.io.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.0/dist/echo.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center mb-8 text-blue-600">
            🏍️ E-Bike Booking - Real-Time Demo
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
                    
                    <div id="bookingAlert" class="hidden bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                        🏍️ <span id="bookingAlertText"></span>
                    </div>
                    
                    <div id="acceptanceTimer" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        ⏰ Time to accept: <span id="timerText">-</span>
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
                    
                    <div id="bookingDetails" class="hidden bg-gray-50 border border-gray-200 text-gray-700 px-4 py-3 rounded">
                        <div class="text-sm space-y-2">
                            <div>📍 Pickup: <span id="pickupLocation">-</span></div>
                            <div>🎯 Dropoff: <span id="dropoffLocation">-</span></div>
                            <div>👥 Passengers: <span id="passengers">-</span></div>
                            <div>💰 Total Fare: <span id="totalFare">-</span></div>
                            <div>🏍️ Riders: <span id="riderCount">-</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Admin Section -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">👨‍💼 Admin Dashboard</h2>
            
            <div class="mb-4">
                <button onclick="connectAsAdmin()" 
                        class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition">
                    🔄 Connect as Admin
                </button>
            </div>
            
            <div id="adminStatus" class="hidden">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    ✅ Connected to real-time updates
                </div>
                
                <div id="adminFeed" class="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-3 rounded max-h-64 overflow-y-auto">
                    <h3 class="font-semibold mb-2">📡 Live Activity Feed</h3>
                    <div id="activityFeed" class="space-y-2 text-sm">
                        <div class="text-gray-500">Waiting for activity...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/websocket-client.js"></script>
    <script>
        let currentRole = null;
        let activityCount = 0;

        // Rider functions
        function connectAsRider() {
            const riderId = document.getElementById('riderId').value;
            if (!riderId) {
                alert('Please enter a rider ID');
                return;
            }
            
            currentRole = 'rider';
            window.subscribeToRiderChannel(riderId);
            
            document.getElementById('riderStatus').classList.remove('hidden');
            document.getElementById('customerStatus').classList.add('hidden');
            document.getElementById('adminStatus').classList.add('hidden');
        }

        // Customer functions
        function connectAsCustomer() {
            const bookingId = document.getElementById('bookingId').value;
            if (!bookingId) {
                alert('Please enter a booking ID');
                return;
            }
            
            currentRole = 'customer';
            window.subscribeToBookingChannel(bookingId);
            
            document.getElementById('customerStatus').classList.remove('hidden');
            document.getElementById('riderStatus').classList.add('hidden');
            document.getElementById('adminStatus').classList.add('hidden');
        }

        // Admin functions
        function connectAsAdmin() {
            currentRole = 'admin';
            
            document.getElementById('adminStatus').classList.remove('hidden');
            document.getElementById('riderStatus').classList.add('hidden');
            document.getElementById('customerStatus').classList.add('hidden');
        }

        // WebSocket event handlers
        window.onWebSocketConnected = function() {
            console.log('WebSocket connected successfully!');
        };

        window.updateRiderQueue = function(data) {
            if (currentRole === 'rider' && data.rider_id == document.getElementById('riderId').value) {
                document.getElementById('queuePosition').textContent = data.new_position;
            }
        };

        window.onBookingAssigned = function(data) {
            if (currentRole === 'rider') {
                document.getElementById('bookingAlert').classList.remove('hidden');
                document.getElementById('bookingAlertText').textContent = 
                    `${data.booking.pax} passengers from ${data.booking.pickup_location}`;
                
                // Show booking details
                addActivityEntry('🏍️ New Booking', `Rider ${data.rider_id} assigned to booking ${data.booking.id}`);
            }
        };

        window.onAcceptanceTimerExpired = function(bookingId) {
            if (currentRole === 'rider') {
                document.getElementById('acceptanceTimer').classList.add('hidden');
                document.getElementById('bookingAlert').innerHTML = 
                    '⏰ Booking assignment expired!';
                
                addActivityEntry('⏰ Timeout', `Booking ${bookingId} acceptance timer expired`);
            }
        };

        window.updateAcceptanceTimer = function(bookingId, timeLeft) {
            if (currentRole === 'rider') {
                document.getElementById('acceptanceTimer').classList.remove('hidden');
                document.getElementById('timerText').textContent = timeLeft;
            }
        };

        window.updateBookingStatus = function(data) {
            if (currentRole === 'customer' && data.booking_id == document.getElementById('bookingId').value) {
                document.getElementById('bookingStatus').textContent = data.status;
                
                // Show booking details
                document.getElementById('bookingDetails').classList.remove('hidden');
                document.getElementById('pickupLocation').textContent = data.pickup_location || '-';
                document.getElementById('dropoffLocation').textContent = data.dropoff_location || '-';
                document.getElementById('passengers').textContent = data.pax || '-';
                document.getElementById('totalFare').textContent = data.total_fare ? `₱${data.total_fare}` : '-';
                document.getElementById('riderCount').textContent = data.rider_count || '-';
                
                addActivityEntry('📊 Status Update', `Booking ${data.booking_id} status changed to ${data.status}`);
            }
        };

        function addActivityEntry(type, message) {
            if (currentRole !== 'admin') return;
            
            activityCount++;
            const feed = document.getElementById('activityFeed');
            const entry = document.createElement('div');
            entry.className = 'border-l-4 border-blue-400 pl-4 py-2';
            entry.innerHTML = `
                <div class="font-semibold">${type}</div>
                <div class="text-gray-600">${message}</div>
                <div class="text-xs text-gray-500">${new Date().toLocaleTimeString()}</div>
            `;
            
            feed.insertBefore(entry, feed.firstChild);
            
            // Keep only last 10 entries
            while (feed.children.length > 10) {
                feed.removeChild(feed.lastChild);
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 E-Bike Booking Real-Time Demo Loaded');
        });
    </script>
</body>
</html>
