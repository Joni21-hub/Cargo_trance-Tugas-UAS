<?php

namespace App\Http\Controllers\Api;

use App\Models\EscrowTransaction;
use App\Models\Shipment;
use App\Models\WalletMutation;
use App\Services\EscrowService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EscrowWalletController extends BaseApiController
{
    public function __construct(
        protected EscrowService $escrowService
    ) {}

    /**
     * GET /api/v1/wallet
     * Return authenticated user's virtual wallet balance, mutations, and relevant escrow contracts.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $mutations = WalletMutation::with('shipment:id,tracking_number')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(30)
            ->get();

        $escrowQuery = EscrowTransaction::with('shipment:id,tracking_number,status,cargo_description');
        if ($user->role === 'customer') {
            $escrowQuery->where(function ($q) use ($user) {
                $q->where('shipper_id', $user->id)->orWhere('buyer_id', $user->id);
            });
        }

        return $this->sendResponse([
            'balance_usd' => (float) $user->wallet_balance_usd,
            'mutations' => $mutations,
            'escrows' => $escrowQuery->latest()->limit(30)->get(),
        ], 'Data dompet dan escrow berhasil dimuat.');
    }

    /**
     * POST /api/v1/wallet/topup
     */
    public function topUp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount_usd' => ['required', 'numeric', 'min:10', 'max:5000000'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        try {
            $mutation = $this->escrowService->topUpWallet(
                $request->user(),
                (float) $validator->validated()['amount_usd']
            );

            return $this->sendResponse([
                'mutation' => $mutation,
                'balance_usd' => (float) $request->user()->fresh()->wallet_balance_usd,
            ], 'Top Up saldo simulasi berhasil.', 201);
        } catch (DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        }
    }

    /**
     * POST /api/v1/wallet/withdraw
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount_usd' => ['required', 'numeric', 'min:10', 'max:5000000'],
            'bank_destination' => ['required', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();

        try {
            $mutation = $this->escrowService->withdrawWallet(
                $request->user(),
                (float) $data['amount_usd'],
                $data['bank_destination']
            );

            return $this->sendResponse([
                'mutation' => $mutation,
                'balance_usd' => (float) $request->user()->fresh()->wallet_balance_usd,
            ], 'Penarikan dana simulasi berhasil dicatat.', 201);
        } catch (DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        }
    }

    /**
     * POST /api/v1/shipments/{id}/escrow/deposit
     * Buyer deposits funds into Escrow for their shipment.
     */
    public function deposit(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)->orWhere('tracking_number', $id)->first();
        if (!$shipment) {
            return $this->sendError('Pengiriman tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if ($user->role === 'customer' && (int) $shipment->buyer_id !== (int) $user->id) {
            return $this->sendError('Forbidden: Hanya Buyer penerima pengiriman ini yang dapat menyetor dana Escrow.', null, 403);
        }

        try {
            $escrow = $this->escrowService->deposit($shipment, $user);

            return $this->sendResponse([
                'escrow' => $escrow,
                'balance_usd' => (float) $user->fresh()->wallet_balance_usd,
            ], 'Dana berhasil ditahan aman di Escrow Meridian.');
        } catch (DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        }
    }

    /**
     * POST /api/v1/shipments/{id}/escrow/dispute
     */
    public function dispute(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)->orWhere('tracking_number', $id)->first();
        if (!$shipment) {
            return $this->sendError('Pengiriman tidak ditemukan.', null, 404);
        }

        $user = $request->user();
        if ($user->role === 'customer' && (int) $shipment->buyer_id !== (int) $user->id) {
            return $this->sendError('Forbidden: Hanya Buyer pemilik pesanan yang dapat mengajukan sengketa.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        try {
            $escrow = $this->escrowService->dispute($shipment, $user, $validator->validated()['reason']);
            return $this->sendResponse($escrow, 'Sengketa Escrow dicatat.');
        } catch (DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        }
    }
}
