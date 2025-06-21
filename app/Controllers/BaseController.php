<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Services\SeoService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * @var UserService
     */
    protected $user;

    /**
     * @var SeoService
     */
    protected $seo;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Servicios globales
        $this->user = new UserService();
        $this->seo = new SeoService();
    }

    /**
     * Método de ayuda para configurar SEO desde controladores hijos
     */
    protected function setSEO(array $data = []): array
    {
        return $this->seo->set($data)->get();
    }
}
