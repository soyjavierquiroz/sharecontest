<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class AdminBasicAuth { public function handle(Request $request, Closure $next): Response { $user = config('contest.admin_user'); $password = config('contest.admin_password'); if (!$user || !$password || !hash_equals($user, (string) $request->getUser()) || !hash_equals($password, (string) $request->getPassword())) return response('Autenticación requerida.', 401, ['WWW-Authenticate'=>'Basic realm="ShareContest Admin"']); return $next($request); } }
