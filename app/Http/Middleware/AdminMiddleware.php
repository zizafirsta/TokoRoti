<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * RQ-004: tolak akses halaman admin bagi pengunjung tanpa login (diarahkan ke login)
     * dan bagi akun customer (403 Forbidden).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (auth()->user()->role !== 'admin') {
            abort(403, 'Akses ditolak! Anda bukan Admin.');
        }

        return $next($request);
    }
}
