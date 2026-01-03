# Livewire Security Best Practices

## Understanding Property Injection Attacks

Livewire components expose public properties to the frontend JavaScript. While this enables reactive interfaces, it also creates a security risk if not properly managed.

## How Attacks Work

### Attack Vector

Attackers can use browser developer tools or intercept HTTP requests to modify Livewire component properties:

```javascript
// In browser console
Livewire.find('component-id').set('isAdmin', true);
Livewire.find('component-id').set('price', 0.01);
```

### What Can Be Exploited

Any public property in a Livewire component can be modified by an attacker unless protected with `#[Locked]`.

## Real-World Attack Example

### Vulnerable Component

```php
class CheckoutComponent extends Component
{
    public float $totalPrice = 100.00;
    public bool $isPremiumUser = false;
    
    public function checkout()
    {
        $discount = $this->isPremiumUser ? 0.5 : 0;
        $finalPrice = $this->totalPrice * (1 - $discount);
        
        // Process payment for $finalPrice
    }
}
```

### The Attack

```javascript
// In browser console
Livewire.find('component-id').set('totalPrice', 0.01);
Livewire.find('component-id').set('isPremiumUser', true);
// Attacker pays $0.005 instead of $100
```

### Secure Version

```php
use Livewire\Attributes\Locked;

class CheckoutComponent extends Component
{
    #[Locked]
    public float $totalPrice = 100.00;
    
    #[Locked]
    public bool $isPremiumUser = false;
    
    public function checkout()
    {
        // Properties cannot be manipulated!
        $discount = $this->isPremiumUser ? 0.5 : 0;
        $finalPrice = $this->totalPrice * (1 - $discount);
        
        // Safe to process payment
    }
}
```

## Common Vulnerability Patterns

### 1. Authorization Flags

```php
// ❌ VULNERABLE
public bool $isAdmin = false;
public bool $canEdit = false;

// ✅ SECURE
#[Locked]
public bool $isAdmin = false;

#[Locked]
public bool $canEdit = false;
```

### 2. Model Instances

```php
// ❌ VULNERABLE
public ?User $user = null;
public ?Cart $cart = null;

// ✅ SECURE
#[Locked]
public ?User $user = null;

#[Locked]
public ?Cart $cart = null;
```

### 3. Business Logic Limits

```php
// ❌ VULNERABLE
public int $maxQuantity = 10;
public float $discountRate = 0.1;

// ✅ SECURE
#[Locked]
public int $maxQuantity = 10;

#[Locked]
public float $discountRate = 0.1;
```

### 4. Configuration Values

```php
// ❌ VULNERABLE
public string $locale = 'en';
public bool $debugMode = false;

// ✅ SECURE
#[Locked]
public string $locale = 'en';

#[Locked]
public bool $debugMode = false;
```

## Mass Assignment Protection

Even with Livewire's `#[Locked]` attribute, always validate and sanitize data:

```php
use Livewire\Attributes\Locked;

class UserProfileComponent extends Component
{
    #[Locked]
    public User $user;
    
    public string $name = '';
    public string $email = '';
    
    public function save()
    {
        // Validate input
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);
        
        // Only update specific fields
        $this->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);
    }
}
```

## Security Checklist

Before deploying a Livewire component:

- [ ] All properties that shouldn't be modified have `#[Locked]`
- [ ] Validation rules exist for all user input
- [ ] Authorization checks are in place where needed
- [ ] No sensitive data in public properties (passwords, tokens)
- [ ] Type hints used for all properties
- [ ] Security audit command passes

## Testing for Vulnerabilities

### Manual Testing

1. Open browser developer tools
2. Find your Livewire component ID
3. Try to modify protected properties:

```javascript
Livewire.find('component-id').set('isAdmin', true);
```

If the property changes, it's vulnerable!

### Automated Testing

Run the security audit command:

```bash
php artisan livewire-injection-stopper:audit
```

## Additional Resources

- [Livewire Security Documentation](https://livewire.laravel.com/docs/locked)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

## Getting Help

If you discover a security vulnerability:

1. **Do NOT** open a public issue
2. Email: info@arvid.nl
3. Include details about the vulnerability
4. Allow time for a fix before public disclosure
