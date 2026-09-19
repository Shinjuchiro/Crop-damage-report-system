#!/bin/sh
# Runs once per deploy, after the build finishes and before traffic is
# routed to the new deployment. Paste this as the service's "Pre-Deploy
# Command" in the Railway dashboard (Settings -> Deploy):
#
#   sh ./railway/init-app.sh
#
# See claude/DEPLOYMENT.md, "Quick demo deployment (Railway)", for the full
# setup this is one step of (the MySQL service, the mounted volume, the
# environment variables).
set -e

php artisan migrate --force

# Seeds the one MAO/Super Admin account (mao_admin / mao@tanza.gov.ph) plus
# the barangay, association and crop reference data the rest of the app
# depends on (registration's barangay dropdown, crop pickers, etc.) - none
# of that exists on a brand new database otherwise. Every seeder here uses
# firstOrCreate, so running this on every deploy is safe and never
# duplicates rows. CHANGE THE SEEDED MAO PASSWORD after your first login -
# see database/seeders/MaoAdminSeeder.php.
php artisan db:seed --force

# storage:link just reports the link already exists on every deploy after
# the first - that is expected, not a failure, so it does not block the
# rest of this script.
php artisan storage:link || true

php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
