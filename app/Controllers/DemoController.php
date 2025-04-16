<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class DemoController extends Controller
{
    public function index()
    {
        if (bORIsession()->get('logged_in')) {
            return redirect()->to('/login');
        }
        
        return view('dashboard');
    }
}
