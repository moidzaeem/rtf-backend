<?php

namespace App\Http\Controllers\API;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends BaseController
{
    public function withdraw(Request $request)
    {
        // Validate the request
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $userId = Auth::id();
        $wallet = Wallet::where('user_id', $userId)->first();

        if (!$wallet) {
            return $this->sendError('Error', ['Wallet not found.'], 404);
        }

        if ($wallet->balance < $request->amount) {
            return $this->sendError('Error', ['Insufficent Balance'], 404);
        }

        // Deduct the amount from the wallet
        $wallet->balance -= $request->amount;
        $wallet->save();

        // Create a transaction record
        Transaction::create([
            'user_id' => $userId,
            'amount' => $request->amount,
            'type' => 'withdrawal',
        ]);

        return $this->sendResponse($wallet, 'Withdrawal Successful.');

    }
}
