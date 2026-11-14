<?php
require_once __DIR__ . '/../includes/functions.php';
handle_delete('farms', 'farm', 'farm_id', 'Farm',
    'SELECT COUNT(*) FROM planting WHERE farm_id = :id');
