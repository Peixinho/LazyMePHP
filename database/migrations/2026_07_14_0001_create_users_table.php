<?php

return [
    'up' => function ($db): void {
        $db->query("
            -- CREATE TABLE example (
            --     id    INTEGER PRIMARY KEY AUTOINCREMENT,
            --     name  TEXT    NOT NULL
            -- )
        ");
    },

    'down' => function ($db): void {
        // $db->query("DROP TABLE IF EXISTS example");
    },
];