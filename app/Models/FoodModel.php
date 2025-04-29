<?php namespace App\Models;

use CodeIgniter\Model;

class FoodModel extends Model
{
    protected $table      = 'foods';
    protected $primaryKey = 'food_id';

    protected $allowedFields = [

        'food_title',
        'food_amount',
        'food_size',
        'food_created_at',
        'food_updated_at',
        'lunch_id'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'food_created_at';
    protected $updatedField  = 'food_updated_at';
}
