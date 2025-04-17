<?php namespace App\Models;

use CodeIgniter\Model;

class EmojiModel extends Model
{
    protected $table      = 'foods';
    protected $primaryKey = 'food_id';

    protected $allowedFields = [
        'emoji_id ',
        'emoji_unicode'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'created_at';
    
}
