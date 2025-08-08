<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\AppClasses;

class AppClassesTest extends TestCase
{
    /**
     * @dataProvider classProvider
     */
    public function test_class_exists(string $class): void
    {
        $this->assertTrue(class_exists($class), "Class $class does not exist");
    }

    public static function classProvider(): array
    {
        return AppClasses::dataProvider();
    }
}
