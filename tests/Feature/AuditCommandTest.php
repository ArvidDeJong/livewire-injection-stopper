<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->testComponentsPath = app_path('Livewire/Test');
    File::makeDirectory($this->testComponentsPath, 0755, true, true);
});

afterEach(function () {
    if (File::exists($this->testComponentsPath)) {
        File::deleteDirectory(dirname($this->testComponentsPath));
    }
});

function createTestComponent(string $name, string $content): void
{
    File::put(test()->testComponentsPath.'/'.$name.'.php', $content);
}

it('detects vulnerable properties', function () {
    createTestComponent(
        'VulnerableComponent',
        <<<'PHP'
<?php

namespace App\Livewire\Test;

use Livewire\Component;

class VulnerableComponent extends Component
{
    public bool $isAdmin = false;
    public ?User $user = null;
    public int $maxItems = 10;
    
    public function render()
    {
        return view('livewire.test.vulnerable-component');
    }
}
PHP
    );

    $this->artisan('livewire-injection-stopper:audit')
        ->assertExitCode(1)
        ->expectsOutputToContain('Potential vulnerabilities found');
});

it('passes when properties are locked', function () {
    createTestComponent(
        'SecureComponent',
        <<<'PHP'
<?php

namespace App\Livewire\Test;

use Livewire\Component;
use Livewire\Attributes\Locked;

class SecureComponent extends Component
{
    #[Locked]
    public bool $isAdmin = false;
    
    #[Locked]
    public ?User $user = null;
    
    public string $searchTerm = '';
    
    public function render()
    {
        return view('livewire.test.secure-component');
    }
}
PHP
    );

    $this->artisan('livewire-injection-stopper:audit')
        ->assertExitCode(0)
        ->expectsOutputToContain('No security issues found');
});

it('detects critical severity properties', function () {
    createTestComponent(
        'AdminComponent',
        <<<'PHP'
<?php

namespace App\Livewire\Test;

use Livewire\Component;

class AdminComponent extends Component
{
    public bool $isAdmin = false;
    public string $role = 'user';
    
    public function render()
    {
        return view('livewire.test.admin-component');
    }
}
PHP
    );

    $this->artisan('livewire-injection-stopper:audit')
        ->assertExitCode(1)
        ->expectsOutputToContain('[CRITICAL]');
});

it('detects high severity properties', function () {
    createTestComponent(
        'CartComponent',
        <<<'PHP'
<?php

namespace App\Livewire\Test;

use Livewire\Component;

class CartComponent extends Component
{
    public ?Cart $cart = null;
    public int $maxQuantity = 100;
    
    public function render()
    {
        return view('livewire.test.cart-component');
    }
}
PHP
    );

    $this->artisan('livewire-injection-stopper:audit')
        ->assertExitCode(1)
        ->expectsOutputToContain('[HIGH]');
});

it('scans traits as well', function () {
    File::makeDirectory(app_path('Traits'), 0755, true, true);

    File::put(
        app_path('Traits/TestTrait.php'),
        <<<'PHP'
<?php

namespace App\Traits;

trait TestTrait
{
    public bool $redirect = false;
    public ?User $user = null;
}
PHP
    );

    $this->artisan('livewire-injection-stopper:audit')
        ->assertExitCode(1);

    File::deleteDirectory(app_path('Traits'));
});

it('warns about missing locked import', function () {
    createTestComponent(
        'NoImportComponent',
        <<<'PHP'
<?php

namespace App\Livewire\Test;

use Livewire\Component;

class NoImportComponent extends Component
{
    public bool $isAdmin = false;
    
    public function render()
    {
        return view('livewire.test.no-import-component');
    }
}
PHP
    );

    // Component has vulnerable property but no Locked import
    $this->artisan('livewire-injection-stopper:audit')
        ->assertExitCode(1)
        ->expectsOutputToContain('[CRITICAL]');
});
