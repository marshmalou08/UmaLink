<?php
require_once __DIR__ . '/../includes/functions.php';
handle_delete('farmers', 'farmer', 'farmer_id', 'Farmer',
    'SELECT COUNT(*) FROM farm WHERE farmer_id = :id');
