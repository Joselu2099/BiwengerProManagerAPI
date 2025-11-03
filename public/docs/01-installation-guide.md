# Installation and Configuration Guide - BiwengerProManagerAPI

## 🚀 Introduction

BiwengerProManagerAPI is a REST API in PHP that acts as a wrapper and backend for Biwenger functionalities: leagues, users, players, rounds, transfers, and clauses. It provides public endpoints (v0) and premium endpoints (v1) for integration with frontend applications.

## 📋 System Requirements

### Mandatory Requirements:
- **PHP >= 7.4** (as defined in composer.json)
- **Composer** (PHP dependency manager)
- **MongoDB >= 4.4** (primary database)
- **MongoDB PHP Extension** (`php-mongodb`)

### Development Tools (Optional):
- **Git** (to clone the repository)
- **Postman** (to test the API)
- **MongoDB Compass** (GUI for MongoDB)
- **VS Code** with PHP extensions

### Supported Web Servers:
- **Apache** with mod_rewrite (recommended for production)
- **Nginx** with PHP-FPM
- **PHP Built-in Server** (development only)

## 🔧 Installation Steps

### 1. Download the Repository

Clone the repository or download the ZIP:

```powershell
# Option A: Clone from GitHub
git clone https://github.com/Joselu2099/BiwengerProManagerAPI.git
cd BiwengerProManagerAPI

# Option B: Download ZIP from GitHub
# Extract the file in your working directory
```

### 2. Install Dependencies

```powershell
# Install Composer dependencies
composer install

# Verify dependencies were installed correctly
composer dump-autoload
```

**Main dependencies installed:**
- `mongodb/mongodb`: Official MongoDB driver
- `phpunit/phpunit`: Testing framework (development)
- PSR-4 autoloader for project classes

### 3. Environment Configuration

The project uses environment-based configuration with `.env` files. The Config class automatically loads configuration from the appropriate environment directory.

#### Configuration model

This project uses a single root environment file: `config/.env`. The loader reads that file (if present) and uses its values. You may also set environment variables directly in your shell. The older per-environment directories (`config/dev`, `config/local`, `config/prod`) are deprecated in this repository.

#### Configuration Priority (highest to lowest):

1. **Environment variables** (from system)
2. **config/{env}/.env file** (primary configuration)
3. **config/{env}/config.conf file** (backup format)
4. **config/{env}/config.php file** (legacy format)

#### Setting Up Your Environment:

**For Development:**
Create `config/.env` from the example and configure it for your environment:

```bash
# Copy the example root env to config/.env
cp config/.env.example config/.env
# On Windows PowerShell:
# Copy-Item config\.env.example -Destination config\.env
```

Edit `config/.env` with your settings:

```env
# MongoDB Configuration
MONGODB_URI=mongodb://localhost:27017
MONGODB_DB=biwenger_dev

# API Authentication
API_KEY=your-api-key-here
BIWENGER_API_KEY=your-biwenger-api-key

# Environment Settings
APP_ENV=local
DEBUG=true
LOG_LEVEL=debug
```

**For Production:**
Configure the root `config/.env` with production values on your production server (do not commit secrets).

```env
# MongoDB Configuration (production)
MONGODB_URI=mongodb://your-production-server:27017
MONGODB_DB=biwenger_prod

# API Authentication (use strong keys)
API_KEY=your-secure-production-api-key
BIWENGER_API_KEY=your-production-biwenger-key

# Environment Settings
APP_ENV=prod
DEBUG=false
LOG_LEVEL=error
```

### 4. Configure MongoDB

#### MongoDB Installation (Windows):

1. **Download MongoDB Community Server:**
   - Go to https://www.mongodb.com/try/download/community
   - Download the Windows version
   - Install following the wizard (includes MongoDB Compass)

2. **Start the service:**
   ```powershell
   # MongoDB installs as a Windows service by default
   # Verify it's running:
   net start MongoDB
   ```

3. **Install PHP MongoDB extension:**
   ```powershell
   # With XAMPP, download the DLL from:
   # https://pecl.php.net/package/mongodb
   # Copy php_mongodb.dll to C:\xampp\php\ext\
   
   # Add to C:\xampp\php\php.ini:
   extension=mongodb
   
   # Restart Apache to load the extension
   ```

#### MongoDB Connection Verification:

The project uses the `MongoConnection` class which automatically loads configuration from the Config class:

