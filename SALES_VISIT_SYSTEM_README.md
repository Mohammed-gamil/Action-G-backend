# Sales Visit System - Backend Implementation Guide

## Overview
Complete backend implementation for the Sales Visit Management System integrated into the Action-G purchase request system.

## Database Structure

### Tables Created
1. **tbl_business_types** - Business type categories (Retail, Restaurant, Cafe, etc.)
2. **tbl_product_categories** - Product categories (Electronics, Fashion, Food, etc.)
3. **tbl_clients** - Client information managed by sales reps
4. **tbl_visits** - Sales visit records with comprehensive details
5. **tbl_visit_files** - Photo and video attachments for visits
6. **tbl_visit_status_history** - Audit trail of status changes

### Visit Status Workflow
```
draft → submitted → pending_review → action_required → approved → quotation_sent → closed_won/closed_lost
```

## Installation Steps

### 1. Run Migration
```bash
cd Action-G-backend
php artisan migrate
```

This will create all tables and seed default business types and product categories.

### 2. Add SALES_REP Role to User System

Update `tbl_users` table to support the SALES_REP role:

```sql
-- If your users table has a role column, add SALES_REP to enum
ALTER TABLE tbl_users MODIFY COLUMN role ENUM('USER', 'DIRECT_MANAGER', 'ACCOUNTANT', 'ADMIN', 'FINAL_MANAGER', 'SALES_REP', 'SUPER_ADMIN') DEFAULT 'USER';
```

### 3. Create Sales Rep Test User
```sql
INSERT INTO tbl_users (name, email, password, role, is_active, created_at, updated_at) 
VALUES ('Test Sales Rep', 'salesrep@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SALES_REP', 1, NOW(), NOW());
-- Password is 'password' (bcrypt hashed)
```

### 4. Configure File Storage
Ensure the storage is properly linked:
```bash
php artisan storage:link
```

Update `.env` for file uploads:
```env
FILESYSTEM_DISK=public
```

## API Endpoints

### Visit Management

#### Get Visits (Paginated, Filtered)
```http
GET /api/visits?per_page=15&status=submitted&search=client&date_from=2025-01-01&date_to=2025-12-31
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "client_id": 1,
      "rep_id": 5,
      "visit_date": "2025-01-20",
      "status": "submitted",
      "rep_name": "John Doe",
      "client": {
        "id": 1,
        "store_name": "ABC Store",
        "contact_person": "Ahmed",
        "mobile": "+966501234567",
        "business_type": {
          "id": 1,
          "name_en": "Retail Store",
          "name_ar": "متجر تجزئة"
        }
      },
      "files": [],
      "created_at": "2025-01-20T10:30:00.000000Z"
    }
  ],
  "pagination": {
    "total": 50,
    "per_page": 15,
    "current_page": 1,
    "last_page": 4
  }
}
```

#### Get Single Visit
```http
GET /api/visits/{id}
Authorization: Bearer {token}
```

#### Create Visit
```http
POST /api/visits
Authorization: Bearer {token}
Content-Type: application/json

{
  "client_id": 1,
  "visit_date": "2025-01-20",
  "has_previous_agency": false,
  "needs_voiceover": true,
  "voiceover_language": "Arabic",
  "shooting_goals": ["social_media", "in_store"],
  "service_types": ["product_photo", "video"],
  "preferred_location": "client_location",
  "product_category_id": 2,
  "product_description": "Fashion items for spring collection",
  "estimated_product_count": 50,
  "preferred_shoot_date": "2025-02-01",
  "budget_range": "10000-15000 SAR",
  "rep_notes": "Client interested in monthly packages"
}
```

#### Update Visit
```http
PUT /api/visits/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "admin_notes": "Approved for quotation preparation",
  "rep_notes": "Follow up scheduled for next week"
}
```

#### Update Visit Status (Admin Only)
```http
POST /api/visits/{id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "approved",
  "notes": "Visit approved by manager",
  "admin_notes": "Good potential client"
}
```

#### Get Visit Status History
```http
GET /api/visits/{id}/history
Authorization: Bearer {token}
```

#### Get Visit Statistics
```http
GET /api/visits/stats?rep_id=5&date_from=2025-01-01
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 125,
    "draft": 10,
    "submitted": 15,
    "pending_review": 8,
    "approved": 30,
    "quotation_sent": 25,
    "closed_won": 20,
    "closed_lost": 17,
    "this_week": 5,
    "this_month": 22
  }
}
```

