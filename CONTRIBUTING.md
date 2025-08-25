# 🤝 Contributing to Webhook Management Platform

Thank you for considering contributing to the Webhook Management Platform! This guide will help you understand how to contribute effectively to this open source project.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [How to Contribute](#how-to-contribute)
- [Contribution Guidelines](#contribution-guidelines)
- [Code Style & Standards](#code-style--standards)
- [Testing Requirements](#testing-requirements)
- [Pull Request Process](#pull-request-process)
- [Community](#community)

## 🌟 Code of Conduct

This project adheres to a code of conduct to ensure a welcoming environment for all contributors. By participating, you agree to uphold these standards:

### Our Pledge
- **Be respectful**: Treat everyone with respect and kindness
- **Be inclusive**: Welcome newcomers and diverse perspectives  
- **Be collaborative**: Work together constructively
- **Be patient**: Help others learn and grow
- **Be professional**: Focus on what's best for the community

### Unacceptable Behavior
- Harassment, discrimination, or offensive comments
- Personal attacks or trolling
- Publishing private information without consent
- Any conduct that would be inappropriate in a professional setting

## 🚀 Getting Started

### Prerequisites
Before contributing, make sure you have:
- **Docker Desktop** installed and running
- **Node.js** 18+ and npm
- **Git** for version control
- A **GitHub account**

### First Steps
1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/webhook-management-platform.git
   cd webhook-management-platform
   ```
3. **Add the upstream remote**:
   ```bash
   git remote add upstream https://github.com/original-repo/webhook-management-platform.git
   ```
4. **Set up the development environment**:
   ```bash
   make setup
   npm install
   npm run dev
   ```

## 🛠️ Development Setup

Follow the detailed setup instructions in [DEVELOPMENT.md](DEVELOPMENT.md) to get your local environment ready for development.

### Quick Setup Commands
```bash
# Start all services
make setup

# Access the application
# Web: http://localhost:8080
# Email testing: http://localhost:8025

# Run tests
make test

# View logs
make logs
```

## 📝 How to Contribute

### Types of Contributions

We welcome various types of contributions:

#### 🐛 Bug Reports
Found a bug? Help us fix it!
- Search existing issues first
- Use the bug report template
- Include detailed steps to reproduce
- Provide environment information

#### ✨ Feature Requests
Have an idea for improvement?
- Search existing issues first
- Use the feature request template
- Explain the use case and benefits
- Consider implementation complexity

#### 🔧 Code Contributions
Ready to write code?
- Bug fixes
- New features
- Performance improvements
- Documentation updates
- Test coverage improvements

#### 📚 Documentation
Help improve our docs:
- Fix typos or unclear sections
- Add examples and tutorials
- Translate documentation
- Improve API documentation

#### 🧪 Testing
Improve our test coverage:
- Add unit tests
- Add integration tests
- Add end-to-end tests
- Improve test utilities

## 📏 Contribution Guidelines

### Branch Naming Convention
Use descriptive branch names with prefixes:
- `feature/add-webhook-filtering`
- `bugfix/fix-delivery-retry-logic`
- `docs/update-api-documentation`
- `refactor/optimize-database-queries`
- `test/add-endpoint-validation-tests`

### Commit Message Format
We use conventional commits for clear history:

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `test`: Adding tests
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `chore`: Maintenance tasks

**Examples:**
```
feat(endpoints): add endpoint filtering by status

Add ability to filter endpoints by active/inactive status
in the dashboard view.

Closes #123

fix(delivery): resolve retry logic infinite loop

The retry mechanism was getting stuck in an infinite loop
when the endpoint returned a 500 status. Added proper
backoff logic and maximum retry limits.

Fixes #456
```

## 🎨 Code Style & Standards

### PHP (Laravel) Standards
We follow PSR-12 coding standards with some project-specific rules:

```php
<?php

namespace App\Services;

use App\Models\Endpoint;
use App\Models\Event;
use Illuminate\Support\Facades\Http;

class WebhookDeliveryService
{
    public function deliver(Event $event, Endpoint $endpoint): bool
    {
        $payload = $this->preparePayload($event);
        
        $response = Http::timeout(30)
            ->withHeaders($this->getHeaders($endpoint))
            ->post($endpoint->url, $payload);
            
        return $response->successful();
    }
    
    private function preparePayload(Event $event): array
    {
        return [
            'event_type' => $event->event_type,
            'payload' => $event->payload,
            'timestamp' => $event->created_at->toISOString(),
        ];
    }
}
```

### Vue.js/JavaScript Standards
We use ESLint with Vue.js best practices:

```vue
<template>
  <div class="webhook-endpoint">
    <h2 class="text-xl font-semibold">
      {{ endpoint.name }}
    </h2>
    <p class="text-gray-600">
      {{ endpoint.description }}
    </p>
    <button 
      @click="toggleStatus"
      class="btn"
      :class="endpoint.is_active ? 'btn-success' : 'btn-warning'"
    >
      {{ endpoint.is_active ? 'Active' : 'Inactive' }}
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  endpoint: {
    type: Object,
    required: true
  }
})

const toggleStatus = () => {
  router.patch(`/endpoints/${props.endpoint.id}`, {
    is_active: !props.endpoint.is_active
  })
}
</script>
```

### Database Standards
- Use descriptive table and column names
- Follow Laravel naming conventions
- Add proper indexes for performance
- Include foreign key constraints
- Write reversible migrations

### API Standards
- Follow RESTful principles
- Use consistent response formats
- Include proper status codes
- Validate all inputs
- Document all endpoints

## 🧪 Testing Requirements

All contributions must include appropriate tests:

### Required Test Coverage
- **New features**: Must include feature tests
- **Bug fixes**: Must include regression tests
- **API changes**: Must include API tests
- **Models**: Must include unit tests

### Writing Tests
```php
// Feature Test Example
class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_successful_webhook_delivery()
    {
        // Arrange
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();
        
        // Mock external HTTP call
        Http::fake([
            $endpoint->url => Http::response(['status' => 'ok'], 200)
        ]);
        
        // Act
        $delivery = app(WebhookDeliveryService::class)
            ->deliver($event, $endpoint);
            
        // Assert
        $this->assertTrue($delivery);
        $this->assertDatabaseHas('deliveries', [
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'success'
        ]);
    }
}
```

### Running Tests
```bash
# Run all tests
make test

# Run specific test file
docker-compose exec app php artisan test tests/Feature/WebhookDeliveryTest.php

# Run with coverage
docker-compose exec app php artisan test --coverage
```

## 🔄 Pull Request Process

### Before Submitting
1. **Sync with upstream**:
   ```bash
   git fetch upstream
   git checkout main
   git merge upstream/main
   ```

2. **Create feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make your changes** following our guidelines

4. **Run tests**:
   ```bash
   make test
   ```

5. **Commit changes**:
   ```bash
   git add .
   git commit -m "feat: add your feature description"
   ```

6. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```

### PR Template
When creating a PR, include:

```markdown
## Description
Brief description of changes made.

## Type of Change
- [ ] Bug fix
- [ ] New feature  
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] Added new tests
- [ ] Updated existing tests

## Screenshots (if applicable)
Add screenshots for UI changes.

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-reviewed the code
- [ ] Commented complex logic
- [ ] Updated documentation
- [ ] No breaking changes (or documented)

## Related Issues
Closes #123
```

### Review Process
1. **Automated checks** must pass (CI/CD pipeline)
2. **Code review** by maintainer(s)
3. **Testing** in various environments
4. **Documentation** review if applicable
5. **Final approval** and merge

## 💬 Community

### Getting Help
- **GitHub Discussions**: General questions and ideas
- **GitHub Issues**: Bug reports and feature requests  
- **Documentation**: Check existing docs first
- **Code Comments**: Look at inline documentation

### Becoming a Maintainer
Active contributors may be invited to become maintainers:
- Consistent high-quality contributions
- Help with issue triage and PR reviews
- Community involvement and leadership
- Deep understanding of the codebase

### Recognition
We recognize contributors in:
- README.md contributors section
- Release notes for significant contributions
- GitHub contributor graphs
- Annual project reports

## 🎯 Good First Issues

Looking for something to work on? Check out issues labeled:
- `good first issue`: Perfect for newcomers
- `help wanted`: Community help needed
- `documentation`: Docs improvements
- `tests`: Test coverage improvements

## 📜 License

By contributing, you agree that your contributions will be licensed under the same [MIT License](LICENSE) that covers the project.

---

## 🙏 Thank You!

Every contribution, no matter how small, helps make this project better. Thank you for being part of our community!

**Happy Contributing! 🚀**
