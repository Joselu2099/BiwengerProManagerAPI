# Testing Tools Guide - BiwengerProManagerAPI

## 🧪 Overview

This guide covers all testing tools and procedures for the BiwengerProManagerAPI, including PHPUnit automated tests, Postman collections, OpenAPI specifications, and testing workflows based on the actual project structure.

## 🧪 PHPUnit Test Suite

### Current Test Structure

The project uses PHPUnit for automated testing with the following structure:

```
tests/
├── bootstrap.php                # Test environment setup
└── Controllers/
    ├── V0EndpointsTest.php     # Public endpoints testing (no API key)
    └── V1EndpointsTest.php     # Premium endpoints testing (requires API key)
```

### PHPUnit Configuration

**File: `phpunit.xml`**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Test Environment Setup

**File: `tests/bootstrap.php`**
```php
<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Bootstrap.php';

session_start();
```

### Running Tests

#### Basic Test Execution

```powershell
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Controllers/V0EndpointsTest.php
vendor/bin/phpunit tests/Controllers/V1EndpointsTest.php

# Run with detailed output
vendor/bin/phpunit --testdox

# Run with verbose output
vendor/bin/phpunit --verbose
```

#### Advanced Test Options

```powershell
# Generate coverage report (requires Xdebug)
vendor/bin/phpunit --coverage-html coverage/

# Run specific test method
vendor/bin/phpunit --filter testApiKeyIsRecognized

# Run tests with specific configuration
vendor/bin/phpunit --configuration phpunit.xml
```

### V0 Endpoints Testing (Public)

**File: `tests/Controllers/V0EndpointsTest.php`**

Tests public endpoints that don't require API key authentication:

- **Authentication endpoints**: Login, account info
- **League endpoints**: List leagues, get league details
- **Player endpoints**: List players, get player details
- **User endpoints**: List users, get user info, sync users
- **Round endpoints**: List rounds, get round results

**Key Features:**
- Uses reflection to inject mock services into controllers
- Tests controller methods directly without HTTP layer
- Validates response structure and data types
- No API key authentication required

**Example Test Method:**
```php
public function testAuthLoginWithValidCredentials()
{
    $mockService = $this->createMock(AuthService::class);
    $mockService->method('login')->willReturn(['token' => 'mock_token']);
    
    $controller = new AuthController($mockService);
    
    ob_start();
    $controller->login('{"email":"test@example.com","password":"password"}');
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    $this->assertEquals(200, $response['status']);
}
```

### V1 Endpoints Testing (Premium)

**File: `tests/Controllers/V1EndpointsTest.php`**

Tests premium endpoints that require API key authentication:

- **Transfer operations**: Execute transfers between users
- **Clause operations**: Execute buyout clauses
- **League settings**: Get and update league configurations

**Key Features:**
- Tests API key validation (`changeme_local` for development)
- Validates payload structure and required fields
- Tests authentication and authorization flows
- Comprehensive validation testing

**Example Test Methods:**
```php
public function testApiKeyIsRecognized()
{
    $_SERVER['HTTP_X_API_KEY'] = 'changeme_local';
    
    $controller = new TransfersController($mockService);
    // Test implementation validates API key recognition
}

public function testValidPayloadValidation()
{
    $validPayload = [
        'playerId' => 123,
        'fromUserId' => 456,
        'toUserId' => 789,
        'price' => 1000000,
        'leagueId' => '1358641'
    ];
    
    // Test validates payload structure and required fields
}
```

### Writing New Tests

#### Test Method Naming Convention

```php
// Test method names should be descriptive
public function testAuthLoginWithValidCredentials()
public function testApiKeyAuthenticationWithInvalidKey()
public function testTransferExecutionWithMissingFields()
public function testLeagueSettingsUpdateWithValidData()
```

#### Mock Service Creation

```php
// Create mock services for testing
$mockService = $this->createMock(ServiceClass::class);
$mockService->method('methodName')->willReturn($expectedResult);

// Inject mock into controller using reflection
$reflection = new ReflectionClass($controller);
$property = $reflection->getProperty('service');
$property->setAccessible(true);
$property->setValue($controller, $mockService);
```

#### Response Validation

