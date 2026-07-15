<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * Source File Generated Automatically
 */

declare(strict_types=1);

namespace App;

class ApiFieldMask {
	private static array $fields = [
		"TestUsers" => ["id","username","email","password","is_active","created_at"]
	];
	public static function get(string $entity): array {
		return self::$fields[$entity] ?? [];
	}
	public static function apply(string $entity, array $data): array {
		$allowed = self::get($entity);
		return array_intersect_key($data, array_flip($allowed));
	}
}