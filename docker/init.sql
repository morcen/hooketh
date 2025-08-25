-- Create database if it doesn't exist
SELECT 'CREATE DATABASE webhook_management'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'webhook_management')\gexec

-- Grant privileges to user
GRANT ALL PRIVILEGES ON DATABASE webhook_management TO webhook_user;
