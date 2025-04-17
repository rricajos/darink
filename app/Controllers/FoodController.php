<?php
namespace App\Controllers;

use App\Models\FoodModel;
use CodeIgniter\Controller;

class FoodController extends BaseController
{
  public function list()
  {
    $model = new \App\Models\FoodModel();

    $foods = $model->orderBy('food_start', 'DESC')->findAll();

    return view('list_food', ['foods' => $foods]);
  }
  public function save()
  {
    $model = new FoodModel();

    $data = [
      'food_start' => $this->request->getPost('food_start_date') . ' ' . $this->request->getPost('food_start_time'),
      'food_end' => $this->combineDatetime($this->request->getPost('food_end_date'), $this->request->getPost('food_end_time')),
      'location' => $this->request->getPost('food_location'),
      'item' => $this->request->getPost('food_item'),
      'quantity' => $this->request->getPost('food_quantity'),
      'traffic_light' => $this->request->getPost('food_traffic_light'),
      'comment' => $this->request->getPost('food_note'),
    ];

    $model->insert($data);

    return redirect()->to('/user/dashboard')->with('message', 'Comida registrada con éxito');
  }

  private function combineDatetime($date, $time)
  {
    if (empty($date) || empty($time)) {
      return null;
    }
    return $date . ' ' . $time;
  }
}
