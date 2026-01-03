<?php

namespace Darvis\LivewireInjectionStopper\Tests\Feature;

use Darvis\LivewireInjectionStopper\Tests\TestCase;
use Illuminate\Support\Facades\File;

class AuditCommandTest extends TestCase
{
    protected string $testComponentsPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testComponentsPath = app_path('Livewire/Test');
        File::makeDirectory($this->testComponentsPath, 0755, true, true);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testComponentsPath)) {
            File::deleteDirectory(dirname($this->testComponentsPath));
        }
        
        parent::tearDown();
    }

    /** @test */
    public function it_detects_vulnerable_properties()
    {
        $this->createTestComponent('VulnerableComponent', <<<'PHP'
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
            ->expectsOutput('⚠️  Potentiële kwetsbaarheden gevonden:');
    }

    /** @test */
    public function it_passes_when_properties_are_locked()
    {
        $this->createTestComponent('SecureComponent', <<<'PHP'
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
            ->expectsOutput('✅ Geen security issues gevonden!');
    }

    /** @test */
    public function it_detects_critical_severity_properties()
    {
        $this->createTestComponent('AdminComponent', <<<'PHP'
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
    }

    /** @test */
    public function it_detects_high_severity_properties()
    {
        $this->createTestComponent('CartComponent', <<<'PHP'
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
    }

    /** @test */
    public function it_scans_traits_as_well()
    {
        File::makeDirectory(app_path('Traits'), 0755, true, true);
        
        File::put(app_path('Traits/TestTrait.php'), <<<'PHP'
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
    }

    /** @test */
    public function it_warns_about_missing_locked_import()
    {
        $this->createTestComponent('NoImportComponent', <<<'PHP'
<?php

namespace App\Livewire\Test;

use Livewire\Component;

class NoImportComponent extends Component
{
    public bool $someFlag = false;
    
    public function render()
    {
        return view('livewire.test.no-import-component');
    }
}
PHP
        );

        $this->artisan('livewire-injection-stopper:audit')
            ->assertExitCode(1)
            ->expectsOutputToContain('⚡ Waarschuwingen:');
    }

    protected function createTestComponent(string $name, string $content): void
    {
        File::put($this->testComponentsPath . '/' . $name . '.php', $content);
    }
}
