<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FoodModel;

class FoodController extends BaseController
{
    protected $foodModel;

    public function __construct()
    {
        $this->foodModel = new FoodModel();
    }

    // POST /food/create
    public function create()
    {
        $data = $this->request->getPost();
    
        // Insertar el Food
        $foodData = [
            'food_title'  => $data['food_title'],
            'food_size'   => $data['food_size'],
            'food_amount' => $data['food_amount'],
            'lunch_id'    => $data['lunch_id'],
        ];
    
        $foodId = $this->foodModel->insert($foodData, true); // true = devolver el ID insertado
    
        if (!$foodId) {
            return redirect()->back()->withInput()->with('errors', $this->foodModel->errors());
        }
    
        // Insertar el Light asociado
        $lightModel = new \App\Models\LightModel();
        $lightData = [
            'light_color'   => $data['light_color'],      // verde, amarillo o rojo
            'light_message' => $data['light_message'],    // comentario breve
            'food_id'       => $foodId,                   // ¡asociamos correctamente!
        ];
        $lightModel->insert($lightData);
    
        return redirect()->back()->with('success', 'Food y Light creados correctamente');
    }
    



    public function edit($id = null)
    {
        $food = $this->foodModel->find($id);

        if (!$food) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Food no encontrado');
        }

        return view('food_edit', ['food' => $food]);
    }

    public function update($id = null)
    {
        $data = $this->request->getPost();

        if (!$this->foodModel->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->foodModel->errors());
        }

        // Redireccionar de vuelta al lunch_edit
        return redirect()->to('/lunch/' . $data['lunch_id'])->with('success', 'Food actualizado correctamente');
    }

    // POST /food/delete/{id}
    public function delete($id = null)
    {
        if (!$this->foodModel->delete($id)) {
            return redirect()->back()->with('error', 'No se pudo eliminar el food');
        }

        return redirect()->back()->with('success', 'Food eliminado correctamente');
    }
}
