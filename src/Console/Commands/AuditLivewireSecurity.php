<?php

declare(strict_types=1);

namespace Darvis\LivewireInjectionStopper\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Audit Livewire components for potential security vulnerabilities.
 *
 * This command scans all Livewire components and traits for public properties
 * that should be protected with the #[Locked] attribute to prevent property
 * injection attacks.
 */
final class AuditLivewireSecurity extends Command
{
    protected $signature = 'livewire-injection-stopper:audit';

    protected $description = 'Audit Livewire components for potential security vulnerabilities';

    /** @var array<int, array{file: string, line: int, property: string, type: string, severity: string}> */
    private array $vulnerabilities = [];

    /** @var array<int, array{file: string, message: string}> */
    private array $warnings = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Scanning Livewire components for security issues...');
        $this->newLine();

        $livewirePath = app_path('Livewire');
        $traitsPath = app_path('Traits');

        $this->scanDirectory($livewirePath);
        $this->scanDirectory($traitsPath);

        $this->displayResults();

        return $this->vulnerabilities ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Scan a directory for PHP files.
     */
    private function scanDirectory(string $path): void
    {
        if (!File::exists($path)) {
            return;
        }

        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $this->scanFile($file->getPathname());
            }
        }
    }

    /**
     * Scan a single PHP file for vulnerable properties.
     */
    private function scanFile(string $filePath): void
    {
        $content = File::get($filePath);
        $lines = explode("\n", $content);
        $relativePath = str_replace(base_path().'/', '', $filePath);

        $hasLockedImport = str_contains($content, 'use Livewire\Attributes\Locked');
        $isLivewireComponent = str_contains($content, 'extends Component') ||
                               str_contains($content, 'trait ') && str_contains($content, 'Trait');

        if (!$isLivewireComponent) {
            return;
        }

        foreach ($lines as $lineNumber => $line) {
            $actualLineNumber = $lineNumber + 1;

            if (preg_match('/^\s+public\s+(bool|int|string|\?[A-Z]\w+)\s+\$(\w+)\s*=/', $line, $matches)) {
                $type = $matches[1];
                $propertyName = $matches[2];

                $previousLine = $lines[$lineNumber - 1] ?? '';
                $isLocked = str_contains($previousLine, '#[Locked]');

                if (!$isLocked && $this->isSuspiciousProperty($propertyName, $type)) {
                    $this->vulnerabilities[] = [
                        'file' => $relativePath,
                        'line' => $actualLineNumber,
                        'property' => $propertyName,
                        'type' => $type,
                        'severity' => $this->getSeverity($propertyName),
                    ];
                }
            }
        }

        if (!$hasLockedImport && preg_match('/^\s+public\s+/', $content)) {
            $this->warnings[] = [
                'file' => $relativePath,
                'message' => 'No Locked attribute import found, but has public properties',
            ];
        }
    }

    /**
     * Determine if a property name/type is suspicious and should be locked.
     */
    private function isSuspiciousProperty(string $name, string $type): bool
    {
        $suspiciousPatterns = [
            'admin', 'role', 'permission', 'auth',
            'max', 'min', 'limit',
            'redirect', 'available', 'allowed',
            'cart', 'user', 'client', 'model',
            'locale', 'config', 'setting',
        ];

        $nameLower = strtolower($name);

        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($nameLower, $pattern)) {
                return true;
            }
        }

        if (preg_match('/^\?[A-Z]/', $type)) {
            return true;
        }

        if ($type === 'bool' && !in_array($nameLower, ['checked', 'selected', 'enabled', 'visible'])) {
            return true;
        }

        return false;
    }

    /**
     * Get the severity level for a vulnerable property.
     */
    private function getSeverity(string $propertyName): string
    {
        $critical = ['admin', 'role', 'permission', 'auth', 'isadmin'];
        $high = ['max', 'limit', 'user', 'client', 'cart'];

        $nameLower = strtolower($propertyName);

        foreach ($critical as $pattern) {
            if (str_contains($nameLower, $pattern)) {
                return 'CRITICAL';
            }
        }

        foreach ($high as $pattern) {
            if (str_contains($nameLower, $pattern)) {
                return 'HIGH';
            }
        }

        return 'MEDIUM';
    }

    /**
     * Display the audit results.
     */
    private function displayResults(): void
    {
        if (empty($this->vulnerabilities) && empty($this->warnings)) {
            $this->info('✅ No security issues found!');

            return;
        }

        if ($this->vulnerabilities) {
            $this->error('⚠️  Potential vulnerabilities found:');
            $this->newLine();

            $grouped = collect($this->vulnerabilities)->groupBy('severity');

            foreach (['CRITICAL', 'HIGH', 'MEDIUM'] as $severity) {
                if ($items = $grouped->get($severity)) {
                    $this->warn("[$severity]");
                    foreach ($items as $vuln) {
                        $this->line("  📍 {$vuln['file']}:{$vuln['line']}");
                        $this->line("     Property: \${$vuln['property']} ({$vuln['type']})");
                        $this->line('     💡 Add #[Locked] attribute above this property');
                        $this->newLine();
                    }
                }
            }

            $this->info('Total: '.count($this->vulnerabilities).' vulnerable properties found');
        }

        if ($this->warnings) {
            $this->newLine();
            $this->warn('⚡ Warnings:');
            foreach ($this->warnings as $warning) {
                $this->line("  📍 {$warning['file']}");
                $this->line("     {$warning['message']}");
                $this->newLine();
            }
        }

        $this->newLine();
        $this->info('📖 See https://livewire.laravel.com/docs/locked for more information about #[Locked]');
    }
}
