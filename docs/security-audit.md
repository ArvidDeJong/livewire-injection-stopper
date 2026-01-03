# Livewire Security Audit Command

## What It Does

The audit command scans your Livewire components and traits for **property injection vulnerabilities**. This is a critical security feature that prevents attackers from manipulating component properties via browser console or modified requests.

## Why You Need This

### The Problem

Livewire components expose public properties to the frontend. Attackers can modify these properties using browser developer tools or intercepted requests, potentially:

- Elevating privileges (`$isAdmin = true`)
- Bypassing limits (`$maxItems = 999999`)
- Manipulating prices (`$price = 0.01`)
- Accessing unauthorized data (`$userId = 1`)

### The Solution

Use Livewire's `#[Locked]` attribute to protect properties that users shouldn't modify.

## Running the Audit

```bash
php artisan livewire-injection-stopper:audit
```

## Understanding the Output

### ✅ No Issues Found

```
🔍 Scanning Livewire components for security issues...

✅ No security issues found!
```

Your components are secure!

### ⚠️ Vulnerabilities Detected

```
🔍 Scanning Livewire components for security issues...

⚠️  Potential vulnerabilities found:

[CRITICAL]
  📍 app/Livewire/Admin/UserEdit.php:15
     Property: $isAdmin (bool)
     💡 Add #[Locked] attribute above this property

[HIGH]
  📍 app/Livewire/Cart/CartComponent.php:20
     Property: $cart (?Cart)
     💡 Add #[Locked] attribute above this property

[MEDIUM]
  📍 app/Livewire/Blog/BlogEdit.php:25
     Property: $published (bool)
     💡 Add #[Locked] attribute above this property

Total: 3 vulnerable properties found
```

## Severity Levels

### CRITICAL - Immediate security risk

Properties containing: `admin`, `role`, `permission`, `auth`

**Impact:** Privilege escalation, unauthorized access  
**Action:** Fix immediately

### HIGH - Significant security risk

- Properties containing: `user`, `client`, `cart`, `max`, `limit`
- Model instances without `#[Locked]`

**Impact:** Data manipulation, business logic bypass  
**Action:** Fix as soon as possible

### MEDIUM - Potential security risk

- Boolean flags (`$published`, `$active`, `$redirect`)
- Configuration properties (`$locale`, `$config`)

**Impact:** Unexpected behavior, minor exploits  
**Action:** Review and fix

## Fixing Vulnerabilities

### Before (Vulnerable)

```php
class UserEdit extends Component
{
    public bool $isAdmin = false;
    public ?User $user = null;
    public int $maxItems = 10;
    
    public function save()
    {
        // Attacker could set $isAdmin = true!
        if ($this->isAdmin) {
            // Grant admin access
        }
    }
}
```

### After (Secure)

```php
use Livewire\Attributes\Locked;

class UserEdit extends Component
{
    #[Locked]
    public bool $isAdmin = false;
    
    #[Locked]
    public ?User $user = null;
    
    #[Locked]
    public int $maxItems = 10;
    
    // Properties users CAN modify
    public string $name = '';
    public string $email = '';
    
    public function save()
    {
        // Now $isAdmin cannot be manipulated!
        if ($this->isAdmin) {
            // Safe to use
        }
    }
}
```

## What Properties Should Be Locked?

### Always Lock

- ✅ Model instances (`User`, `Cart`, `Invoice`)
- ✅ Authorization flags (`$isAdmin`, `$canEdit`)
- ✅ Limits and maximums (`$maxQuantity`, `$limit`)
- ✅ Configuration (`$locale`, `$settings`)
- ✅ Internal state (`$redirect`, `$available`)

### Leave Unlocked

- ✅ Form inputs (`$name`, `$email`, `$message`)
- ✅ Search/filter values (`$searchTerm`, `$sortBy`)
- ✅ Pagination parameters
- ✅ User selections (`$selectedQuantity`, `$checked`)

## Automated Scanning in CI/CD

Add to your CI pipeline:

```yaml
# .github/workflows/tests.yml
- name: Security Audit
  run: php artisan livewire-injection-stopper:audit
```

The command returns:
- Exit code `0` - No issues found
- Exit code `1` - Vulnerabilities detected

## Real-World Attack Example

See [Livewire Security Best Practices](livewire-security.md#real-world-attack-example) for detailed attack scenarios and how to prevent them.
