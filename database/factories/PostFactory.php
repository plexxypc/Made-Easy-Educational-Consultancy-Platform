<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * A draft by a staff member.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(5, true));

        return [
            'post_category_id' => PostCategory::factory(),
            'author_id' => User::factory()->staff(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'published_at' => null,
        ];
    }

    /**
     * Public since the given time (default: a day ago).
     *
     * @param  DateTimeInterface|null  $at
     * @return static
     */
    public function published(?DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => $at ?? now()->subDay()]);
    }

    /**
     * Scheduled for the given future time (default: tomorrow).
     *
     * @param  DateTimeInterface|null  $at
     * @return static
     */
    public function scheduled(?DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => $at ?? now()->addDay()]);
    }
}
