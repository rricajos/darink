<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Controllers\LunchController;
use App\Controllers\FoodController;
use App\Controllers\LightController;
use App\Models\LunchModel;
use Ramsey\Uuid\Uuid;
use App\Services\SeoService;


class AppController extends BaseController
{
    protected $foodController;
    protected $lunchController;
    protected $lightController;

    protected $lunchModel;

    public function view(string $page = 'AppView', array $data = [])
    {

        // Permite sobreescribir dinámicamente los valores SEO
        $seo = $this->seo->set($data['seo'] ?? [])->get();

        return $this->view('AppView', [
            'seo' => [
                'title' => 'Mi página de prueba',
                'description' => 'Descripción para motores de búsqueda'
            ],
            'mensaje' => 'Hola mundo'
        ]);

    }

    public function __construct()
    {
        $this->lunchModel = new LunchModel();
        $this->lunchController = new LunchController();
        $this->foodController = new FoodController();
        $this->lightController = new LightController();
    }

    public function index()
    {
        return view('index');
    }

    public function reindex()
    {
        return redirect()->to('/');
    }


    public function dashboard()
    {
        view('dashboard');
    }


    // GET /lunch/all
    public function all()
    {
        $user_id = $this->user->id();
        if ($user_id == null) {
            return view('index');
        }
        $lunches = $this->lunchModel->where('user_id', $user_id)->findAll();
        view('lunch_index', ['lunches' => $lunches]);

    }

    // GET /lunch/new
    public function new()
    {
        view('lunch_form');
    }

    // POST /lunch
    public function create()
    {
        // Recoger solo los campos necesarios y confiar en nosotros, no en el usuario
        $data = [
            'lunch_uuid' => Uuid::uuid4()->toString(),
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

        return redirect()->to('/lunch/')->with('success', 'Lunch creado correctamente');
    }


    // GET /lunch/{id}
    public function edit($uuid = null)
    {
        $lunch = $this->lunchModel->where('lunch_uuid', $uuid)->first();

        if (!$lunch) {
            return redirect()->back()->with('error', 'Lunch no encontrado');
        }


        // Proteger: solo el dueño puede editar
        if ($lunch['user_id'] != $this->user->id()) {
            return redirect()->back()->with('error', 'Permiso/Acceso denegado');

        }


        $foodModel = new \App\Models\FoodModel();
        $foods = $foodModel
            ->select('foods.*, lights.light_color')
            ->join('lights', 'lights.food_id = foods.food_id', 'left')
            ->where('lunch_id', $lunch['lunch_id'])
            ->findAll();

        view('lunch_edit', [
            'lunch' => $lunch,
            'foods' => $foods
        ]);



    }




    // POST /lunch/update/{id}
    public function update($uuid = null)
    {
        $lunch = $this->lunchModel->where('lunch_uuid', value: $uuid)->first();

        if (!$lunch) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Lunch no encontrado');
        }

        // Proteger: solo el dueño puede actualizar
        if ($lunch['user_id'] != $this->user->id()) {
            return redirect()->back()->with('error', 'Permiso/Acceso denegado');
        }


        $data = $this->request->getPost();

        if (!$this->lunchModel->update($lunch["lunch_id"], $data)) {
            return redirect()->back()->withInput()->with('errors', $this->lunchModel->errors());
        }

        // Redireccionar con mensaje de éxito
        return redirect()->to('lunch/' . $uuid)->with('success', 'changes_saved');

    }

    // POST /lunch/delete/{id}
    public function delete($uuid = null)
    {
        $lunch = $this->lunchModel->where('lunch_uuid', value: $uuid)->first();

        if (!$lunch) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Lunch no encontrado');
        }

        // Proteger: solo el dueño puede borrar
        if ($lunch['user_id'] != $this->user->id()) {
            return redirect()->back()->with('error', 'Permiso/Acceso denegado');
        }


        if (!$this->lunchModel->delete($lunch['lunch_id'])) {
            return redirect()->back()->with('error', 'No se pudo eliminar el lunch');
        }

        return redirect()->to('/lunch')->with('success', 'Lunch eliminado correctamente');
    }
}
