# E-Bike Booking System - Backend API

A production-ready Laravel API for an E-Bike Booking System with role-based access, real-time rider assignments, and free deployment architecture.

## 🚀 Features

- **🔐 Sanctum Authentication** - Token-based API security
- **👥 Role-Based Access Control** - Admin, Rider, Customer roles
- **🏍️ Rider Queue System** - FIFO queue with automatic assignments
- **⏰ Timeout Handling** - 3-minute assignment expiration with auto-reassignment
- **� Late Acceptance** - Support for riders accepting after timeout
- **� Multi-Passenger** - Handle bookings with multiple passengers
- **📱 Push Notifications** - Firebase Cloud Messaging integration
- **� Free Hosting Ready** - Optimized for Render.com + Supabase
- **🧪 Comprehensive Tests** - Full test coverage for all features

## 📋 System Requirements

- PHP 8.1+
- Laravel 10+
- PostgreSQL 12+
- Redis (optional, for caching)
- Firebase Cloud Messaging (for push notifications)

## 🛠️ Installation

1. Clone the repository
```bash
git clone https://github.com/asfrire/ebike-booking-backend.git
cd ebike-booking-backend
```

2. Install dependencies
```bash
composer install
npm install
```

3. Environment setup
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure database in `.env`
```env
DB_CONNECTION=pgsql
DB_HOST=your-supabase-host.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-password
```

5. Run migrations
```bash
php artisan migrate
```

6. Start the server
```bash
php artisan serve
```

## 🏗️ Architecture

### Database Schema

- **users** - User accounts with roles (admin, rider, customer)
- **riders** - Rider profiles with queue positions and capacity
- **bookings** - Booking requests with passenger counts
- **booking_riders** - Assignment relationships with status tracking

### API Endpoints

#### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/logout` - User logout

#### Customer Routes
- `GET /api/bookings` - List customer's bookings
- `POST /api/bookings` - Create new booking
- `GET /api/bookings/{booking}` - View booking details
- `PUT /api/bookings/{booking}/cancel` - Cancel booking

#### Rider Routes
- `GET /api/rider/bookings` - List rider's bookings
- `POST /api/rider/bookings/{booking}/accept` - Accept booking
- `POST /api/rider/go-online` - Enter rider queue
- `POST /api/rider/go-offline` - Exit rider queue
- `GET /api/rider/queue` - Get queue position
- `GET /api/rider/status` - Get rider status

#### Admin Routes
- `GET /api/admin/bookings` - All bookings
- `GET /api/admin/riders` - All riders
- `PUT /api/admin/riders/{rider}/capacity` - Update rider capacity

## 🔄 Business Logic

### Rider Assignment System
1. **FIFO Queue**: Riders served in order of queue position
2. **Capacity Matching**: Assign riders based on passenger count
3. **Timeout Handling**: 3-minute expiration with auto-reassignment
4. **Late Acceptance**: Riders can accept after timeout if seats available

### Booking Flow
1. Customer creates booking with pickup/dropoff locations and passenger count
2. System assigns available riders from queue
3. Riders receive push notifications for assignments
4. Riders accept or reject assignments
5. Booking status updates based on acceptance

### Timeout Management
- **No Background Workers**: Checks happen during API calls
- **Automatic Reassignment**: Expired assignments return to queue
- **Queue Penalty**: Timed-out riders moved to queue end

## 🧪 Testing

Run the test suite:
```bash
php artisan test
```

Key test files:
- `DatabaseTest.php` - Database structure verification
- `BookingLogicTest.php` - Booking assignment logic
- `AcceptLogicTest.php` - Acceptance with DB transactions
- `TimeoutLogicTest.php` - Timeout handling
- `LateAcceptanceTest.php` - Late acceptance rules
- `RiderQueueSystemTest.php` - Queue management

## 🚀 Deployment

### Render.com (Backend)
1. Connect GitHub repository
2. Configure environment variables
3. Deploy with PHP 8.1+ and PostgreSQL

### Supabase (Database)
1. Create new project
2. Get connection string
3. Configure in `.env`

### Environment Variables
```env
DB_CONNECTION=pgsql
DB_HOST=your-host.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-password

FCM_SERVER_KEY=your-fcm-server-key
FCM_SENDER_ID=your-fcm-sender-id
```

## 📱 Push Notifications

Firebase Cloud Messaging integration:
- Rider assignment notifications
- Booking status updates
- Customer notifications

## 🔧 Configuration

### Rider Capacity
Default rider capacity is 2 passengers. Can be updated per rider.

### Assignment Timeout
Default timeout is 3 minutes. Configurable in `BookingService.php`.

### Queue Behavior
- FIFO ordering maintained
- Online/offline status tracking
- Automatic reordering on offline/timeout

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Make changes with tests
4. Submit pull request

## 📄 License

This project is open-source and available under the [MIT License](LICENSE).

## 🔗 Related Projects

- [E-Bike Booking Frontend](https://github.com/asfrire/ebike-booking-frontend) - React Native app
- [API Documentation](API_DOCUMENTATION.md) - Detailed API reference

## � Support

For questions or support:
- Create an issue on GitHub
- Email: asfrire@example.com
For technical support:
- Review API documentation
- Check deployment guide
- Create GitHub issue

---

**Built with ❤️ using Laravel 10+**
=======
# ebike-booking-backend
>>>>>>> 6bb8c461e4c57ce602ec12ae06848906fe560d9f
