<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends BaseApiController
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * GET /api/v1/chats/threads
     * List conversation peers & recent messages for the authenticated user (Buyer <-> Shipper <-> Operator).
     */
    public function threads(Request $request): JsonResponse
    {
        $me = $request->user();

        $messages = ChatMessage::with([
            'sender:id,name,email,role,company_name,phone,city,is_verified',
            'recipient:id,name,email,role,company_name,phone,city,is_verified',
            'shipment:id,tracking_number,cargo_description',
            'exportProduct:id,name,island,region',
        ])
        ->where(function ($q) use ($me) {
            $q->where('sender_id', $me->id)->orWhere('recipient_id', $me->id);
        })
        ->latest()
        ->limit(250)
        ->get();

        $threads = [];
        foreach ($messages as $m) {
            $peer = ((int) $m->sender_id === (int) $me->id) ? $m->recipient : $m->sender;
            if (!$peer) {
                continue;
            }
            $key = 'peer_' . $peer->id;
            if (!isset($threads[$key])) {
                $threads[$key] = [
                    'peer' => $peer,
                    'last_message' => $m->message,
                    'last_at' => $m->created_at,
                    'shipment' => $m->shipment,
                    'export_product' => $m->exportProduct,
                    'unread_count' => 0,
                ];
            }
            if ((int) $m->recipient_id === (int) $me->id && !$m->is_read) {
                $threads[$key]['unread_count']++;
            }
        }

        // Also include key contacts so user can initiate chat easily
        $contacts = User::where('id', '!=', $me->id)
            ->whereIn('role', ['customer', 'operator', 'admin'])
            ->select('id', 'name', 'email', 'role', 'company_name', 'phone', 'city', 'country', 'is_verified', 'location_id')
            ->with('branchLocation:id,code,name,island')
            ->orderBy('role')
            ->orderBy('id')
            ->get();

        return $this->sendResponse([
            'threads' => array_values($threads),
            'contacts' => $contacts,
        ], 'Daftar percakapan berhasil dimuat');
    }

    /**
     * GET /api/v1/chats/messages
     * Fetch messages between authenticated user and peer_id (or scoped to shipment_id / pickup_request_id).
     */
    public function messages(Request $request): JsonResponse
    {
        $me = $request->user();

        $query = ChatMessage::with([
            'sender:id,name,email,role,company_name,is_verified',
            'recipient:id,name,email,role,company_name,is_verified',
            'exportProduct:id,name,island,region',
            'shipment:id,tracking_number',
        ]);

        if ($request->filled('shipment_id')) {
            $query->where('shipment_id', (int) $request->query('shipment_id'));
        } elseif ($request->filled('pickup_request_id')) {
            $query->where('pickup_request_id', (int) $request->query('pickup_request_id'));
        } elseif ($request->filled('peer_id')) {
            $peerId = (int) $request->query('peer_id');
            $query->where(function ($q) use ($me, $peerId) {
                $q->where(fn ($sub) => $sub->where('sender_id', $me->id)->where('recipient_id', $peerId))
                  ->orWhere(fn ($sub) => $sub->where('sender_id', $peerId)->where('recipient_id', $me->id));
            });

            // Mark incoming messages as read
            ChatMessage::where('sender_id', $peerId)
                ->where('recipient_id', $me->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        } else {
            $query->where(function ($q) use ($me) {
                $q->where('sender_id', $me->id)->orWhere('recipient_id', $me->id);
            });
        }

        $msgs = $query->orderBy('created_at', 'asc')->limit(150)->get();

        return $this->sendResponse($msgs, 'Riwayat pesan berhasil dimuat');
    }

    /**
     * POST /api/v1/chats/messages
     * Send a direct or shipment-linked chat message.
     */
    public function store(Request $request): JsonResponse
    {
        $me = $request->user();

        $validator = Validator::make($request->all(), [
            'recipient_id' => ['required', 'exists:users,id'],
            'message' => ['required', 'string', 'max:2000'],
            'channel_type' => ['nullable', 'in:DIRECT,SHIPMENT,PICKUP_REQUEST'],
            'shipment_id' => ['nullable', 'exists:shipments,id'],
            'pickup_request_id' => ['nullable', 'exists:pickup_requests,id'],
            'export_product_id' => ['nullable', 'exists:export_products,id'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi pesan gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $chat = ChatMessage::create([
            'channel_type' => $data['channel_type'] ?? (!empty($data['shipment_id']) ? 'SHIPMENT' : 'DIRECT'),
            'shipment_id' => $data['shipment_id'] ?? null,
            'pickup_request_id' => $data['pickup_request_id'] ?? null,
            'export_product_id' => $data['export_product_id'] ?? null,
            'sender_id' => $me->id,
            'recipient_id' => (int) $data['recipient_id'],
            'message' => trim($data['message']),
            'is_read' => false,
        ]);

        if ((int) $data['recipient_id'] !== (int) $me->id) {
            $this->notificationService->notify(
                (int) $data['recipient_id'],
                $chat->shipment_id,
                'CHAT_MESSAGE',
                "Pesan Baru dari {$me->name}",
                mb_strimwidth($chat->message, 0, 120, '...')
            );
        }

        return $this->sendResponse(
            $chat->load(['sender:id,name,email,role,company_name,is_verified', 'recipient:id,name,email,role,company_name,is_verified']),
            'Pesan terkirim',
            201
        );
    }
}
