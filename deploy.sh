#!/usr/bin/env bash
set -e

echo "🚀 Starting Trip Framework Production Deployment..."

# 1. Enter maintenance mode
php trip down --message="Deploying updates, back in 30 seconds" --retry=30

# 2. Pull latest code from Git
echo "📦 Pulling latest changes from repository..."
git pull origin main

# 3. Install production dependencies only
echo "⚡ Installing Composer production dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Run database migrations
echo "🗄️ Running pending database migrations..."
php trip migrate

# 5. Clear old caches and compile fresh production optimizations
echo "🔥 Compiling production route and config caches..."
php trip optimize:clear
php trip optimize

# 6. Bring application back online
php trip up

echo "✅ Trip Application successfully deployed and live!"
