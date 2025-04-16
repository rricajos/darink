<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class DashboardController extends Controller
{
    public function index()
    {
    
        return view('dashboard');
    }


    public function addFood() {
        return view('add_food');
    }
}
