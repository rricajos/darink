<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'userss';
    protected $primaryKey = 'id';
    protected $allowedFields = ['username', 'password'];
}