```php
// Capture controller output
ob_start();
$controller->methodName($payload);
$output = ob_get_clean();

// Parse and validate response
$response = json_decode($output, true);
$this->assertEquals(200, $response['status']);
$this->assertArrayHasKey('data', $response);
$this->assertStringContainsString('expected message', $response['message']);
```

## 📮 Postman Testing

### Collection Structure

The project includes a comprehensive Postman collection located at:
`public/docs/BiwengerProManagerAPI.postman_collection.json`

**Collection Organization:**
```
BiwengerProManagerAPI/
├── V0 Public Endpoints/
│   ├── Auth/
│   ├── Leagues/
│   ├── Players/
│   ├── Users/
│   └── Rounds/
└── V1 Premium Endpoints/
    ├── Transfers/
    ├── Clauses/
    └── League Settings/
```

### Environment Configuration

**File: `public/docs/BiwengerProManagerAPI.postman_environment.json`**

**Core Variables:**
```json
{
  "BASE_URL": "http://localhost:8080",
  "API_KEY": "changeme_local",
  "token": "",
  "email": "your-email@example.com",
  "password": "your-password"
}
```

**Test Data Variables:**
```json
{
  "leagueId": "1358641",
  "competitionId": "2",
  "userId": "123456",
  "playerId": "34147",
  "fromUserId": "123456",
  "toUserId": "789012",
  "transferPrice": "120",
  "clauseType": "buyout",
  "clauseAmount": "200"
}
```

**League Settings Variables:**
```json
{
  "clausesValue": "250",
  "timesCanClause": "3",
  "maxTimesClaused": "2",
  "numRoundsToUnlock": "5",
  "numDaysBeforeRound": "2",
  "maxPlayersSameTeam": "5"
}
```

### Setting Up Postman

#### 1. Import Collection and Environment

```
1. Open Postman
2. Import → File → Select BiwengerProManagerAPI.postman_collection.json
3. Import → File → Select BiwengerProManagerAPI.postman_environment.json
4. Select "BiwengerProManagerAPI Environment" from environment dropdown
```

#### 2. Configure Variables

```
1. Click environment dropdown → BiwengerProManagerAPI Environment → Edit
2. Update BASE_URL to your local server (default: http://localhost:8080)
3. Update email/password with your Biwenger credentials
4. Update API_KEY if using custom key (default: changeme_local)
5. Update test data variables (leagueId, userId, etc.) with real values
```

### Testing Workflows

#### Basic V0 Testing Flow

```
1. Variables → Set email/password → Your Biwenger credentials
2. V0 Public Endpoints → Auth → Login
   ✅ Token automatically saved to {{token}} variable
3. V0 Public Endpoints → Auth → Account
   ✅ Verify authentication works
4. Test other V0 endpoints:
   - Leagues → List/Get by ID
   - Players → List/Get by ID
   - Users → List for League/Get by ID
   - Rounds → List/Results
```

#### Premium V1 Testing Flow

```
1. Complete V0 flow first (steps 1-3 above)
2. Variables → Set API_KEY → "changeme_local" (or your key)
3. Variables → Set test data → leagueId, userId, playerId
4. Test V1 endpoints:
   - League Settings → Get/Update
   - Transfers → Execute (adjust payload)
   - Clauses → Execute (adjust payload)
```

### Advanced Postman Features

#### Pre-request Scripts

Auto-login script for authenticated endpoints:
```javascript
const baseUrl = pm.environment.get("BASE_URL");
const email = pm.environment.get("email");
const password = pm.environment.get("password");
const currentToken = pm.environment.get("token");

if (!currentToken) {
    pm.sendRequest({
        url: `${baseUrl}/api/v0/auth/login`,
        method: 'POST',
        header: { 'Content-Type': 'application/json' },
        body: {
            mode: 'raw',
            raw: JSON.stringify({ email, password })
        }
    }, (err, response) => {
        if (!err && response.code === 200) {
            const responseData = response.json();
            if (responseData.data && responseData.data.token) {
                pm.environment.set("token", responseData.data.token);
            }
        }
    });
}
```

#### Test Scripts

Response validation script:
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has correct structure", function () {
    const responseJson = pm.response.json();
    pm.expect(responseJson).to.have.property('status');
    pm.expect(responseJson).to.have.property('message');
    pm.expect(responseJson).to.have.property('data');
});

