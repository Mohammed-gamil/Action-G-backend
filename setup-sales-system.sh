#!/bin/bash
# Quick Setup Script for Sales Visit System

echo "🚀 Starting Sales Visit System Setup..."
echo ""

# Navigate to backend
cd Action-G-backend

# Step 1: Fresh Migration
echo "📦 Step 1: Running migrations..."
php artisan migrate:fresh
echo "✅ Migrations complete!"
echo ""

# Step 2: Seed Sales Data
echo "🌱 Step 2: Seeding Business Types & Product Categories..."
php artisan db:seed --class=SalesVisitSeeder
echo "✅ Sales data seeded!"
echo ""

# Step 3: Seed Users
echo "👥 Step 3: Seeding Users..."
php artisan db:seed --class=UserSeeder
echo "✅ Users seeded!"
echo ""

# Verify
echo "🔍 Verifying data..."
php artisan tinker --execute="
echo '✅ Business Types: ' . App\Models\BusinessType::count() . PHP_EOL;
echo '✅ Product Categories: ' . App\Models\ProductCategory::count() . PHP_EOL;
echo '✅ Total Users: ' . App\Models\User::count() . PHP_EOL;
echo '✅ Sales Reps: ' . App\Models\User::where('role', 'SALES_REP')->count() . PHP_EOL;
"

echo ""
echo "✅ Setup Complete!"
echo ""
echo "📝 Login Credentials:"
echo "   Admin:      admin@test.com  / password"
echo "   Sales Rep:  sales@test.com  / password"
echo "   Sales Rep 2: sales2@test.com / password"
echo ""
echo "🚀 Start servers:"
echo "   Backend:  php artisan serve --port=8001"
echo "   Frontend: npm run dev"
echo ""
