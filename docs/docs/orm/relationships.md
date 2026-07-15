---
id: relationships
title: Relationships
sidebar_position: 5
---

# Relationships

Define relationships as methods on your model subclass. LazyMePHP lazy-loads a relation on first access and caches the result on the model instance. Use `with()` on a query to eager-load and avoid N+1 queries.

## Defining relationships

```php
use Core\Model;

class Post extends Model {
    protected static string $table = 'posts';

    // Many posts belong to one user
    public function author() {
        return $this->belongsTo(User::class, 'user_id');
    }

    // One post has many comments
    public function comments() {
        return $this->hasMany(Comment::class, 'post_id');
    }

    // One post has one featured image
    public function featuredImage() {
        return $this->hasOne(Image::class, 'post_id');
    }
}

class User extends Model {
    protected static string $table = 'users';

    // A user has many posts
    public function posts() {
        return $this->hasMany(Post::class, 'user_id');
    }
}

class Tag extends Model {
    protected static string $table = 'tags';
}

class Post extends Model {
    // Many-to-many via pivot table
    public function tags() {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id', 'tag_id');
    }
}
```

## Lazy loading

Relationships are loaded on first property access:

```php
$post = new Post(1);
echo $post->author->name;   // fires SELECT on first access, cached after
echo $post->author->name;   // no second query
```

## Eager loading

Prevents N+1 — one batch query per relation:

```php
$posts = Post::query()->with('author', 'comments')->get();

foreach ($posts as $post) {
    echo $post->author->name;          // no extra queries
    echo count($post->comments);       // no extra queries
}
```

## Supported types

| Method | Description |
|---|---|
| `belongsTo(Model, foreignKey)` | This model holds the FK; returns a single model |
| `hasMany(Model, foreignKey)` | Remote model holds the FK; returns an array |
| `hasOne(Model, foreignKey)` | Remote model holds the FK; returns a single model |
| `belongsToMany(Model, pivot, localKey, foreignKey)` | Many-to-many via pivot table |
