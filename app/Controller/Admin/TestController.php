<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;

class TestController extends AbstractController
{
    public function index()
    {
        return $this->render->render('admin.test.index', [
            'adminMenuEnabled' => true,
        ]);
    }
}

