<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\LunchModel;



class AppController extends BaseController
{
    protected $lunchModel;

    public function __construct()
    {
        $this->lunchModel = new LunchModel();
    }

    // GET /lunch
    public function index()
    {
        $user_id = $this->user->id();
        if ($user_id == null) {
            return view('index');
        }
        $lunches = $this->lunchModel->where('user_id', $user_id)->findAll();
        return view('lunch_index', ['lunches' => $lunches]);

    }

    // GET /lunch/new
    public function new()
    {
        return view('lunch_form');
    }

    // POST /lunch
    public function create()
    {
        // Recoger solo los campos necesarios y confiar en nosotros, no en el usuario
        $data = [
            'lunch_tag' => $this->request->getPost('lunch_tag'),
            'lunch_location' => $this->request->getPost('lunch_location'),
            'lunch_start_at' => $this->request->getPost('lunch_start_at'),
            'lunch_end_at' => $this->request->getPost('lunch_end_at'),
            'user_id' => $this->user->id()
        ];

        // (Opcional pero recomendado) Validar mínimos manualmente
        if (empty($data['lunch_tag']) || empty($data['lunch_location'])) {
            return redirect()->back()->withInput()->with('error', 'Tag y ubicación son obligatorios.');
        }

        if (!$this->lunchModel->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->lunchModel->errors());
        }

        return redirect()->to('/lunch/' . $this->lunchModel->getInsertID())->with('success', 'Lunch creado correctamente');
    }


    // GET /lunch/{id}
    public function edit($id = null)
    {
        $lunch = $this->lunchModel->find($id);
        if (!$lunch) {
            return redirect()->back()->with('error', 'Lunch denegado');
        }


        // Proteger: solo el dueño puede editar
        if ($lunch['user_id'] != $this->user->id()) {
            return redirect()->back()->with('error', 'Permiso/Acceso denegado');

        } else {


            $foodModel = new \App\Models\FoodModel();
            $foods = $foodModel
                ->select('foods.*, lights.light_color')
                ->join('lights', 'lights.food_id = foods.food_id', 'left')
                ->where('lunch_id', $id)
                ->findAll();

            return view('lunch_edit', [
                'lunch' => $lunch,
                'foods' => $foods
            ]);

        }

    }




    // POST /lunch/update/{id}
    public function update($id = null)
    {
        $lunch = $this->lunchModel->find($id);

        if (!$lunch) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Lunch no encontrado');
        }

        // Proteger: solo el dueño puede actualizar
        if ($lunch['user_id'] != $this->user->id()) {
            return redirect()->back()->with('error', 'Permiso/Acceso denegado');
        }


        $data = $this->request->getPost();

        if (!$this->lunchModel->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->lunchModel->errors());
        }

        // Redireccionar con mensaje de éxito
        return redirect()->to('lunch/' . $id)->with('success', 'changes_saved');

    }

    // POST /lunch/delete/{id}
    public function delete($id = null)
    {
        $lunch = $this->lunchModel->find($id);

        if (!$lunch) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Lunch no encontrado');
        }

        // Proteger: solo el dueño puede borrar
        if ($lunch['user_id'] != $this->user->id()) {
            return redirect()->back()->with('error', 'Permiso/Acceso denegado');
        }


        if (!$this->lunchModel->delete($id)) {
            return redirect()->back()->with('error', 'No se pudo eliminar el lunch');
        }

        return redirect()->to('/lunch')->with('success', 'Lunch eliminado correctamente');
    }
}
