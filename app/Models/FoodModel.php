<?php namespace App\Models;

use CodeIgniter\Model;

class FoodModel extends Model
{
    protected $table      = 'food_entries';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'food_start',
        'food_end',
        'location',
        'item',
        'quantity',
        'traffic_light',
        'comment'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
}
