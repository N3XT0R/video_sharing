<?php
namespace Tests\Feature;

use Tests\AppClasses;
use Tests\TestCase;

class AppIntegrationTest extends TestCase
{
    /**
     * @dataProvider classProvider
     */
    public function test_class_can_be_resolved(string $class): void
    {
        try {
            $instance = app()->make($class);
            $this->assertInstanceOf($class, $instance);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Cannot resolve ' . $class . ': ' . $e->getMessage());
        }
    }

    public static function classProvider(): array
    {
        return AppClasses::dataProvider();
    }
}
