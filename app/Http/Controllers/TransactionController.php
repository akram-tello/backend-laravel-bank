<?php

namespace App\Http\Controllers;

use App\Services\PrismaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Transactions",
 *     description="API Endpoints for managing transactions"
 * )
 */
class TransactionController extends Controller
{
    private $prisma;

    public function __construct()
    {
        $this->prisma = PrismaService::getInstance()->getPrisma();
    }

    /**
     * @OA\Post(
     *     path="/api/transactions/deposit",
     *     summary="Deposit money into account",
     *     tags={"Transactions"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deposit successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="transaction", type="object"),
     *             @OA\Property(property="balance", type="number", format="float")
     *         )
     *     )
     * )
     */
    public function deposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = $this->prisma->user->findUnique([
            'where' => [
                'id' => $request->user()->id
            ],
            'include' => [
                'account' => true
            ]
        ]);

        if (!$user->account) {
            throw ValidationException::withMessages([
                'account' => ['Account not found.'],
            ]);
        }

        // Create transaction and update balance
        $transaction = $this->prisma->transaction->create([
            'data' => [
                'amount' => $validated['amount'],
                'type' => 'DEPOSIT',
                'fromAccount' => [
                    'connect' => ['id' => $user->account->id]
                ],
                'toAccount' => [
                    'connect' => ['id' => $user->account->id]
                ]
            ]
        ]);

        $account = $this->prisma->account->update([
            'where' => [
                'id' => $user->account->id
            ],
            'data' => [
                'balance' => $user->account->balance + $validated['amount']
            ]
        ]);

        return response()->json([
            'transaction' => $transaction,
            'balance' => $account->balance,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/transactions/transfer",
     *     summary="Transfer money between accounts",
     *     tags={"Transactions"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount","to_account_number"},
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="to_account_number", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="transaction", type="object"),
     *             @OA\Property(property="balance", type="number", format="float")
     *         )
     *     )
     * )
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'to_account_number' => 'required|string',
        ]);

        $user = $this->prisma->user->findUnique([
            'where' => [
                'id' => $request->user()->id
            ],
            'include' => [
                'account' => true
            ]
        ]);

        if (!$user->account) {
            throw ValidationException::withMessages([
                'account' => ['Your account not found.'],
            ]);
        }

        $toAccount = $this->prisma->account->findUnique([
            'where' => [
                'number' => $validated['to_account_number']
            ]
        ]);

        if (!$toAccount) {
            throw ValidationException::withMessages([
                'to_account_number' => ['Recipient account not found.'],
            ]);
        }

        if ($user->account->balance < $validated['amount']) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient funds.'],
            ]);
        }

        // Create transaction and update balances
        $transaction = $this->prisma->transaction->create([
            'data' => [
                'amount' => $validated['amount'],
                'type' => 'TRANSFER',
                'fromAccount' => [
                    'connect' => ['id' => $user->account->id]
                ],
                'toAccount' => [
                    'connect' => ['id' => $toAccount->id]
                ]
            ]
        ]);

        // Update sender's balance
        $fromAccount = $this->prisma->account->update([
            'where' => [
                'id' => $user->account->id
            ],
            'data' => [
                'balance' => $user->account->balance - $validated['amount']
            ]
        ]);

        // Update recipient's balance
        $this->prisma->account->update([
            'where' => [
                'id' => $toAccount->id
            ],
            'data' => [
                'balance' => $toAccount->balance + $validated['amount']
            ]
        ]);

        return response()->json([
            'transaction' => $transaction,
            'balance' => $fromAccount->balance,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/transactions/{accountId}",
     *     summary="Get account transactions",
     *     tags={"Transactions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="accountId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of transactions",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     )
     * )
     */
    public function getTransactions(Request $request, string $accountId): JsonResponse
    {
        $account = $this->prisma->account->findUnique([
            'where' => [
                'id' => $accountId
            ],
            'include' => [
                'user' => true
            ]
        ]);

        if (!$account || $account->user->id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transactions = $this->prisma->transaction->findMany([
            'where' => [
                'OR' => [
                    ['fromAccountId' => $accountId],
                    ['toAccountId' => $accountId]
                ]
            ],
            'include' => [
                'fromAccount' => true,
                'toAccount' => true
            ],
            'orderBy' => [
                'createdAt' => 'desc'
            ]
        ]);

        return response()->json($transactions);
    }

    /**
     * @OA\Get(
     *     path="/api/balance/{accountId}",
     *     summary="Get account balance",
     *     tags={"Transactions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="accountId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account balance",
     *         @OA\JsonContent(
     *             @OA\Property(property="balance", type="number", format="float")
     *         )
     *     )
     * )
     */
    public function getBalance(Request $request, string $accountId): JsonResponse
    {
        $account = $this->prisma->account->findUnique([
            'where' => [
                'id' => $accountId
            ],
            'include' => [
                'user' => true
            ]
        ]);

        if (!$account || $account->user->id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'balance' => $account->balance,
        ]);
    }
}
