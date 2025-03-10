<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Database\Database; // ✅ Import Database Class

class Home extends BaseController
{
    public function index(): string
    {
        return view('landing_page');
    }

    public function a(): string
    {
        // ✅ Get the database connection
        $db = \Config\Database::connect();

        // ✅ Test the connection by running a simple query
        try {
            $query = $db->query("SELECT 1 AS test_connection");
            $result = $query->getRow();

            if ($result) {
                return "✅ Database Connection Successful!";
            } else {
                return "❌ Database Connection Failed!";
            }
        } catch (\Exception $e) {
            return "❌ Error: " . $e->getMessage();
        }
    }
}
