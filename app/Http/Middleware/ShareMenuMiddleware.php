<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use App\Models\General\Menu;

class ShareMenuMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Ambil data menu
        $menus = Menu::whereNull('parent_id')->with('children')->orderBy('order')->get();

        // Bagikan data menu ke semua views
        View::share('menus', $menus);

        return $next($request);
    }
}
