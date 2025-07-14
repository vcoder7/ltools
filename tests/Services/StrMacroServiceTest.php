<?php

namespace Vcoder7\Ltools\Tests\Services;

use Orchestra\Testbench\TestCase;
use Vcoder7\Ltools\Services\StrMacroService;

class StrMacroServiceTest extends TestCase
{
    private StrMacroService $testService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testService = new StrMacroService();
    }

    public function test_single_name(): void
    {
        $this->assertEquals('J', $this->testService->initials('John'));
    }

    public function test_with_two_names(): void
    {
        $this->assertEquals('JD', $this->testService->initials('John Doe'));
    }

    public function test_with_three_names(): void
    {
        $this->assertEquals('AMS', $this->testService->initials('Anna Maria Smith'));
    }

    public function test_with_extra_spaces(): void
    {
        $this->assertEquals('JD', $this->testService->initials('  John   Doe  '));
    }

    public function test_with_lowercase_names(): void
    {
        $this->assertEquals('JD', $this->testService->initials('john doe'));
    }

    public function test_unicode_input(): void
    {
        $this->assertEquals('ÁÖÜ', $this->testService->initials('Álvaro Öztürk Ümkil'));
    }

    public function test_with_empty_string(): void
    {
        $this->assertEquals('', $this->testService->initials(''));
    }
}
