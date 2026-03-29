#!/bin/bash

# Run migrations
php artisan migrate

# Seed admin user
php artisan db:seed --class=AdminSeeder

echo "Admin setup complete!"
echo "Login credentials:"
echo "Email: admin@jetpicker.com"
echo "Password: JetPicker@dmin2026!"
