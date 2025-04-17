<?php

/** SQL
lunch_id          INT AUTO_INCREMENT PRIMARY KEY
lunch_tag         ENUM('breakfast', 'brunch', 'lunch', 'snack', 'dinner', 'other') DEFAULT 'lunch'
lunch_location    ENUM('house', 'work', 'school', 'restaurant', 'other') DEFAULT 'house'
lunch_start_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
lunch_end_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
lunch_created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
lunch_updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
user_id           INT NOT NULL
 */
namespace App\Models;

use CodeIgniter\Model;

class LunchModel extends Model
{
  protected $table = 'lunches';
  protected $primaryKey = 'lunch_id';

  protected $allowedFields = [
    'lunch_tag',
    'lunch_location',
    'lunch_start_at',
    'lunch_end_at',
    'lunch_created_at',
    'lunch_updated_at',   
    'user_id',   
  ];

  protected $useTimestamps = true;
  protected $createdField = 'lunch_created_at';
  protected $updatedField = 'lunch_updated_at';

}
