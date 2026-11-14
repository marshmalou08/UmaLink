<?php
require_once __DIR__ . '/../includes/functions.php';
handle_delete('crops', 'crop', 'crop_id', 'Crop',
    'SELECT COUNT(*) FROM planting WHERE crop_id = :id');
