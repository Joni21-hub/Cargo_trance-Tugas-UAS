<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EscrowTransaction;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Models\WalletMutation;
use DomainException;
use Illuminate\Support\Facades\DB;

class EscrowService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Initialize an escrow contract record when a shipment is registered with contract value.
     */
    public function initializeForShipment(Shipment $shipment, float $amountUsd): ?EscrowTransaction
    {
        if ($amountUsd <= 0) {
            return null;
        }

        return EscrowTransaction::firstOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'shipper_id' => $shipment->customer_id,
                'buyer_id' => $shipment->buyer_id,
                'amount_usd' => $amountUsd,
                'status' => 'PENDING_DEPOSIT',
                'notes' => 'Menunggu setoran dana simulasi oleh Buyer ke rekening Escrow Meridian.',
            ]
        );
    }

    /**
     * Buyer deposits simulated wallet funds into Escrow for a shipment.
     *
     * @throws DomainException
     */
    public function deposit(Shipment $shipment, User $buyer): EscrowTransaction
    {
        return DB::transaction(function () use ($shipment, $buyer) {
            /** @var EscrowTransaction|null $escrow */
            $escrow = EscrowTransaction::where('shipment_id', $shipment->id)->lockForUpdate()->first();

            if (!$escrow) {
                throw new DomainException('Pengiriman ini tidak memiliki kontrak Escrow aktif.');
            }

            if ($escrow->status !== 'PENDING_DEPOSIT') {
                throw new DomainException("Dana Escrow sudah berada pada status '{$escrow->status}'.");
            }

            $freshBuyer = User::where('id', $buyer->id)->lockForUpdate()->firstOrFail();
            if ($freshBuyer->wallet_balance_usd < $escrow->amount_usd) {
                throw new DomainException(
                    'Saldo dompet simulasi tidak mencukupi ($' . number_format($freshBuyer->wallet_balance_usd, 2) .
                    ' tersedia, dibutuhkan $' . number_format($escrow->amount_usd, 2) . '). Silakan lakukan Top Up terlebih dahulu.'
                );
            }

            $freshBuyer->wallet_balance_usd = round($freshBuyer->wallet_balance_usd - $escrow->amount_usd, 2);
            $freshBuyer->save();

            WalletMutation::create([
                'user_id' => $freshBuyer->id,
                'shipment_id' => $shipment->id,
                'type' => 'ESCROW_DEPOSIT',
                'amount_usd' => -$escrow->amount_usd,
                'balance_after_usd' => $freshBuyer->wallet_balance_usd,
                'reference_code' => 'ESC-DEP-' . strtoupper(substr(uniqid(), -6)),
                'description' => "Setoran Escrow untuk {$shipment->tracking_number}",
            ]);

            $escrow->update([
                'buyer_id' => $freshBuyer->id,
                'status' => 'HELD_IN_ESCROW',
                'deposited_at' => now(),
                'notes' => 'Dana ditahan aman di rekening bersama Escrow Meridian hingga barang diterima Buyer.',
            ]);

            AuditLog::create([
                'user_id' => $freshBuyer->id,
                'action' => 'ESCROW_DEPOSIT',
                'model_type' => EscrowTransaction::class,
                'model_id' => $escrow->id,
                'old_values' => ['status' => 'PENDING_DEPOSIT'],
                'new_values' => ['status' => 'HELD_IN_ESCROW', 'amount_usd' => $escrow->amount_usd],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            $this->notificationService->notify(
                $escrow->shipper_id,
                $shipment->id,
                'ESCROW_UPDATE',
                "Dana Escrow Ditahan ({$shipment->tracking_number})",
                'Buyer telah menyetor $' . number_format($escrow->amount_usd, 2) . ' USD ke Escrow. Pengiriman aman untuk dikonfirmasi.'
            );

            return $escrow->fresh();
        });
    }

    /**
     * Freeze escrow payout if an active severe exception is logged.
     */
    public function freezeOnException(Shipment $shipment, ShipmentException $exception): void
    {
        $escrow = EscrowTransaction::where('shipment_id', $shipment->id)->first();
        if (!$escrow || $escrow->status !== 'HELD_IN_ESCROW') {
            return;
        }

        if (in_array($exception->exception_type, ['CUSTOMS_HOLD', 'DAMAGED', 'MISROUTED'], true) || $exception->severity === 'HIGH' || $exception->severity === 'CRITICAL') {
            $escrow->update([
                'status' => 'FROZEN_ON_EXCEPTION',
                'notes' => "Pencairan ditahan otomatis karena kendala {$exception->exception_type}.",
            ]);

            $this->notificationService->notifyParties(
                $shipment,
                'ESCROW_UPDATE',
                "Escrow Dibekukan ({$shipment->tracking_number})",
                "Pencairan Escrow dibekukan sementara karena {$exception->exception_type} hingga kendala diselesaikan."
            );
        }
    }

    /**
     * Unfreeze escrow back to HELD_IN_ESCROW once all exceptions are resolved.
     */
    public function unfreezeIfClear(Shipment $shipment): void
    {
        $escrow = EscrowTransaction::where('shipment_id', $shipment->id)->first();
        if (!$escrow || $escrow->status !== 'FROZEN_ON_EXCEPTION') {
            return;
        }

        if ($shipment->activeExceptions()->count() === 0) {
            $escrow->update([
                'status' => 'HELD_IN_ESCROW',
                'notes' => 'Seluruh kendala telah terselesaikan. Escrow kembali aktif menunggu konfirmasi tiba Buyer.',
            ]);
        }
    }

    /**
     * Release escrow funds to Shipper's virtual wallet upon confirmed DELIVERED receipt.
     *
     * @throws DomainException
     */
    public function releaseToShipper(Shipment $shipment, ?int $actorId = null): ?EscrowTransaction
    {
        return DB::transaction(function () use ($shipment, $actorId) {
            /** @var EscrowTransaction|null $escrow */
            $escrow = EscrowTransaction::where('shipment_id', $shipment->id)->lockForUpdate()->first();
            if (!$escrow) {
                return null;
            }

            if ($escrow->status === 'RELEASED') {
                return $escrow;
            }

            if ($shipment->status !== 'DELIVERED') {
                throw new DomainException('Dana Escrow hanya dapat dicairkan setelah status pengiriman DELIVERED.');
            }

            if ($shipment->activeExceptions()->count() > 0) {
                throw new DomainException('Dana Escrow masih dibekukan karena masih ada kendala (exception) yang belum selesai.');
            }

            if ($escrow->status === 'PENDING_DEPOSIT') {
                // No simulated deposit was made yet; skip wallet payout cleanly
                return $escrow;
            }

            if (!in_array($escrow->status, ['HELD_IN_ESCROW', 'FROZEN_ON_EXCEPTION'], true)) {
                throw new DomainException("Dana Escrow berstatus '{$escrow->status}' dan tidak dapat dicairkan.");
            }

            $shipper = User::where('id', $escrow->shipper_id)->lockForUpdate()->firstOrFail();
            $shipper->wallet_balance_usd = round($shipper->wallet_balance_usd + $escrow->amount_usd, 2);
            $shipper->save();

            WalletMutation::create([
                'user_id' => $shipper->id,
                'shipment_id' => $shipment->id,
                'type' => 'ESCROW_RELEASE',
                'amount_usd' => $escrow->amount_usd,
                'balance_after_usd' => $shipper->wallet_balance_usd,
                'reference_code' => 'ESC-REL-' . strtoupper(substr(uniqid(), -6)),
                'description' => "Pencairan Escrow dari pengiriman {$shipment->tracking_number}",
            ]);

            $oldStatus = $escrow->status;
            $escrow->update([
                'status' => 'RELEASED',
                'released_at' => now(),
                'notes' => 'Dana Escrow 100% resmi dicairkan ke saldo dompet Shipper setelah konfirmasi Buyer.',
            ]);

            AuditLog::create([
                'user_id' => $actorId ?? $escrow->buyer_id,
                'action' => 'ESCROW_RELEASED',
                'model_type' => EscrowTransaction::class,
                'model_id' => $escrow->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'RELEASED', 'amount_usd' => $escrow->amount_usd],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            $this->notificationService->notify(
                $shipper->id,
                $shipment->id,
                'ESCROW_UPDATE',
                "Dana Escrow Cair ({$shipment->tracking_number})",
                '$' . number_format($escrow->amount_usd, 2) . ' USD telah masuk ke saldo dompet Anda setelah konfirmasi penerimaan Buyer.'
            );

            return $escrow->fresh();
        });
    }

    /**
     * Buyer opens a dispute on an active held escrow.
     *
     * @throws DomainException
     */
    public function dispute(Shipment $shipment, User $buyer, string $reason): EscrowTransaction
    {
        return DB::transaction(function () use ($shipment, $buyer, $reason) {
            $escrow = EscrowTransaction::where('shipment_id', $shipment->id)->lockForUpdate()->first();
            if (!$escrow) {
                throw new DomainException('Pengiriman ini tidak memiliki transaksi Escrow.');
            }

            if (!in_array($escrow->status, ['HELD_IN_ESCROW', 'FROZEN_ON_EXCEPTION'], true)) {
                throw new DomainException("Sengketa tidak dapat diajukan saat status Escrow '{$escrow->status}'.");
            }

            $oldStatus = $escrow->status;
            $escrow->update([
                'status' => 'DISPUTED',
                'notes' => 'Sengketa diajukan Buyer: ' . $reason,
            ]);

            AuditLog::create([
                'user_id' => $buyer->id,
                'action' => 'ESCROW_DISPUTED',
                'model_type' => EscrowTransaction::class,
                'model_id' => $escrow->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'DISPUTED', 'reason' => $reason],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            $this->notificationService->notify(
                $escrow->shipper_id,
                $shipment->id,
                'ESCROW_UPDATE',
                "Sengketa Escrow ({$shipment->tracking_number})",
                "Buyer mengajukan sengketa: {$reason}"
            );

            return $escrow->fresh();
        });
    }

    /**
     * Simulated Top Up into virtual wallet.
     */
    public function topUpWallet(User $user, float $amountUsd): WalletMutation
    {
        return DB::transaction(function () use ($user, $amountUsd) {
            if ($amountUsd <= 0) {
                throw new DomainException('Nominal Top Up harus lebih dari 0.');
            }

            $fresh = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
            $fresh->wallet_balance_usd = round($fresh->wallet_balance_usd + $amountUsd, 2);
            $fresh->save();

            $mutation = WalletMutation::create([
                'user_id' => $fresh->id,
                'type' => 'TOPUP',
                'amount_usd' => $amountUsd,
                'balance_after_usd' => $fresh->wallet_balance_usd,
                'reference_code' => 'TOP-' . strtoupper(substr(uniqid(), -6)),
                'description' => 'Top Up Saldo Simulasi Dompet Meridian',
            ]);

            AuditLog::create([
                'user_id' => $fresh->id,
                'action' => 'WALLET_TOPUP',
                'model_type' => WalletMutation::class,
                'model_id' => $mutation->id,
                'old_values' => null,
                'new_values' => ['amount_usd' => $amountUsd, 'balance_after_usd' => $fresh->wallet_balance_usd],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $mutation;
        });
    }

    /**
     * Simulated withdrawal from virtual wallet to destination bank account.
     *
     * @throws DomainException
     */
    public function withdrawWallet(User $user, float $amountUsd, string $bankDestination): WalletMutation
    {
        return DB::transaction(function () use ($user, $amountUsd, $bankDestination) {
            if ($amountUsd <= 0) {
                throw new DomainException('Nominal penarikan harus lebih dari 0.');
            }

            $fresh = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
            if ($fresh->wallet_balance_usd < $amountUsd) {
                throw new DomainException('Saldo dompet tidak mencukupi untuk penarikan.');
            }

            $fresh->wallet_balance_usd = round($fresh->wallet_balance_usd - $amountUsd, 2);
            $fresh->save();

            $mutation = WalletMutation::create([
                'user_id' => $fresh->id,
                'type' => 'WITHDRAWAL',
                'amount_usd' => -$amountUsd,
                'balance_after_usd' => $fresh->wallet_balance_usd,
                'reference_code' => 'WDR-' . strtoupper(substr(uniqid(), -6)),
                'description' => "Penarikan dana simulasi ke {$bankDestination}",
            ]);

            AuditLog::create([
                'user_id' => $fresh->id,
                'action' => 'WALLET_WITHDRAWAL',
                'model_type' => WalletMutation::class,
                'model_id' => $mutation->id,
                'old_values' => null,
                'new_values' => ['amount_usd' => $amountUsd, 'bank_destination' => $bankDestination],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $mutation;
        });
    }
}