pm.test("Response time is acceptable", function () {
    pm.expect(pm.response.responseTime).to.be.below(2000);
});
```

#### Collection Runner

```
1. Collection → Run → Configure:
   - Environment: BiwengerProManagerAPI Environment
   - Iterations: 1
   - Delay: 500ms between requests
   - Data file: Optional CSV with test data
2. Run and review results
```

### Newman CLI Testing

#### Installation and Basic Usage

```powershell
# Install Newman globally
npm install -g newman

# Run collection with environment
newman run public/docs/BiwengerProManagerAPI.postman_collection.json `
  -e public/docs/BiwengerProManagerAPI.postman_environment.json `
  --reporters cli,html `
  --reporter-html-export newman-report.html

# Run with specific data file
newman run public/docs/BiwengerProManagerAPI.postman_collection.json `
  -e public/docs/BiwengerProManagerAPI.postman_environment.json `
  -d test-data.csv `
  --iteration-count 3
```

#### CI/CD Integration

```yaml
# GitHub Actions example
- name: Run API tests with Newman
  run: |
    npm install -g newman
    newman run public/docs/BiwengerProManagerAPI.postman_collection.json \
      -e public/docs/BiwengerProManagerAPI.postman_environment.json \
      --reporters junit \
      --reporter-junit-export newman-results.xml
```

## 📋 OpenAPI Specification

### OpenAPI Documentation

**File: `public/docs/openapi.yaml`**

The OpenAPI 3.0.3 specification provides comprehensive API documentation:

- **Version**: 1.0.0
- **Base URL**: `http://bwpromanager.local` (configurable)
- **Authentication**: Bearer tokens and API keys
- **Endpoints**: Complete V0 and V1 endpoint documentation

### Using OpenAPI Specification

#### 1. Swagger UI Visualization

**Online (Swagger Editor):**
```
1. Go to https://editor.swagger.io/
2. File → Import File → Select openapi.yaml
3. View interactive documentation
```

**Local with Docker:**
```powershell
docker run -p 8081:8080 -v ${PWD}/public/docs:/tmp swaggerapi/swagger-ui
# Open http://localhost:8081?url=/tmp/openapi.yaml
```

**VS Code Extension:**
```
1. Install "Swagger Viewer" extension
2. Open openapi.yaml
3. Command Palette → "Swagger: Preview"
```

#### 2. Code Generation

**TypeScript/JavaScript Client:**
```powershell
npx @openapitools/openapi-generator-cli generate \
  -i public/docs/openapi.yaml \
  -g typescript-axios \
  -o generated/typescript-client
```

**PHP Client:**
```powershell
java -jar openapi-generator-cli.jar generate \
  -i public/docs/openapi.yaml \
  -g php \
  -o generated/php-client
```

#### 3. Validation Tools

**Spectral Linting:**
```powershell
npm install -g @stoplight/spectral-cli
spectral lint public/docs/openapi.yaml
```

**OpenAPI Validator:**
```powershell
npm install -g ibm-openapi-validator
lint-openapi public/docs/openapi.yaml
```

## 🔧 Development Tools

### VS Code Extensions

Recommended extensions for testing:

```json
{
  "recommendations": [
    "humao.rest-client",           // REST Client for .http files
    "42crunch.vscode-openapi",     // OpenAPI/Swagger editor
    "ms-vscode.vscode-json",       // Enhanced JSON support
    "bmewburn.vscode-intelephense", // PHP IntelliSense
    "felixfbecker.php-debug"       // PHP debugging
  ]
}
```

### REST Client Testing

Create `.http` files for quick API testing:

**File: `api-tests.http`**
```http
### Variables
@baseUrl = http://localhost:8080
@apiKey = changeme_local
@token = your_jwt_token_here

### Login
POST {{baseUrl}}/api/v0/auth/login
Content-Type: application/json

{
  "email": "your-email@example.com",
  "password": "your-password"
}

### Get Account Info
GET {{baseUrl}}/api/v0/auth/account
Authorization: Bearer {{token}}

### List Leagues
GET {{baseUrl}}/api/v0/leagues
Authorization: Bearer {{token}}

### Execute Transfer (Premium)
POST {{baseUrl}}/api/v1/transfers
Authorization: Bearer {{token}}
X-API-KEY: {{apiKey}}
Content-Type: application/json

{
  "playerId": 34147,
  "fromUserId": 123456,
  "toUserId": 789012,
  "price": 1200000,
  "leagueId": "1358641"
}
```

