<?php
namespace App\Controllers;

use App\Models\FoodModel;
use CodeIgniter\Controller;

class FoodController extends BaseController
{

  protected $foodModel;

  public function __construct()
  {
    $this->foodModel = new FoodModel();
  }

  /**
   * CREATE - mostrar formulario / procesar creación
   */
  public function create()
  {
     // Verificar que hay un lunch activo y usuario logueado
     $lunchId = session()->get('last_lunch_id');
     if (!$lunchId || !$this->user->isLoggedIn()) {
         return redirect()->to('/lunch/create')->with('error', 'Primero registra un almuerzo antes de añadir comida.');
     }
 
     // Obtener y combinar fecha y hora de inicio y fin
     $startDate = $this->request->getPost('food_start_date');
     $startTime = $this->request->getPost('food_start_time');
     $endDate   = $this->request->getPost('food_end_date') ?? $startDate;
     $endTime   = $this->request->getPost('food_end_time') ?? $startTime;
 
     // Datos a guardar
     $data = [
         'food_title'     => $this->request->getPost('food_item'),
         'food_size'      => $this->request->getPost('food_quantity'),
         'food_created_at'=> "$startDate $startTime",
         'food_updated_at'=> "$endDate $endTime",
         'lunch_id'       => $lunchId
     ];
 
     // Insertar en la base de datos
     $this->foodModel->insert($data);
     $foodId = $this->foodModel->getInsertID();
 
     // Guardar semáforo solo si fue indicado
     $lightColor = $this->request->getPost('food_traffic_light');
     $lightNote  = $this->request->getPost('food_note');
 
     if ($lightColor || $lightNote) {
         $lightModel = new \App\Models\LightModel();
         $lightModel->insert([
             'light_color'   => $lightColor,
             'light_message' => $lightNote,
             'food_id'       => $foodId
         ]);
     }
 
     return redirect()->to("/lunch/{$lunchId}")->with('message', 'Comida registrada con éxito.');
 }






  private function combineDatetime($date, $time)
  {
    if (empty($date) || empty($time)) {
      return null;
    }
    return $date . ' ' . $time;
  }
}
