<?php

namespace App\Http\Controllers;

use App\Services\PrismaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for user authentication"
 * )
 */
class AuthController extends Controller
{
    private $prisma;

    public function __construct()
    {
        $this->prisma = PrismaService::getInstance()->getPrisma();
    }

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="account", type="object")
     *         )
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        // Check if email exists
        $existingUser = $this->prisma->user->findUnique([
            'where' => [
                'email' => $validated['email']
            ]
        ]);

        if ($existingUser) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        // Create user with account
        $user = $this->prisma->user->create([
            'data' => [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'account' => [
                    'create' => [
                        'number' => 'ACC' . Str::random(8),
                        'balance' => 0
                    ]
                ]
            ],
            'include' => [
                'account' => true
            ]
        ]);

        // Create token
        $token = $this->createToken($user);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'account' => $user->account,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     summary="Login user and create token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="account", type="object")
     *         )
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = $this->prisma->user->findUnique([
            'where' => [
                'email' => $validated['email']
            ],
            'include' => [
                'account' => true
            ]
        ]);

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create token
        $token = $this->createToken($user);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'account' => $user->account,
        ]);
    }

    private function createToken($user): string
    {
        // Since we can't use Laravel's built-in token generation with Prisma,
        // we'll create a simple token for demonstration
        return base64_encode(json_encode([
            'id' => $user->id,
            'email' => $user->email,
            'timestamp' => time()
        ]));
    }
}