```php
// The MongoConnection class reads from your .env file:
// MONGODB_URI=mongodb://localhost:27017
// MONGODB_DB=biwenger_dev

$mongoConnection = MongoConnection::getInstance();
$database = $mongoConnection->getDatabase();
```

### 5. Web Server Configuration

#### Option A: PHP Built-in Server (Development Only)

There are two common ways to run the development server locally:

Option 1 — recommended (uses the provided launcher):

```bash
# From the project root on Unix-like systems
./scripts/run.sh
# The launcher reads config/.env (if present) and starts PHP's built-in server.
```

Option 2 — start PHP built-in server manually:

```bash
# From the project root
php -S localhost:8000 -t public
# The API will be available at: http://localhost:8000
```

On Windows without WSL, you can either start Apache from XAMPP Control Panel (DocumentRoot -> public/) or run the PHP built-in server from the project root using the `php` executable shipped with XAMPP.

#### Option B: Apache Configuration

If using XAMPP or Apache, configure a virtual host:

1. **Edit httpd.conf or create a virtual host:**
   ```apache
   <VirtualHost *:80>
       DocumentRoot "C:/xampp/htdocs/BiwengerProManagerAPI/public"
       ServerName bwpromanager.local
       
       <Directory "C:/xampp/htdocs/BiwengerProManagerAPI/public">
           AllowOverride All
           Require all granted
           
           # Enable URL rewriting
           RewriteEngine On
           RewriteCond %{REQUEST_FILENAME} !-f
           RewriteCond %{REQUEST_FILENAME} !-d
           RewriteRule ^(.*)$ index.php [QSA,L]
       </Directory>
   </VirtualHost>
   ```

2. **Add to hosts file (C:\Windows\System32\drivers\etc\hosts):**
   ```
   127.0.0.1 bwpromanager.local
   ```

3. **Restart Apache and access:** http://bwpromanager.local

### 6. Database Configuration

#### MongoDB Database Setup:

```powershell
# Connect to MongoDB (default localhost:27017)
mongosh

# Create database and collections
use biwenger_dev
db.createCollection("users")
db.createCollection("leagues")
db.createCollection("accounts")

# Create basic indexes
db.users.createIndex({ "userId": 1, "leagueId": 1 })
db.leagues.createIndex({ "id": 1 })
db.accounts.createIndex({ "id": 1 })
```

### 7. API Configuration

#### Authentication Setup:

The project supports two authentication methods:

1. **Bearer Token Authentication** (for user sessions)
2. **API Key Authentication** (for application access)

Configure your API keys in the `.env` file:

```env
# API Authentication
API_KEY=your-secure-api-key-here
BIWENGER_API_KEY=your-biwenger-api-key
```

#### Configuration Class Usage:

The Config class supports encrypted configuration values. You can encrypt sensitive data:

```php
// Example: Store encrypted MongoDB URI
$config = new Config();
$encryptedUri = $config->encrypt('mongodb://username:password@host:27017');
// Store this encrypted value in your .env file
```

## ✅ Installation Verification

### 1. Test Basic API Access:

```powershell
# Test the API is running
curl http://localhost:8000/api/v0/health
# or
curl http://bwpromanager.local/api/v0/health
```

### 2. Test MongoDB Connection:

```powershell
# Test endpoint that requires database
curl http://localhost:8000/api/v0/leagues
```

### 3. Test Authentication:

```powershell
# Test with API key
curl -H "X-API-Key: your-api-key" http://localhost:8000/api/v1/premium-endpoint
```

### 4. Verify PHP Extensions:

```powershell
# Check PHP info includes MongoDB extension
php -m | findstr mongodb
```

## 🎯 Quick Start Commands

```powershell
# Complete setup for development
git clone https://github.com/Joselu2099/BiwengerProManagerAPI.git
cd BiwengerProManagerAPI
composer install
# Copy the development template to your local config directory (Unix/macOS)
cp config/.env.example config/.env || echo "Please create config/.env from config/.env.example"
# On Windows PowerShell:
# Copy-Item config\.env.example -Destination config\.env
# Edit config/.env with your settings (MONGODB_URI, MONGODB_DB, API_KEY, APP_ENV)

# Start the app (recommended launcher on Unix-like):
./scripts/run.sh

# Or start PHP built-in server manually:
# php -S localhost:8000 -t public
```

## 🔧 Configuration File Structure

The project uses a sophisticated configuration system with environment-based `.env` files:

```
config/
├── dev/
│   └── .env           # Development environment
├── local/
│   └── .env           # Local development (your copy)
└── prod/
    └── .env           # Production environment
```

