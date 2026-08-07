<?php

use Core\Migration\Blueprint;
use Core\Migration\Schema;

return [
    'up' => function (): void {
        // Example — uncomment and adjust. Schema emits the correct DDL for DB_TYPE.
        // Schema::create('example', function (Blueprint $t): void {
        //     $t->id();
        //     $t->string('name');
        //     $t->timestamps();
        // });
    },

    'down' => function (): void {
        // Schema::dropIfExists('example');
    },
];
