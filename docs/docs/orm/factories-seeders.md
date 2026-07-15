---
id: factories-seeders
title: Factories & Seeders
sidebar_position: 11
---

# Factories & Seeders

## Seeders

Seeders populate the database with initial or test data.

```bash
php LazyMePHP make:seeder UserSeeder    # scaffold App/Seeders/UserSeeder.php
php LazyMePHP db:seed                   # run all seeders
php LazyMePHP db:seed --class=UserSeeder
```

```php
// App/Seeders/UserSeeder.php
use Core\Seeder\Seeder;

class UserSeeder extends Seeder {
    public function run(): void {
        $this->insert('users', ['name' => 'Admin', 'email' => 'admin@example.com']);
        $this->insert('users', ['name' => 'Alice', 'email' => 'alice@example.com']);
    }
}
```

## Factories

Factories generate model instances for tests and seeding.

```bash
php LazyMePHP make:factory PostFactory   # scaffold App/Factories/PostFactory.php
```

```php
// App/Factories/PostFactory.php
use Core\Factory\Factory;

class PostFactory extends Factory {
    protected string $table = 'posts';

    public function definition(): array {
        static $n = 0; $n++;
        return [
            'title'   => "Post {$n}",
            'body'    => 'Lorem ipsum dolor sit amet',
            'user_id' => 1,
        ];
    }
}
```

### Factory usage

```php
// Unsaved model instance
$post = PostFactory::new()->make();

// Saved to DB
$post = PostFactory::new()->create();

// 10 saved models
$posts = PostFactory::new()->count(10)->create();

// Override specific attributes
$post = PostFactory::new()->state(['user_id' => 5])->create();

// Combine count + state
$posts = PostFactory::new()->count(3)->state(['user_id' => 2])->create();
```

### Using factories in tests

```php
// In a Pest/PHPUnit test
$user = UserFactory::new()->create();
$posts = PostFactory::new()->count(5)->state(['user_id' => $user->getPrimaryKey()])->create();

$page = Post::query()->where('user_id', $user->getPrimaryKey())->paginate(10);
expect($page['total'])->toBe(5);
```
