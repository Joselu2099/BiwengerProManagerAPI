# 📚 BiwengerProManagerAPI

Welcome to the complete documentation for **BiwengerProManagerAPI**, a PHP REST API that acts as a wrapper and backend for Biwenger functionalities.

## 🗂️ Documentation Structure

The documentation is organized into **4 main guides** that cover everything needed to install, use, and maintain the API:

### 📖 Main Guides

| Document | Description | Audience |
|----------|-------------|----------|
| **[01 - Installation Guide](01-installation-guide.md)** | Complete project setup from scratch | Developers, DevOps |
| **[02 - API Usage Guide](02-api-usage-guide.md)** | Endpoints, authentication, and usage examples | Frontend/Backend Developers |
| **[03 - Data Model](03-data-model.md)** | MongoDB structure and data models | Backend Developers, DBAs |
| **[04 - Testing Tools](04-testing-tools.md)** | Postman, OpenAPI, PHPUnit, and validation | QA, Developers |

### 🔧 Configuration Files

| File | Purpose |
|------|---------|
| `BiwengerProManagerAPI.postman_collection.json` | Complete Postman collection with all endpoints |
| `BiwengerProManagerAPI.postman_environment.json` | Environment variables for Postman |
| `openapi.yaml` | OpenAPI 3.0 specification of the API |

## 🚀 Quick Start

### For New Users

1. **📥 Installation**: Start with the [Installation Guide](01-installation-guide.md)
2. **🔧 Configuration**: Follow MongoDB and web server configuration steps
3. **🧪 Validation**: Run tests to verify everything works
4. **📮 Testing**: Import Postman files to test the API

### For Developers

1. **📖 API**: Review the [API Usage Guide](02-api-usage-guide.md) to understand endpoints
2. **🗄️ Data**: Check the [Data Model](03-data-model.md) to understand the structure
3. **🧪 Testing**: Use the [Testing Tools](04-testing-tools.md) to validate changes
4. **🔧 Integration**: Implement clients using code examples

## 🎯 Key Features

### API Versioning
- **V0 (Public Endpoints)**: Read operations and basic authentication
- **V1 (Premium Endpoints)**: Data modification operations, require API Key

### Dual Authentication
- **Bearer Token**: To identify authenticated users
- **API Key**: To authorize access to premium functionalities

### Hybrid Persistence
- **MongoDB**: Configurations, history, and local cache
- **Biwenger API**: Source of truth for dynamic data

### Complete Testing
- **PHPUnit**: Automated unit and integration tests
- **Postman**: Complete collection for manual and automated testing
- **OpenAPI**: Standard specification for documentation and code generation

## 📂 Project Structure

```
BiwengerProManagerAPI/
├── 📁 config/                          # Configuration
│   ├── dev/                            # Development config
│   ├── local/                          # Local config
│   └── prod/                           # Production config
├── 📁 public/                          # Web root
│   ├── index.php                       # Front controller
│   └── 📁 docs/                        # Documentation
│       ├── 01-installation-guide.md    # 📖 Installation
│       ├── 02-api-usage-guide.md       # 🔌 API Usage
│       ├── 03-data-model.md            # 🗄️ Data Model
│       ├── 04-testing-tools.md         # 🧪 Testing
│       ├── *.postman_*.json           # 📮 Postman Files
│       └── openapi.yaml               # 📋 OpenAPI Specification
├── 📁 src/                            # Source code
│   ├── 📁 Controllers/                # HTTP Controllers
│   ├── 📁 Services/                   # Business logic
│   ├── 📁 Models/                     # Data models
│   ├── 📁 Database/                   # Persistence layer
│   └── 📁 Utils/                      # Utilities
├── 📁 tests/                          # Automated tests
├── 📁 scripts/                        # Setup scripts
└── 📁 vendor/                         # Composer dependencies
```

## 🔗 Quick Links

### 🛠️ For System Administrators
- [System Requirements](01-installation-guide.md#-system-requirements)
- [MongoDB Configuration](01-installation-guide.md#mongodb-configuration)
- [Web Server Configuration](01-installation-guide.md#web-server-configuration)
- [Production Deployment](01-installation-guide.md#production-deployment)

### 👨‍💻 For Backend Developers
- [V0 Endpoints - Public](02-api-usage-guide.md#v0-endpoints---public)
- [V1 Endpoints - Premium](02-api-usage-guide.md#v1-endpoints---premium-require-api-key)
- [Authentication and Authorization](02-api-usage-guide.md#authentication-and-authorization)
- [MongoDB Structure](03-data-model.md#mongodb-collections)

### 🎨 For Frontend Developers
- [Authentication Flow](02-api-usage-guide.md#authentication-flow)
- [Usage Examples](02-api-usage-guide.md#usage-examples)
- [HTTP Response Codes](02-api-usage-guide.md#http-response-codes)
- [Application Integration](02-api-usage-guide.md#application-integration)

### 🧪 For QA and Testing
- [Postman Configuration](04-testing-tools.md#postman-testing)
- [PHPUnit Automated Tests](04-testing-tools.md#phpunit-test-suite)
- [OpenAPI Specification](04-testing-tools.md#openapi-specification)
- [Continuous Integration Tools](04-testing-tools.md#continuous-integration)

## 🆘 Support and Contribution

### 📞 Getting Help
- **Documentation**: Read the specific guides according to your needs
- **Issues**: Report problems at [GitHub Issues](https://github.com/Joselu2099/BiwengerProManagerAPI/issues)
- **Discussions**: Participate in [GitHub Discussions](https://github.com/Joselu2099/BiwengerProManagerAPI/discussions)

### 🤝 Contributing
- **Fork** the repository
- **Create** a branch for your feature (`git checkout -b feature/new-functionality`)
- **Commit** your changes (`git commit -am 'Add new functionality'`)
- **Push** to the branch (`git push origin feature/new-functionality`)
- **Create** a Pull Request

### 📝 Updating Documentation
- Keep documentation updated with code changes
- Follow existing format and structure
- Include practical examples and use cases
- Update Postman and OpenAPI files accordingly

## 📊 Project Status

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4+-blue)
![MongoDB](https://img.shields.io/badge/MongoDB-4.4+-green)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen)
![Documentation](https://img.shields.io/badge/docs-complete-brightgreen)
![License](https://img.shields.io/badge/license-Apache%202.0-blue)

### 📈 Roadmap
- ✅ Complete REST API with V0 and V1 endpoints
- ✅ Dual authentication (Bearer + API Key)
- ✅ MongoDB persistence
- ✅ PHPUnit automated tests
- ✅ Complete Postman collection
- ✅ Complete documentation
- 🔄 Continuous integration (CI/CD)
- 🔄 Rate limiting and throttling
- 📋 Advanced metrics and monitoring
- 📋 Intelligent API caching

## 💻 Technology Stack

- **Backend**: PHP 7.4+ with PSR-4 autoloading
- **Database**: MongoDB with PHP driver
- **Testing**: PHPUnit for automated testing
- **Documentation**: OpenAPI 3.0 specification
- **API Testing**: Postman collections and environments
- **License**: Apache 2.0

---

**Start your journey with BiwengerProManagerAPI by following the [Installation Guide](01-installation-guide.md)!** 🚀