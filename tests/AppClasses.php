<?php
namespace Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AppClasses
{
    /**
     * Get all class names within app directory.
     *
     * @return string[]
     */
    public static function names(): array
    {
        $basePath = realpath(__DIR__.'/../app');
        $classes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative = ltrim(str_replace($basePath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $class = 'App\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);
                $classes[] = $class;
            }
        }
        sort($classes);
        return $classes;
    }

    /**
     * @return array<int,array{0:string}>
     */
    public static function dataProvider(): array
    {
        return array_map(fn($class) => [$class], self::names());
    }
}
