<?php

namespace Core\DB;

/**
 * SmartHydrator - Automatically aliases fields and hydrates models from raw SQL results.
 * 
 * Handles field collisions by prefixing fields with table aliases (A_id, B_name, etc.)
 * and then hydrating them into proper model objects.
 * 
 * @example
 * // Basic usage with SELECT * and automatic field aliasing
 * $hydrator = new SmartHydrator([
 *     'A' => \Models\Utilizadores::class,
 *     'B' => \Models\Unidades::class,
 * ]);
 * 
 * $sql = "SELECT A.*, B.* FROM Utilizadores A JOIN Unidades B ON A.id_unidade = B.id";
 * $rows = $db->Query($sql)->fetchAll();
 * $hydrated = $hydrator->hydrateRows($rows);
 * // Returns: [['A' => Utilizadores, 'B' => Unidades], ...]
 * 
 * @example
 * // Complex query with custom aliases and field selection
 * $hydrator = new SmartHydrator([
 *     'U' => \Models\Utilizadores::class,
 *     'UN' => \Models\Unidades::class,
 *     'E' => \Models\Episodio::class,
 * ]);
 * 
 * $sql = "
 *     SELECT 
 *         U.id, U.mecanografico, U.id_unidade,
 *         UN.unidade,
 *         E.episodio, E.data_inicio,
 *         COUNT(*) as total_movements
 *     FROM Utilizadores U
 *     LEFT JOIN Unidades UN ON U.id_unidade = UN.id
 *     LEFT JOIN Episodio E ON E.id_unidade = UN.id
 *     GROUP BY U.id, UN.id
 * ";
 * $rows = $db->Query($sql)->fetchAll();
 * $hydrated = $hydrator->hydrateRows($rows);
 * // Returns: [['U' => Utilizadores, 'UN' => Unidades, 'E' => Episodio], ...]
 * // Note: total_movements remains as raw data (not part of any model)
 * 
 * @example
 * // Single row hydration
 * $hydrator = new SmartHydrator(['A' => \Models\Utilizadores::class]);
 * $row = $db->Query("SELECT * FROM Utilizadores WHERE id = 1")->fetchArray();
 * $model = $hydrator->hydrate($row);
 * // Returns: ['A' => Utilizadores]
 */

class SmartHydrator {
    private array $modelMap;
    private array $fieldCache = [];

    public function __construct(array $modelMap) {
        $this->modelMap = $modelMap;
        foreach ($modelMap as $alias => $class) {
            $this->fieldCache[$alias] = $this->getModelFields($class);
        }
    }

    private function getModelFields(string $class): array {
        $fields = [];
        $reflect = new \ReflectionClass($class);
        foreach ($reflect->getProperties(\ReflectionProperty::IS_PROTECTED) as $prop) {
            $field = $prop->getName();
            if (strpos($field, '__') === 0) continue;
            $fields[] = $field;
        }
        return $fields;
    }

    public function aliasRow(array $row): array {
        $aliased = [];
        $used = [];
        foreach ($this->modelMap as $alias => $class) {
            foreach ($this->fieldCache[$alias] as $field) {
                if (array_key_exists($field, $row)) {
                    $aliased[$alias . '_' . $field] = $row[$field];
                    $used[$field] = true;
                }
            }
        }
        // Optionally, add any unused fields as-is
        foreach ($row as $k => $v) {
            if (!isset($used[$k])) {
                $aliased[$k] = $v;
            }
        }
        return $aliased;
    }

    public function hydrate(array $row): array {
        // Alias the row first
        $aliased = $this->aliasRow($row);
        $models = [];
        foreach ($this->modelMap as $alias => $class) {
            $data = [];
            $prefix = $alias . '_';
            foreach ($aliased as $key => $value) {
                if (strpos($key, $prefix) === 0) {
                    $field = substr($key, strlen($prefix));
                    $data[$field] = $value;
                }
            }
            $models[$alias] = new $class($data);
        }
        return $models;
    }

    public function hydrateRows(array $rows): array {
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }
} 