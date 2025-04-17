<?php
namespace App\Models;

use CodeIgniter\Model;

class LightModel extends Model
{
  protected $table = 'lights';
  protected $primaryKey = 'light_id';

  protected $allowedFields = [
    'light_id',
    'light_color',
    'light_message',
    'light_created_at',
    'light_updated_at',
    'food_id',
    'emoji_id	',
  ];

  protected $useTimestamps = true;
  protected $createdField = 'light_created_at';
  protected $updatedField = 'light_updated_at';

}
