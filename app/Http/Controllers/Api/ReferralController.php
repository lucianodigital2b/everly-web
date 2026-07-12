<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReferralController extends Controller
{
    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        // Lock the row so the same code can't be redeemed twice by concurrent
        // requests — the check and the claim have to be atomic.
        $reward = DB::transaction(function () use ($data, $request): array {
            $referral = Referral::whereRaw('LOWER(code) = ?', [mb_strtolower($data['code'])])
                ->lockForUpdate()
                ->first();

            if (! $referral) {
                throw ValidationException::withMessages([
                    'code' => ['That referral code is not valid.'],
                ]);
            }

            if ($referral->isRedeemed()) {
                throw ValidationException::withMessages([
                    'code' => ['That referral code has already been used.'],
                ]);
            }

            $referral->user_id = $request->user()->id;
            $referral->redeemed_at = Date::now();
            $referral->save();

            return $referral->reward ?? [];
        });

        return response()->json([
            'success' => true,
            'reward' => $reward,
        ]);
    }
}
