<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class DashboardController extends BaseController
{
    public function index()
    {
        return view('dashboard');
    }
    
    public function reindex()
    {
        return redirect()->to('/user/dashboard');
    }

}
