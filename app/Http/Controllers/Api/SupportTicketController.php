<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupportTicketController extends BaseApiController
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * GET /api/v1/support-tickets
     * Admin sees all incoming tickets; regular users see their own tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SupportTicket::with([
            'user:id,name,email,role,phone,company_name,is_verified',
            'replier:id,name,email',
        ]);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $this->sendResponse($query->latest()->limit(80)->get(), 'Daftar tiket bantuan admin dimuat');
    }

    /**
     * POST /api/v1/support-tickets
     * Available to both authenticated users (Shipper/Buyer/Operator) and public visitors ("Bantuan Admin").
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'guest_name' => [$user ? 'nullable' : 'required', 'string', 'max:120'],
            'guest_contact' => [$user ? 'nullable' : 'required', 'string', 'max:120'],
            'category' => ['nullable', 'in:AKUN_VERIFIKASI,PENGIRIMAN,REKENING_JAMINAN,SENGKETA,UMUM'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi laporan bantuan gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $ticket = SupportTicket::create([
            'ticket_code' => 'HLP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'user_id' => $user?->id,
            'guest_name' => $user ? $user->name : ($data['guest_name'] ?? 'Pengunjung Publik'),
            'guest_contact' => $user ? ($user->phone ?: $user->email) : ($data['guest_contact'] ?? '-'),
            'user_role' => $user ? $user->role : 'public',
            'category' => $data['category'] ?? 'UMUM',
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'status' => 'OPEN',
        ]);

        // Notify all Administrators
        foreach (User::where('role', 'admin')->get() as $admin) {
            $this->notificationService->notify(
                $admin->id,
                null,
                'ADMIN_SUPPORT',
                "Bantuan Admin Baru ({$ticket->ticket_code})",
                "{$ticket->guest_name}: {$ticket->subject}"
            );
        }

        return $this->sendResponse(
            $ticket->load('user:id,name,email,role,phone,company_name'),
            'Pesan bantuan berhasil dikirim ke Administrator. Admin akan segera membalas laporan Anda.',
            201
        );
    }

    /**
     * POST /api/v1/admin/support-tickets/{id}/reply
     * Administrator replies to and updates ticket status.
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);
        if (!$ticket) {
            return $this->sendError('Tiket bantuan tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'admin_reply' => ['required', 'string', 'max:2000'],
            'status' => ['nullable', 'in:OPEN,IN_PROGRESS,RESOLVED'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi balasan gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $admin = $request->user();

        $ticket->update([
            'admin_reply' => trim($data['admin_reply']),
            'status' => $data['status'] ?? 'RESOLVED',
            'replied_by' => $admin->id,
            'replied_at' => now(),
        ]);

        if ($ticket->user_id) {
            $this->notificationService->notify(
                $ticket->user_id,
                null,
                'ADMIN_SUPPORT_REPLY',
                "Balasan Admin ({$ticket->ticket_code})",
                mb_strimwidth($ticket->admin_reply, 0, 140, '...')
            );
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'ADMIN_REPLY_SUPPORT',
            'model_type' => SupportTicket::class,
            'model_id' => $ticket->id,
            'old_values' => null,
            'new_values' => ['ticket_code' => $ticket->ticket_code, 'status' => $ticket->status],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $ticket->fresh(['user:id,name,email,role,phone,company_name', 'replier:id,name,email']),
            'Balasan Admin berhasil dikirim ke pengguna.'
        );
    }
}