## 📊 Testing Best Practices

### Test Coverage Guidelines

1. **Unit Tests (PHPUnit)**:
   - Test all controller methods
   - Test service layer logic
   - Mock external dependencies
   - Validate input/output formats

2. **Integration Tests (Postman)**:
   - Test complete request/response cycles
   - Validate authentication flows
   - Test error handling
   - Verify data persistence

3. **API Contract Tests (OpenAPI)**:
   - Validate response schemas
   - Test required/optional fields
   - Verify status codes
   - Check content types

### Environment Management

#### Development Environment
```
BASE_URL: http://localhost:8080
API_KEY: changeme_local
Database: Local MongoDB instance
```

#### Testing Environment
```
BASE_URL: http://test.bwpromanager.local
API_KEY: test_api_key
Database: Test MongoDB instance
```

#### Production Environment
```
BASE_URL: https://api.biwenger.com
API_KEY: production_api_key
Database: Production MongoDB cluster
```

### Continuous Integration

#### GitHub Actions Workflow

**File: `.github/workflows/tests.yml`**
```yaml
name: API Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mongodb:
        image: mongo:4.4
        ports:
          - 27017:27017
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mongodb
        
    - name: Install Composer dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Run PHPUnit tests
      run: vendor/bin/phpunit --coverage-clover=coverage.xml
      
    - name: Setup Node.js
      uses: actions/setup-node@v2
      with:
        node-version: '16'
        
    - name: Install Newman
      run: npm install -g newman
      
    - name: Run Postman tests
      run: |
        newman run public/docs/BiwengerProManagerAPI.postman_collection.json \
          -e public/docs/BiwengerProManagerAPI.postman_environment.json \
          --reporters junit \
          --reporter-junit-export newman-results.xml
      
    - name: Upload coverage reports
      uses: codecov/codecov-action@v1
      with:
        file: ./coverage.xml
```

## 🚀 Quick Start Testing Guide

### 1. Set Up Local Environment

```powershell
# Install dependencies
composer install

# Start local server
cd public && php -S localhost:8080

# Verify MongoDB is running
mongosh --eval "db.adminCommand('ping')"
```

### 2. Run PHPUnit Tests

```powershell
# Quick test run
vendor/bin/phpunit

# Detailed output
vendor/bin/phpunit --testdox

# Test specific endpoints
vendor/bin/phpunit tests/Controllers/V0EndpointsTest.php
vendor/bin/phpunit tests/Controllers/V1EndpointsTest.php
```

### 3. Test with Postman

```
1. Import collection: BiwengerProManagerAPI.postman_collection.json
2. Import environment: BiwengerProManagerAPI.postman_environment.json
3. Configure variables: email, password, BASE_URL
4. Run V0 endpoints → Auth → Login
5. Test other endpoints using saved token
```

### 4. Validate API Documentation

```powershell
# View in Swagger Editor
# Go to https://editor.swagger.io/
# Import public/docs/openapi.yaml

# Or use VS Code with Swagger Viewer extension
code public/docs/openapi.yaml
# Command Palette → "Swagger: Preview"
```

## 📚 Resources and References

### Documentation
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Postman Learning Center](https://learning.postman.com/)
- [OpenAPI Specification](https://swagger.io/specification/)
- [Newman CLI Documentation](https://github.com/postmanlabs/newman)

### Tools
- [Postman](https://www.postman.com/) - API testing platform
- [Insomnia](https://insomnia.rest/) - Alternative REST client
- [HTTPie](https://httpie.io/) - Command-line HTTP client
- [Swagger Editor](https://editor.swagger.io/) - OpenAPI editor

### Testing Resources
- [API Testing Best Practices](https://restfulapi.net/api-testing/)
- [PHP Testing Strategies](https://phpunit.readthedocs.io/)
- [Postman Testing Scripts](https://learning.postman.com/docs/writing-scripts/test-scripts/)

---

This testing guide provides comprehensive coverage of all testing tools and procedures for BiwengerProManagerAPI. Regular testing ensures API reliability and helps maintain code quality throughout development.