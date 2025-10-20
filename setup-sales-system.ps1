# Quick Setup Script for Sales Visit System (PowerShell)

Write-Host "🚀 Starting Sales Visit System Setup..." -ForegroundColor Green
Write-Host ""

# Navigate to backend
Set-Location Action-G-backend

# Step 1: Fresh Migration
Write-Host "📦 Step 1: Running migrations..." -ForegroundColor Yellow
php artisan migrate:fresh
Write-Host "✅ Migrations complete!" -ForegroundColor Green
Write-Host ""

# Step 2: Seed Sales Data
Write-Host "🌱 Step 2: Seeding Business Types & Product Categories..." -ForegroundColor Yellow
php artisan db:seed --class=SalesVisitSeeder
Write-Host "✅ Sales data seeded!" -ForegroundColor Green
Write-Host ""

# Step 3: Seed Users
Write-Host "👥 Step 3: Seeding Users..." -ForegroundColor Yellow
php artisan db:seed --class=UserSeeder
Write-Host "✅ Users seeded!" -ForegroundColor Green
Write-Host ""

# Verify
Write-Host "🔍 Verifying data..." -ForegroundColor Yellow
php artisan tinker --execute="echo 'Business Types: ' . App\Models\BusinessType::count() . PHP_EOL . 'Product Categories: ' . App\Models\ProductCategory::count() . PHP_EOL . 'Total Users: ' . App\Models\User::count() . PHP_EOL . 'Sales Reps: ' . App\Models\User::where('role', 'SALES_REP')->count() . PHP_EOL;"

Write-Host ""
Write-Host "✅ Setup Complete!" -ForegroundColor Green
Write-Host ""
Write-Host "📝 Login Credentials:" -ForegroundColor Cyan
Write-Host "   Admin:       admin@test.com  / password"
Write-Host "   Sales Rep:   sales@test.com  / password"
Write-Host "   Sales Rep 2: sales2@test.com / password"
Write-Host ""
Write-Host "🚀 Start servers:" -ForegroundColor Cyan
Write-Host "   Backend:  php artisan serve --port=8001"
Write-Host "   Frontend: cd .. ; npm run dev"
Write-Host ""
