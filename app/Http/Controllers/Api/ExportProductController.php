<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\ExportProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExportProductController extends BaseApiController
{
    /**
     * GET /api/v1/export-products
     * Nusantara Coffee Directory (Non-Marketplace: NO price column; Buyer negotiates directly via Chat).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExportProduct::with([
            'shipper:id,name,email,phone,company_name,address,city,country,identity_number,bio,is_verified',
            'originLocation:id,code,name,island,type,country,latitude,longitude',
        ]);

        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($request->filled('island') && $request->query('island') !== 'ALL') {
            $query->where('island', $request->query('island'));
        }

        if ($request->filled('bean_type') && $request->query('bean_type') !== 'ALL') {
            $query->where('bean_type', $request->query('bean_type'));
        }

        if ($request->filled('shipper_id')) {
            $query->where('shipper_id', (int) $request->query('shipper_id'));
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('region', 'like', "%{$s}%")
                  ->orWhere('island', 'like', "%{$s}%")
                  ->orWhere('tasting_notes', 'like', "%{$s}%");
            });
        }

        $products = $query->orderBy('island')->orderBy('name')->get();

        return $this->sendResponse($products, 'Direktori kopi Nusantara berhasil dimuat');
    }

    /**
     * POST /api/v1/export-products
     * Shipper or Admin adds a coffee commodity entry to the Nusantara Directory (no pricing).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:160'],
            'island' => ['required', 'in:Sumatra,Jawa,Kalimantan,Sulawesi,Papua'],
            'region' => ['required', 'string', 'max:160'],
            'origin_location_id' => ['nullable', 'exists:locations,id'],
            'bean_type' => ['required', 'in:Arabika,Robusta,Liberika,Campuran'],
            'process_method' => ['nullable', 'string', 'max:100'],
            'altitude_mdpl' => ['nullable', 'string', 'max:80'],
            'tasting_notes' => ['nullable', 'string', 'max:255'],
            'supply_capacity' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi produk gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['shipper_id'] = $user->role === 'admin' && $request->filled('shipper_id')
            ? (int) $request->input('shipper_id')
            : $user->id;
        $data['is_active'] = true;

        $product = ExportProduct::create($data);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'CREATE_EXPORT_PRODUCT',
            'model_type' => ExportProduct::class,
            'model_id' => $product->id,
            'old_values' => null,
            'new_values' => ['name' => $product->name, 'island' => $product->island],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $product->load(['shipper:id,name,email,phone,company_name,city,country,is_verified', 'originLocation:id,code,name,island,type']),
            'Komoditas kopi berhasil ditambahkan ke Direktori Nusantara',
            201
        );
    }

    /**
     * PUT/PATCH /api/v1/export-products/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = ExportProduct::find($id);
        if (!$product) {
            return $this->sendError('Produk kopi tidak ditemukan', null, 404);
        }

        $user = $request->user();
        if ($user->role !== 'admin' && (int) $product->shipper_id !== (int) $user->id) {
            return $this->sendError('Anda hanya dapat mengubah komoditas milik Anda sendiri.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'island' => ['sometimes', 'required', 'in:Sumatra,Jawa,Kalimantan,Sulawesi,Papua'],
            'region' => ['sometimes', 'required', 'string', 'max:160'],
            'origin_location_id' => ['nullable', 'exists:locations,id'],
            'bean_type' => ['sometimes', 'required', 'in:Arabika,Robusta,Liberika,Campuran'],
            'process_method' => ['nullable', 'string', 'max:100'],
            'altitude_mdpl' => ['nullable', 'string', 'max:80'],
            'tasting_notes' => ['nullable', 'string', 'max:255'],
            'supply_capacity' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi pembaruan produk gagal', $validator->errors(), 422);
        }

        $product->update($validator->validated());

        return $this->sendResponse(
            $product->fresh(['shipper:id,name,email,phone,company_name,city,country,is_verified', 'originLocation:id,code,name,island,type']),
            'Data komoditas kopi berhasil diperbarui'
        );
    }

    /**
     * DELETE /api/v1/export-products/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $product = ExportProduct::find($id);
        if (!$product) {
            return $this->sendError('Produk kopi tidak ditemukan', null, 404);
        }

        $user = $request->user();
        if ($user->role !== 'admin' && (int) $product->shipper_id !== (int) $user->id) {
            return $this->sendError('Anda hanya dapat menghapus komoditas milik Anda sendiri.', null, 403);
        }

        $product->delete();

        return $this->sendResponse(null, 'Komoditas kopi berhasil dihapus dari direktori');
    }
}
