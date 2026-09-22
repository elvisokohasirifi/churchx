<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AskDataRequest;
use App\Models\User;
use App\Services\AskDataService;
use Illuminate\View\View;

class AskDataController extends Controller
{
    public function index(AskDataService $askData): View
    {
        $user = backpack_user();
        abort_unless($user instanceof User && $askData->canUse($user), 403);

        return view('admin.ask-data', [
            'capabilities' => $askData->capabilities($user),
            'question' => null,
            'from' => null,
            'to' => null,
            'result' => null,
        ]);
    }

    public function answer(AskDataRequest $request, AskDataService $askData): View
    {
        $data = $request->validated();
        $user = backpack_user();
        abort_unless($user instanceof User, 403);

        return view('admin.ask-data', [
            'capabilities' => $askData->capabilities($user),
            'question' => $data['question'],
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'result' => $askData->analyze($user, $data['question'], $data['from'] ?? null, $data['to'] ?? null),
        ]);
    }
}
