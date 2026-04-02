# 🚀 Hooketh: A Webhook Management Platform

<p align="center">
  <strong>A comprehensive, enterprise-grade webhook management and delivery platform built with Laravel and Vue.js</strong>
</p>

![Hooketh screenshot](https://github.com/morcen/hooketh/blob/main/screenshot.png?raw=true)

<p align="center">
  <a href="#features">Features</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#documentation">Documentation</a> •
  <a href="#contributing">Contributing</a> •
  <a href="#license">License</a>
</p>

---

## ✨ Features

### Core Functionality
- **🎯 Endpoint Management** - Create and manage webhook endpoints with security configurations
- **📦 Event Processing** - Handle various event types with structured payloads
- **🔄 Reliable Delivery** - Automatic retry mechanisms with exponential backoff
- **📊 Real-time Monitoring** - Track delivery status, response codes, and performance metrics
- **🔐 Security First** - HMAC signature verification and secret key management
- **⚡ Queue Processing** - Background job processing for high-volume webhook delivery

### Management & Analytics
- **📈 Dashboard & Analytics** - Comprehensive delivery statistics and success rates
- **🔍 Advanced Filtering** - Search and filter events by type, status, date range
- **📝 Detailed Logging** - Complete audit trail of webhook attempts and responses
- **🎛️ Admin Controls** - Endpoint activation/deactivation and configuration management
- **💾 Data Export** - Export delivery logs and analytics data

### Technical Features
- **🚄 High Performance** - Built on Laravel with Redis caching and queue optimization
- **🏗️ Scalable Architecture** - Docker containerization with horizontal scaling support
- **🔧 Developer Friendly** - Comprehensive API, detailed documentation, and testing utilities
- **📧 Email Notifications** - Delivery failure alerts and status notifications

## 🚀 Quick Start

### Prerequisites
- [Docker](https://docker.com) and [Docker Compose](https://docs.docker.com/compose/)
- [Node.js](https://nodejs.org) (for frontend development)
- [Git](https://git-scm.com)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/morcen/hooketh.git
   cd webhook-management-platform
   ```

2. **Set up environment configuration**
   ```bash
   # Copy the example environment file
   cp .env.example .env

   # Generate application key
   php artisan key:generate
   ```

   > **Important**: The Docker setup now uses your local `.env` file instead of a separate Docker environment file. Make sure your `.env` file is properly configured before building containers.

3. **Start with Docker** (Recommended)
   ```bash
   # Build and start all services
   make setup

   # Or manually:
   docker-compose up -d
   make migrate
   make seed
   ```

4. **Access the application**
   - **Web Interface**: http://localhost:8080
   - **API Documentation**: http://localhost:8080/api/docs
   - **Email Testing**: http://localhost:8025 (MailHog)

5. **Default Login**
   - **Email**: test@example.com
   - **Password**: password

### Development Setup

For Vue.js development with hot reloading:

```bash
# Install Node.js dependencies
npm install

# Start Vite development server
npm run dev
```

See [DEVELOPMENT.md](DEVELOPMENT.md) for detailed development instructions.

## 📚 Documentation

- **[Development Guide](DEVELOPMENT.md)** - Detailed development setup and workflows
- **[Docker Documentation](DOCKER.md)** - Docker setup, commands, and configuration
- **[Deployment Guide](DEPLOYMENT.md)** - Cloud deployment instructions and troubleshooting
- **[API Documentation](API.md)** - REST API endpoints and examples
- **[Architecture Overview](ARCHITECTURE.md)** - System design and components

## 🛠️ Tech Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Vue.js 3, Inertia.js, Tailwind CSS
- **Database**: PostgreSQL
- **Cache & Queue**: Redis
- **Web Server**: Nginx
- **Build Tool**: Vite
- **Containerization**: Docker & Docker Compose

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Quick Contribution Steps
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for your changes
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## 🐛 Issues & Support

- **Bug Reports**: [Create an issue](https://github.com/morcen/hooketh/issues/new?template=bug_report.md)
- **Feature Requests**: [Create an issue](https://github.com/morcen/hooketh/issues/new?template=feature_request.md)
- **Questions**: [Discussions](https://github.com/morcen/hooketh/discussions)

## 🔐 Security

If you discover a security vulnerability, please send an email to [security@yourproject.com](mailto:security@yourproject.com) instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com)
- UI components by [Tailwind CSS](https://tailwindcss.com)
- Frontend framework by [Vue.js](https://vuejs.org)
- Thanks to all [contributors](https://github.com/morcen/hooketh/graphs/contributors)

---

<p align="center">Made with ❤️ for the developer community</p>
