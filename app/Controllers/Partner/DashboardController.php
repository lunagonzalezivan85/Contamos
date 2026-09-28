<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\DashboardService;

class DashboardController extends BaseController
{
    public function index()
    {
        $service = new DashboardService();

        return view('partner/dashboard/index', [
            'title' => 'Dashboard — Contamos',
            'chips' => $service->chipsContadores((int) session('tenant_id')),
        ]);
    }
}
