<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'user_id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'user_id',
        'user_first_name',
        'user_last_name',
        'user_nickname',
        'user_email',
        'user_password',
        'user_age',
        'user_gender',
        'user_created_at',
        'user_updated_at'
    ];

    protected $useTimestamps    = true;
    protected $createdField     = 'user_created_at';
    protected $updatedField     = 'user_updated_at';

    protected $validationRules  = [
        'user_first_name' => 'required|alpha_space|min_length[2]',
        'user_last_name'  => 'required|alpha_space|min_length[2]',
        'user_nickname'   => 'required|alpha_numeric_space|min_length[3]|is_unique[users.user_nickname]',
        'user_email'      => 'required|valid_email|is_unique[users.user_email]',
        'user_password'   => 'required|min_length[6]',
        'user_age'        => 'required|integer|greater_than_equal_to[13]',
        'user_gender'     => 'required|in_list[male,female,other]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;
}
