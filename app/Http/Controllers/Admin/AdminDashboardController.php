<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    private function sharedData(): array
    {
        return [
            'stores' => Store::active()->get(),
        ];
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', $this->sharedData());
    }

    public function analytics(): View
    {
        return view('admin.analytics.index', $this->sharedData());
    }

    public function queryRules(): View
    {
        return view('admin.query-rules.index', $this->sharedData());
    }

    public function synonyms(): View
    {
        return view('admin.synonyms.index', $this->sharedData());
    }

    public function campaigns(): View
    {
        return view('admin.campaigns.index', $this->sharedData());
    }

    public function preview(): View
    {
        return view('admin.preview.index', $this->sharedData());
    }

    public function ranking(): View
    {
        return view('admin.ranking.index', $this->sharedData());
    }

    public function auditLog(): View
    {
        return view('admin.audit-log.index', $this->sharedData());
    }

    public function stores(): View
    {
        return view('admin.stores.index', $this->sharedData());
    }

    public function storeCategories(\App\Models\Store $store): View
    {
        return view('admin.stores.categories', array_merge($this->sharedData(), ['store' => $store]));
    }

    public function imports(): View
    {
        return view('admin.imports.index', $this->sharedData());
    }

    public function benchmarks(): View
    {
        return view('admin.benchmarks.index', $this->sharedData());
    }

    public function login(): View
    {
        return view('admin.auth.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/admin');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }
}
