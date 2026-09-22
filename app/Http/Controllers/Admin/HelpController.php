<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HelpCenterService;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function __invoke(HelpCenterService $helpCenter): View
    {
        $user = backpack_user();

        abort_unless($user instanceof User, 403);

        $sections = $helpCenter->sections($user);

        return view('admin.help', [
            'sections' => $sections,
            'roleNames' => $helpCenter->activeRoleNames(),
            'articleCount' => collect($sections)->sum(fn (array $section): int => count($section['articles'])),
        ]);
    }
}
