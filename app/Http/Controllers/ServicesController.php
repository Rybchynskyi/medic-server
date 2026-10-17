<?php

namespace App\Http\Controllers;

use App\Presenters\ServicePresenter;
use App\Repositories\ServiceRepository;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository
    ) {}

    public function index(Request $request)
    {
        $services = $this->serviceRepository->search(
            $request->user()->organization_id,
            $request->get('search')
        );

        return $this->present($services, ServicePresenter::class);
    }
}
