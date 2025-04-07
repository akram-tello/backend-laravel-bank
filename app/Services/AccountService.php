<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Str;

class AccountService
{
    public function createAccount(User $user): Account
    {
        $accountNumber = $this->generateAccountNumber();

        return Account::create([
            'user_id' => $user->id,
            'number' => $accountNumber,
            'balance' => 0,
        ]);
    }

    private function generateAccountNumber(): string
    {
        do {
            $number = 'ACC' . Str::random(8);
        } while (Account::where('number', $number)->exists());

        return $number;
    }
}
