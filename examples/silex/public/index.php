<?php

require __DIR__ . '/../../../vendor/autoload.php';

use SilexTodos\App;

$app = new App(__DIR__ . '/../todos.db');
$app->run();
