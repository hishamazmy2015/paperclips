<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The opt-out link in every reminder (spec §13): signed, no sign-in needed. */
final class ReminderOptOutController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = User::query()->find((int) $request->query('user', '0'));
        if ($user !== null && $user->reminders_opted_out_at === null) {
            $user->reminders_opted_out_at = now();
            $user->save();
        }

        return view('app.opted-out');
    }
}
