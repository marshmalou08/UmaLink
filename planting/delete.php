<?php
require_once __DIR__ . '/../includes/functions.php';
handle_delete('planting', 'planting', 'planting_id', 'Planting record',
    'SELECT COUNT(*) FROM harvest WHERE planting_id = :id');
