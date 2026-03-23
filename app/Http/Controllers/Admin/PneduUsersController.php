<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PneduUser;
use Illuminate\View\View;

class PneduUsersController extends Controller
{
    public function index(): View
    {
        if (! auth()->user()->hasPermission('users.view')) {
            abort(403, 'Brak uprawnień do przeglądania użytkowników.');
        }

        $users = PneduUser::query()
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.pnedu-users.index', compact('users'));
    }
}
