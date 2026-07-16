<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

namespace Core\Session;

/**
 * Static facade over SessionStore.
 *
 *   Session::put('user_id', $id);
 *   Session::get('user_id');
 *
 * Previously this class also declared the storage methods (get/put/has/...)
 * directly as public instance methods, which meant PHP resolved static calls
 * like Session::get(...) to "non-static method cannot be called statically"
 * instead of ever reaching __callStatic below — the facade never actually
 * worked. The real implementation now lives in SessionStore; this class is a
 * pure forwarder so the static calls above are unambiguous and work.
 *
 * Need a typed instance (e.g. a typed property) instead of static calls? Use
 * SessionStore::getInstance() directly.
 *
 * @method static mixed  get(string $key, mixed $default = null)
 * @method static void   put(string $key, mixed $value)
 * @method static void   set(string $key, mixed $value)
 * @method static bool   has(string $key)
 * @method static mixed  pull(string $key, mixed $default = null)
 * @method static void   forget(array|string $keys)
 * @method static void   flush()
 * @method static array  all()
 * @method static void   flash(string $key, mixed $value)
 * @method static mixed  getFlash(string $key, mixed $default = null)
 * @method static bool   hasFlash(string $key)
 * @method static array  pullAllFlash()
 * @method static bool   regenerate(bool $deleteOld = true)
 * @method static string getId()
 */
class Session
{
    public static function getInstance(): SessionStore
    {
        return SessionStore::getInstance();
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return SessionStore::getInstance()->$method(...$args);
    }
}
