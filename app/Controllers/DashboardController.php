<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }
    
    public function reindex()
    {
        return redirect()->to('/user/dashboard');
    }


    public function addFood()
    {
        return view('add_food');
    }
}