### Client Management

#### Search Clients (Autocomplete)
```http
GET /api/visits/clients/search?q=ABC
Authorization: Bearer {token}
```

#### Get All Clients
```http
GET /api/visits/clients?per_page=20&search=store
Authorization: Bearer {token}
```

#### Create Client
```http
POST /api/visits/clients
Authorization: Bearer {token}
Content-Type: application/json

{
  "store_name": "New Store",
  "contact_person": "Ahmed Ali",
  "mobile": "+966501234567",
  "mobile_2": "+966507654321",
  "address": "Riyadh, King Fahd Road",
  "business_type_id": 1
}
```

### File Management

#### Upload Visit File
```http
POST /api/visits/{id}/files
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: [binary file] (max 50MB, jpg/jpeg/png/mp4/mov/avi)
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "visit_id": 1,
    "file_type": "photo",
    "original_filename": "product_photo.jpg",
    "storage_url": "/storage/visits/1/1737367200_abc123.jpg",
    "file_size_bytes": 2048576,
    "uploaded_at": "2025-01-20T10:30:00.000000Z"
  },
  "message": "File uploaded successfully"
}
```

#### Delete Visit File
```http
DELETE /api/visits/{visitId}/files/{fileId}
Authorization: Bearer {token}
```

### Reference Data

#### Get Business Types
```http
GET /api/visits/business-types
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {"id": 1, "name_en": "Retail Store", "name_ar": "متجر تجزئة", "sort_order": 1},
    {"id": 2, "name_en": "Restaurant", "name_ar": "مطعم", "sort_order": 2}
  ]
}
```

#### Get Product Categories
```http
GET /api/visits/product-categories
Authorization: Bearer {token}
```

## Authorization & Permissions

### Sales Rep (SALES_REP)
- Create visits
- Edit own draft visits only
- View only own visits
- Upload/delete files for own visits
- Create clients

### Admin/Manager (ADMIN, SUPER_ADMIN)
- View all visits
- Change visit status
- Edit any visit
- View statistics for all reps
- Full access to all endpoints

## Models & Relationships

### Visit Model
```php
Visit::with(['client.businessType', 'productCategory', 'salesRep', 'files', 'statusHistory'])
```

### Relationships
- Visit → Client (belongsTo)
- Visit → User (salesRep, belongsTo)
- Visit → ProductCategory (belongsTo)
- Visit → VisitFile (hasMany)
- Visit → VisitStatusHistory (hasMany)
- Client → BusinessType (belongsTo)

## Testing

### Manual Testing with Postman/Insomnia

1. **Login as Sales Rep**
```json
POST /api/auth/login
{
  "email": "salesrep@example.com",
  "password": "password"
}
```

2. **Create a Client**
3. **Create a Visit**
4. **Upload Files**
5. **Submit Visit** (change status to submitted)

6. **Login as Admin**
7. **View All Visits**
8. **Change Visit Status** to approved
9. **View Statistics**

### Database Seeding

The migration automatically seeds:
- 9 Business Types (Retail, Restaurant, Cafe, etc.)
- 9 Product Categories (Electronics, Fashion, etc.)

## Troubleshooting

### File Upload Issues
```bash
# Ensure storage directory exists and is writable
chmod -R 775 storage
chown -R www-data:www-data storage

# Re-link storage
php artisan storage:link
```

### Role Not Recognized
```sql
-- Verify role enum includes SALES_REP
SHOW COLUMNS FROM tbl_users LIKE 'role';
```

### Migration Errors
```bash
# Rollback and re-run
php artisan migrate:rollback --step=1
php artisan migrate
```

## Next Steps

1. ✅ Run migrations
2. ✅ Test API endpoints with Postman
3. ✅ Create test users (SALES_REP and ADMIN)
4. ✅ Frontend integration (already completed)
5. 📋 Optional: Add middleware for role-based access control
6. 📋 Optional: Add visit approval workflow notifications
7. 📋 Optional: Export visits to Excel/PDF reports

## API Collection

Import the API collection from the frontend project's API integration to test all endpoints.

## Support

For issues or questions:
- Check Laravel logs: `storage/logs/laravel.log`
- Verify database structure: `php artisan migrate:status`
- Test endpoints with `php artisan tinker`
