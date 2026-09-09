<?php

namespace App\Modules\Dashboard\Controllers;

use App\Core\Response;
use App\Core\View;
use App\Modules\Dashboard\Api\DashboardApi;

final class DashboardController
{
    public static function index(): string
    {
        Response::reset();

        $payload = DashboardApi::index();

        return View::layout('layouts/admin', 'Dashboard::index', [
            'title'    => 'Dashboard',
            'data'     => $payload,
            'messages' => $payload['m'] ?? [],
        ]);
    }
}
