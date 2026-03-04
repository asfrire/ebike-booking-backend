# E-Bike Booking System - Backend API

A production-ready Laravel API for an E-Bike Booking System with role-based access, real-time rider assignments, and free deployment architecture.

## 🚀 Features

- **🔐 Sanctum Authentication** - Token-based API security
- **👥 Role-Based Access Control** - Admin, Rider, Customer roles
- **🏍️ Rider Queue System** - FIFO queue with automatic assignments
- **⏰ 3-Minute Expiration** - Auto reassignment on timeout
- **👨‍👩‍👧‍👦 Multi-Passenger Support** - 2-5 passengers per e-bike
- **🔄 Late Acceptance** - Accept expired assignments if seats available
- **📱 Push Notifications** - Firebase Cloud Messaging ready
- **🏗️ Free Deployment** - Render + Supabase architecture
- **🔒 Race Condition Prevention** - Database transactions and row locking

## 📋 Requirements

- PHP 8.2+
- PostgreSQL (Supabase recommended)
- Composer
- Laravel 10+

## 🛠️ Installation

### 1. Clone Repository
```bash
git clone <repository-url>
cd ebike-booking-system/ebike-booking-backend
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Database Configuration
Update `.env` with your database credentials:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ebike_booking
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 5. Run Migrations
```bash
php artisan migrate
```

### 6. Start Development Server
```bash
php artisan serve
```

## 🏗️ Architecture

### Database Schema
- **Users** - Authentication and role management
- **Riders** - E-bike driver profiles and queue management
- **Bookings** - Customer booking requests
- **Booking_Riders** - Rider assignments with expiration tracking

### Business Logic
- **FIFO Queue** - Riders served in order of availability
- **Smart Assignment** - Optimal rider capacity utilization
- **Timeout Handling** - 3-minute expiration with auto reassignment
- **Late Acceptance** - Accept expired assignments if seats not taken

## 📡 API Endpoints

### Authentication
- `POST /api/register` - User registration
- `POST /api/login` - User login
- `POST /api/logout` - User logout
- `GET /api/profile` - Get user profile

### Customer Routes
- `GET /api/bookings` - List customer bookings
- `POST /api/bookings` - Create new booking
- `GET /api/bookings/{id}` - View booking details
- `PUT /api/bookings/{id}/cancel` - Cancel booking

### Rider Routes
- `GET /api/rider/bookings` - List rider assignments
- `POST /api/rider/bookings/{id}/accept` - Accept assignment
- `POST /api/rider/bookings/{id}/reject` - Reject assignment
- `POST /api/rider/go-online` - Enter queue
- `POST /api/rider/go-offline` - Exit queue
- `GET /api/rider/queue` - Get queue position
- `GET /api/rider/status` - Get rider status

### Admin Routes
- `GET /api/admin/bookings` - List all bookings
- `GET /api/admin/riders` - List all riders
- `PUT /api/admin/riders/{id}/capacity` - Update rider capacity

## 🧪 Testing

### Run Tests
```bash
php artisan test
```

### API Testing Examples
```bash
# Register Admin
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Admin User",
    "email": "admin@test.com",
    "password": "password123",
    "role": "admin"
  }'

# Create Booking (as Customer)
curl -X POST http://localhost:8000/api/bookings \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "pickup_location": "123 Main St",
    "dropoff_location": "456 Oak Ave",
    "pax": 3
  }'

# Rider Go Online
curl -X POST http://localhost:8000/api/rider/go-online \
  -H "Authorization: Bearer RIDER_TOKEN"
```

## 🚀 Deployment

### Render.com + Supabase (Free)

1. **Setup Supabase**
   - Create free project at [supabase.com](https://supabase.com)
   - Get database connection string

2. **Setup Render**
   - Connect GitHub repository
   - Configure environment variables
   - Deploy with automatic migrations

3. **Environment Variables**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   DB_CONNECTION=pgsql
   DB_HOST=your-project.supabase.co
   DB_PASSWORD=your-supabase-password
   SANCTUM_STATEFUL_DOMAINS=https://your-app.onrender.com
   ```

See [DEPLOYMENT.md](../DEPLOYMENT.md) for detailed instructions.

## 🔧 Configuration

### Rider Capacity
Default rider capacity is 2 passengers (configurable per rider):
```bash
# Update rider capacity (Admin only)
curl -X PUT https://your-api.onrender.com/api/admin/riders/{id}/capacity \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -d '{"capacity": 4}'
```

### Timeout Duration
Default timeout is 3 minutes (configurable in `BookingService.php`):
```php
'expires_at' => now()->addMinutes(3)
```

### Queue Management
- **FIFO ordering** based on queue position
- **Auto reordering** when riders go offline
- **Timeout handling** moves riders to end of queue

## 🔒 Security Features

- **Token Authentication** - Laravel Sanctum
- **Role-Based Access** - Middleware protection
- **Input Validation** - Request validation for all endpoints
- **SQL Injection Prevention** - Eloquent ORM
- **CORS Configuration** - Cross-origin security
- **Rate Limiting** - Prevent API abuse

## 📱 Push Notifications

Firebase Cloud Messaging integration ready:
```env
FCM_PROJECT_ID=your-project-id
FCM_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----..."
FCM_CLIENT_EMAIL=firebase-adminsdk@your-project.iam.gserviceaccount.com
```

## 🐛 Troubleshooting

### Common Issues
1. **Migration fails** - Check database credentials
2. **401 Unauthorized** - Verify token and headers
3. **403 Forbidden** - Check user role permissions
4. **422 Validation Error** - Review request format

### Debug Commands
```bash
# Check Laravel status
php artisan about

# Test database connection
php artisan tinker
>>> DB::connection()->getPdo()

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## 📊 Monitoring

### Health Check
```bash
curl https://your-api.onrender.com/health
```

### Logs
- Render dashboard logs
- Laravel logs (`storage/logs/laravel.log`)
- Database query logs

## 🔄 Version Control

### Git Workflow
```bash
git add .
git commit -m "Feature: Add rider queue management"
git push origin main
```

### Branch Strategy
- `main` - Production ready code
- `develop` - Integration branch
- `feature/*` - Feature branches

## 📝 License

This project is open-source and available under the [MIT License](LICENSE).

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Make changes
4. Add tests
5. Submit pull request

## 📞 Support

For technical support:
- Review API documentation
- Check deployment guide
- Create GitHub issue

---

**Built with ❤️ using Laravel 10+**
