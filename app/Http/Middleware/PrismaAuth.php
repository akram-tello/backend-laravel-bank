<?php

namespace App\Http\Middleware;

use App\Services\PrismaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrismaAuth
{
    private $prisma;

    public function __construct()
    {
        $this->prisma = PrismaService::getInstance()->getPrisma();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $payload = json_decode(base64_decode($token));
            $user = $this->prisma->user->findUnique([
                'where' => [
                    'id' => $payload->id
                ]
            ]);

            if (!$user || $user->email !== $payload->email) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Add user to request
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }
    }
}
