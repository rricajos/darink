<?php 
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * Summary of AuthCheck
 * 
 * 🔒 AuthCheck filtra acceso a rutas protegidas: ✔️
 * 🧠 También verifica que haya un user_id válido: ✔️
 * 🧼 El controlador puede usar $this->userId de forma limpia: ✔️
 * 💣 Nunca dependés de datos del cliente (input, JS): ✔️
 */
class AuthCheck implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
    
        $userId = $session->get('user_id');
        $isLoggedIn = $session->get('isLoggedIn');
    
        if (!$isLoggedIn || !$userId || !is_numeric($userId)) {
            // Destruir la sesión si está corrupta
            $session->destroy();
    
            return redirect()
                ->to('/auth/signin')
                ->with('message', 'Tu sesión ha expirado o es inválida. Iniciá sesión nuevamente.');
        }
    
        // Si querés: registrar en logs qué usuario está navegando
        // log_message('info', "Usuario $userId accediendo a {$request->uri}");
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada aquí
    }
}