### Config Class Features:

- **Environment Detection**: Automatically detects environment (dev/local/prod)
- **Multiple Format Support**: .env, .conf, and .php configuration files
- **Encryption Support**: Can encrypt/decrypt sensitive configuration values
- **Priority System**: Environment variables → .env file → .conf file → .php file
- **Singleton Pattern**: One instance throughout the application

### Example Configuration Files:

#### `config/.env` (Development):
```env
# MongoDB Configuration
MONGODB_URI=mongodb://localhost:27017
MONGODB_DB=biwenger_dev

# API Keys
API_KEY=development-api-key-12345
BIWENGER_API_KEY=your-biwenger-api-key

# Environment Settings
APP_ENV=local
DEBUG=true
LOG_LEVEL=debug

# Biwenger Service
BIWENGER_BASE_URL=https://biwenger.as.com
BIWENGER_TIMEOUT=30
```

#### `config/.env` (Production example):
```env
# MongoDB Configuration (production)
MONGODB_URI=mongodb://username:password@prod-server:27017/biwenger_prod?authSource=admin
MONGODB_DB=biwenger_prod

# API Keys (use strong, unique keys)
API_KEY=prod-secure-api-key-abcdef123456
BIWENGER_API_KEY=your-production-biwenger-key

# Environment Settings
APP_ENV=prod
DEBUG=false
LOG_LEVEL=error

# Biwenger Service
BIWENGER_BASE_URL=https://biwenger.as.com
BIWENGER_TIMEOUT=60
```

## 🚨 Common Issues and Solutions

### 1. MongoDB Connection Issues:

**Error:** "MongoDB connection failed"
```powershell
# Check MongoDB service status
net start MongoDB

# Test connection manually
mongosh mongodb://localhost:27017

# Verify .env configuration
echo "Check MONGODB_URI and MONGODB_DB in your config/{env}/.env file"
```

### 2. PHP MongoDB Extension Missing:

**Error:** "Class 'MongoDB\Client' not found"
```powershell
# Install MongoDB extension
# Download from: https://pecl.php.net/package/mongodb
# Add to php.ini: extension=mongodb
# Restart web server

# Verify installation
php -m | findstr mongodb
```

### 3. Composer Dependencies Issues:

**Error:** "Class not found" or autoload issues
```powershell
# Reinstall dependencies
rm -rf vendor/
composer install
composer dump-autoload -o
```

### 4. Configuration Not Loading:

**Error:** Config values returning null
```powershell
# Check environment detection
php -r "require 'vendor/autoload.php'; echo (new \App\Config\Config())->get('ENVIRONMENT');"

# Verify file exists
ls config/.env
ls config/.env
```

### 5. API Authentication Issues:

**Error:** "Unauthorized" responses
```powershell
# Test API key in .env file
curl -H "X-API-Key: your-api-key" http://localhost:8000/api/v1/test

# Check Bearer token format
curl -H "Authorization: Bearer your-token" http://localhost:8000/api/v1/test
```

## 📝 Next Steps

After successful installation:

1. **Read the API Guide**: Check `02-api-guide.md` for endpoint documentation
2. **Test with Postman**: Import the collection from `public/docs/postman_collection.json`
3. **Review Data Models**: Understand the structure in `03-data-models.md`
4. **Development Workflow**: See development patterns in `04-development-guide.md`

## 🔗 Additional Resources

- **Project Repository**: https://github.com/Joselu2099/BiwengerProManagerAPI
- **MongoDB Documentation**: https://docs.mongodb.com/
- **Composer Documentation**: https://getcomposer.org/doc/
- **PHP MongoDB Driver**: https://docs.mongodb.com/drivers/php/
        'timezone' => 'Europe/Madrid'
    ]
];
```

#### Archivo `config/production.php`:

```php
<?php
return [
    'database' => [
        'mongodb' => [
            'host' => 'your-production-host',
            'port' => 27017,
            'database' => 'biwenger_api_prod',
            'username' => 'your-username',
            'password' => 'your-secure-password'
        ]
    ],
    'api' => [
        'keys' => [
            'your-secure-api-key' => 'production',
        ],
        'biwenger' => [
            'base_url' => 'https://biwenger.as.com',
            'timeout' => 30
        ]
    ],
    'app' => [
        'debug' => false,
        'timezone' => 'Europe/Madrid'
    ]
];
```

### 5. Configuración del Servidor Web

#### Opción A: PHP Built-in Server (Desarrollo)

```powershell
# Navegar al directorio public
cd public



