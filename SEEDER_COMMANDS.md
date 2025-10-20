# 🌱 Database Seeders Commands

## Quick Commands

### Run All Seeders (Fresh Start)
```bash
php artisan migrate:fresh --seed
```

### Run Individual Seeders

#### 1. Sales Visit Data (Business Types + Product Categories)
```bash
php artisan db:seed --class=SalesVisitSeeder
```

#### 2. Users (Sales Reps + Admin)
```bash
php artisan db:seed --class=UserSeeder
```

#### 3. Sample Clients & Visits (Demo Data)
```bash
php artisan db:seed --class=SampleVisitsSeeder
```

#### 4. Test Users
```bash
php artisan db:seed --class=UserSeeder
```

#### 3. Test Users
```bash
php artisan db:seed --class=TestUsersSeeder
```

#### 4. Inventory
```bash
php artisan db:seed --class=InventorySeeder
```

#### 5. Departments
```bash
php artisan db:seed --class=DepartmentSeeder
```

#### 6. Teams
```bash
php artisan db:seed --class=TeamSeeder
```

---

## Complete Setup (From Scratch)

### Step 1: Reset Database
```bash
cd Action-G-backend
php artisan migrate:fresh
```

### Step 2: Seed Core Data
```bash
# Sales Visit Reference Data (MUST RUN FIRST!)
php artisan db:seed --class=SalesVisitSeeder

# Users
php artisan db:seed --class=UserSeeder

# Departments & Teams (if needed)
php artisan db:seed --class=DepartmentSeeder
php artisan db:seed --class=TeamSeeder
```

---

## What Each Seeder Does

### ✅ SalesVisitSeeder
**Seeds:**
- 15 Business Types (مطاعم, مقاهي, متاجر تجزئة, etc.)
- 14 Product Categories (طعام ومشروبات, أزياء, إلكترونيات, etc.)

**Must run:** Before creating any clients or visits!

```bash
php artisan db:seed --class=SalesVisitSeeder
```

---

### ✅ UserSeeder
**Seeds:**
- Admin user: `admin@test.com` / `password` (ADMIN)
- Sales Rep 1: `sales@test.com` / `password` (SALES_REP)
- Sales Rep 2: `sales2@test.com` / `password` (SALES_REP)

```bash
php artisan db:seed --class=UserSeeder
```

---

### ✅ TestUsersSeeder
**Seeds:**
Additional test users for different roles

```bash
php artisan db:seed --class=TestUsersSeeder
```

---

### ✅ InventorySeeder
**Seeds:**
Sample inventory items

```bash
php artisan db:seed --class=InventorySeeder
```

---

### ✅ DepartmentSeeder
**Seeds:**
Company departments

```bash
php artisan db:seed --class=DepartmentSeeder
```

---

### ✅ TeamSeeder
**Seeds:**
Teams within departments

```bash
php artisan db:seed --class=TeamSeeder
```

---

## Recommended Order

For **Sales Visit System** only:
```bash
# 1. Reset database
php artisan migrate:fresh

# 2. Seed sales data
php artisan db:seed --class=SalesVisitSeeder

# 3. Seed users
php artisan db:seed --class=UserSeeder

# 4. Seed sample clients & visits (optional)
php artisan db:seed --class=SampleVisitsSeeder
```

For **Complete System** (Purchase Requests + Sales):
```bash
# 1. Fresh start
php artisan migrate:fresh

# 2. Core data
php artisan db:seed --class=SalesVisitSeeder
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=DepartmentSeeder
php artisan db:seed --class=TeamSeeder
php artisan db:seed --class=InventorySeeder
```

---

## Troubleshooting

### Problem: "Class not found"
```bash
# Regenerate autoload
composer dump-autoload

# Then retry
php artisan db:seed --class=SalesVisitSeeder
```

### Problem: "Foreign key constraint"
```bash
# Run fresh migration first
php artisan migrate:fresh

# Then seed
php artisan db:seed --class=SalesVisitSeeder
```

### Problem: "Duplicate entry"
```bash
# Seeders use updateOrInsert, so running twice is safe
# But if you want fresh data:
php artisan migrate:fresh
php artisan db:seed --class=SalesVisitSeeder
```

---

## Verify Data

### Check Business Types
```bash
php artisan tinker
>>> App\Models\BusinessType::count()
>>> App\Models\BusinessType::all()->pluck('name_en')
>>> exit
```

### Check Product Categories
```bash
php artisan tinker
>>> App\Models\ProductCategory::count()
>>> App\Models\ProductCategory::all()->pluck('name_en')
>>> exit
```

### Check Users
```bash
php artisan tinker
>>> App\Models\User::where('role', 'SALES_REP')->get(['name', 'email', 'role'])
>>> exit
```

---

## Database Status Check

```bash
# Check all tables
php artisan db:show

# Check specific table
php artisan db:table tbl_business_types
php artisan db:table tbl_product_categories
php artisan db:table users

# Count records
php artisan tinker --execute="echo 'Business Types: ' . App\Models\BusinessType::count() . PHP_EOL;"
php artisan tinker --execute="echo 'Product Categories: ' . App\Models\ProductCategory::count() . PHP_EOL;"
php artisan tinker --execute="echo 'Users: ' . App\Models\User::count() . PHP_EOL;"
```

---

## Quick Copy-Paste Commands

### Fresh Setup (Sales System Only)
```bash
cd Action-G-backend && php artisan migrate:fresh && php artisan db:seed --class=SalesVisitSeeder && php artisan db:seed --class=UserSeeder
```

### Re-seed Sales Data Only
```bash
cd Action-G-backend && php artisan db:seed --class=SalesVisitSeeder
```

### Re-seed Users Only
```bash
cd Action-G-backend && php artisan db:seed --class=UserSeeder
```

### Verify Everything
```bash
cd Action-G-backend && php artisan tinker --execute="echo 'Business Types: ' . App\Models\BusinessType::count() . ' | Product Categories: ' . App\Models\ProductCategory::count() . ' | Sales Users: ' . App\Models\User::where('role', 'SALES_REP')->count();"
```

---

## PowerShell One-Liners

### Complete Fresh Setup
```powershell
cd Action-G-backend; php artisan migrate:fresh; php artisan db:seed --class=SalesVisitSeeder; php artisan db:seed --class=UserSeeder
```

### Check Data
```powershell
cd Action-G-backend; php artisan tinker --execute="echo 'Business Types: ' . App\Models\BusinessType::count() . PHP_EOL . 'Product Categories: ' . App\Models\ProductCategory::count() . PHP_EOL . 'Users: ' . App\Models\User::count() . PHP_EOL;"
```

---

## Summary Table

| Seeder | Command | Records | Required For |
|--------|---------|---------|--------------|
| **SalesVisitSeeder** | `php artisan db:seed --class=SalesVisitSeeder` | 15 + 14 = 29 | Sales Visits |
| **UserSeeder** | `php artisan db:seed --class=UserSeeder` | 3 users | Login |
| **TestUsersSeeder** | `php artisan db:seed --class=TestUsersSeeder` | Multiple | Testing |
| **InventorySeeder** | `php artisan db:seed --class=InventorySeeder` | Items | Inventory |
| **DepartmentSeeder** | `php artisan db:seed --class=DepartmentSeeder` | Depts | Organization |
| **TeamSeeder** | `php artisan db:seed --class=TeamSeeder` | Teams | Organization |

---

**Last Updated:** October 21, 2025  
**Status:** ✅ All Seeders Ready
