<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'section' => $this->faker->randomElement(['legal', 'help', 'docs', 'about']),
            'content' => "## {$title}\n\n".$this->faker->paragraphs(3, true),
        ];
    }

    public function imprint(): static
    {
        return $this->state(fn() => [
            'slug' => 'imprint',
            'title' => 'Imprint',
            'section' => 'legal',
            'content' => "## Imprint\n\n".$this->faker->paragraphs(2, true),
        ]);
    }

    public function privacy(): static
    {
        return $this->state(fn() => [
            'slug' => 'privacy',
            'title' => 'Privacy Policy',
            'section' => 'legal',
            'content' => "## Privacy Policy\n\n".$this->faker->paragraphs(3, true),
        ]);
    }

    public function withContent(string $markdown): static
    {
        return $this->state(fn() => ['content' => $markdown]);
    }

    public function withSlug(string $slug): static
    {
        return $this->state(fn() => ['slug' => $slug]);
    }

    public function inSection(string $section): static
    {
        return $this->state(fn() => ['section' => $section]);
    }
}