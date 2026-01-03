# Testing Guide

## Running Tests

The package includes a comprehensive test suite covering all functionality.

### Prerequisites

```bash
# Navigate to package directory
cd vendor/darvis/livewire-injection-stopper

# Install dev dependencies
composer install
```

### Run All Tests

```bash
# Using composer script
composer test

# Or directly with PHPUnit
vendor/bin/phpunit
```

### Run Specific Tests

```bash
# Middleware tests only
vendor/bin/phpunit tests/Feature/MiddlewareTest.php

# Audit command tests only
vendor/bin/phpunit tests/Feature/AuditCommandTest.php

# Config tests only
vendor/bin/phpunit tests/Unit/ConfigTest.php
```

### Coverage Reports

```bash
# Generate HTML coverage report
composer test-coverage

# View report
open coverage/index.html
```

## Test Coverage

### Middleware Tests (9 tests)

Tests for the blocking middleware:

- ✅ Allows normal browser requests
- ✅ Blocks Python Requests user agent
- ✅ Blocks curl requests
- ✅ Blocks wget requests
- ✅ Blocks bot user agents
- ✅ Blocks configured IP addresses
- ✅ Allows whitelisted routes
- ✅ Case-insensitive matching
- ✅ Handles requests without user agent

### Audit Command Tests (6 tests)

Tests for the security audit command:

- ✅ Detects vulnerable properties
- ✅ Passes when properties are locked
- ✅ Detects critical severity issues
- ✅ Detects high severity issues
- ✅ Scans traits as well as components
- ✅ Warns about missing Locked imports

### Config Tests (5 tests)

Tests for configuration handling:

- ✅ Loads default configuration
- ✅ Has correct default blocked user agents
- ✅ Allows custom configuration
- ✅ Has default response status 403
- ✅ Has logging enabled by default

## Writing Tests

### Test Structure

```php
<?php

namespace Darvis\LivewireInjectionStopper\Tests\Feature;

use Darvis\LivewireInjectionStopper\Tests\TestCase;

class MyTest extends TestCase
{
    /** @test */
    public function it_does_something()
    {
        // Arrange
        $this->setupTestData();
        
        // Act
        $result = $this->performAction();
        
        // Assert
        $this->assertTrue($result);
    }
}
```

### Testing Middleware

```php
use Darvis\LivewireInjectionStopper\Middleware\BlockInjectionAttempts;
use Illuminate\Support\Facades\Route;

protected function setUp(): void
{
    parent::setUp();
    
    Route::middleware(BlockInjectionAttempts::class)
        ->get('/test', fn() => response()->json(['success' => true]));
}

/** @test */
public function it_blocks_specific_user_agent()
{
    $response = $this->get('/test', [
        'User-Agent' => 'python-requests/2.28.0',
    ]);
    
    $response->assertStatus(403);
}
```

### Testing Audit Command

```php
/** @test */
public function it_detects_vulnerable_properties()
{
    $this->createTestComponent('VulnerableComponent', <<<'PHP'
<?php
class VulnerableComponent extends Component
{
    public bool $isAdmin = false;
}
PHP
    );
    
    $this->artisan('livewire-injection-stopper:audit')
        ->assertExitCode(1)
        ->expectsOutput('⚠️  Potential vulnerabilities found:');
}
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: mbstring, pdo, pdo_sqlite
          
      - name: Install Dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: Run Tests
        run: composer test
        
      - name: Security Audit
        run: php artisan livewire-injection-stopper:audit
```

## Test Database

Tests use an in-memory SQLite database by default. No additional setup required.

## Debugging Tests

### Enable Verbose Output

```bash
vendor/bin/phpunit --verbose
```

### Run Single Test

```bash
vendor/bin/phpunit --filter test_method_name
```

### Debug with dd()

```php
/** @test */
public function it_debugs_something()
{
    $data = $this->getData();
    dd($data); // Dumps and dies
    
    $this->assertTrue(true);
}
```

## Best Practices

1. **One assertion per test** - Keep tests focused
2. **Descriptive names** - Use `it_does_something` format
3. **Arrange-Act-Assert** - Follow AAA pattern
4. **Clean up** - Use `tearDown()` to clean test data
5. **Mock external services** - Don't make real API calls

## Contributing Tests

When contributing:

1. All new features must include tests
2. Maintain or improve code coverage
3. Follow existing test patterns
4. Run full test suite before submitting PR

```bash
# Before submitting
composer test
```
