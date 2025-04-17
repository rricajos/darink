<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\LunchModel;

class LunchController extends BaseController
{
    protected $lunchModel;

    public function __construct()
    {
        $this->lunchModel = new LunchModel();
    }

    /**
     * CREATE - mostrar formulario / procesar creación
     */
    public function create()
    {

        // Procesar formulario
        if ($this->request->getMethod() === 'POST') {
            $data = [
                'lunch_tag'       => $this->request->getPost('lunch_tag') ?? 'lunch',
                'lunch_location'  => $this->request->getPost('lunch_location') ?? 'house',
                'lunch_start_at'  => $this->request->getPost('lunch_start_at') ?? date('Y-m-d H:i:s'),
                'lunch_end_at'    => $this->request->getPost('lunch_end_at') ?? date('Y-m-d H:i:s'),
                'user_id'         => $this->user->id()
            ];
    
            $this->lunchModel->insert($data);
    
            // Guardar valores en sesión para próximos usos
            session()->set([
                'last_lunch_tag'      => $data['lunch_tag'],
                'last_lunch_location' => $data['lunch_location'],
                'last_lunch_start_at' => $data['lunch_start_at'],
                'last_lunch_end_at'   => $data['lunch_end_at'],
            ]);
    
            return redirect()->to('/lunch')->with('message', 'Almuerzo registrado con éxito');
        }
    
        // Valores por defecto desde sesión o actuales
        $lastTag      = session()->get('last_lunch_tag') ?? 'lunch';
        $lastLocation = session()->get('last_lunch_location') ?? 'house';
        $lastStart    = session()->get('last_lunch_start_at') ?? date('Y-m-d H:i');
        $lastEnd      = session()->get('last_lunch_end_at') ?? date('Y-m-d H:i');
    
        return view('lunch/create', [
            'default_tag'      => $lastTag,
            'default_location' => $lastLocation,
            'default_start'    => date('Y-m-d\TH:i', strtotime($lastStart)),
            'default_end'      => date('Y-m-d\TH:i', strtotime($lastEnd)),
        ]);
    }
    


    /**
     * READ - leer uno por ID
     */
    public function read($id)
    {
        $lunch = $this->lunchModel->find($id);
        session()->set('last_lunch_id', $id);

        if (!$lunch) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound("Lunch $id no encontrado.");
        }

        return view('lunch/read', ['lunch' => $lunch]);
    }

    /**
     * READ - listado por defecto
     */
    public function readList()
    {
        $lunches = $this->lunchModel->orderBy('lunch_start_at', 'DESC')->findAll();
        return view('lunch/read_list', ['lunches' => $lunches]);
    }

    /**
     * READ - vista calendario
     */
    public function readCalendar()
    {
        $lunches = $this->lunchModel->findAll();
        return view('lunch/read_calendar', ['lunches' => $lunches]);
    }

    /**
     * READ - vista agenda
     */
    public function readAgenda()
    {
        $lunches = $this->lunchModel->orderBy('lunch_start_at')->findAll();
        return view('lunch/read_agenda', ['lunches' => $lunches]);
    }

    /**
     * UPDATE - mostrar formulario / procesar edición
     */
    public function update($id)
    {
        $lunch = $this->lunchModel->find($id);
        if (!$lunch) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound("Lunch $id no encontrado.");
        }

        if ($this->request->getMethod() === 'post') {
            $data = $this->request->getPost();

            $this->lunchModel->update($id, [
                'lunch_location' => $data['lunch_location'],
                'lunch_start_at' => $data['lunch_start_at'],
                'lunch_end_at' => $data['lunch_end_at'],
                'lunch_tag' => $data['lunch_tag']
            ]);

            return redirect()->to("/user/lunch/read/$id")->with('message', 'Almuerzo actualizado');
        }

        return view('lunch/update', ['lunch' => $lunch]);
    }

    /**
     * DELETE - mostrar confirmación / procesar eliminación
     */
    public function delete($id)
    {
        $lunch = $this->lunchModel->find($id);
        if (!$lunch) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound("Lunch $id no encontrado.");
        }

        if ($this->request->getMethod() === 'post') {
            $this->lunchModel->delete($id);
            return redirect()->to('/user/lunch/read')->with('message', 'Almuerzo eliminado');
        }

        return view('lunch/delete', ['lunch' => $lunch]);
    }
}
