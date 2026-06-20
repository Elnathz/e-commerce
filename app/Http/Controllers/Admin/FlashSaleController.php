<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Services\FlashSaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FlashSaleController extends Controller
{
    public function index()
    {
        $sales = FlashSale::withCount('items')
            ->orderByDesc('starts_at')
            ->get()
            ->map(function ($sale) {
                $sale->status = $this->computeStatus($sale);
                return $sale;
            });

        return Inertia::render('Admin/FlashSales/Index', [
            'sales' => $sales,
            'stats' => [
                'total' => FlashSale::count(),
                'active' => FlashSale::query()->activeAt(now())->count(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/FlashSales/Form', [
            'sale' => null,
            'products' => $this->productOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $this->guardItems($data['items'], $data['starts_at'], $data['ends_at'], null);

        DB::transaction(function () use ($data) {
            $sale = FlashSale::create([
                'name' => $data['name'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['items'] as $item) {
                FlashSaleItem::create([
                    'flash_sale_id' => $sale->id,
                    'product_variant_id' => $item['product_variant_id'],
                    'sale_price' => $item['sale_price'],
                    'quota' => $item['quota'] ?? null,
                ]);
            }
        });

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash Sale ditambahkan.');
    }

    public function edit(FlashSale $flashSale)
    {
        $flashSale->load('items.variant.product');

        return Inertia::render('Admin/FlashSales/Form', [
            'sale' => $flashSale,
            'products' => $this->productOptions(),
        ]);
    }

    public function update(Request $request, FlashSale $flashSale)
    {
        $data = $this->validateData($request);

        $this->guardItems($data['items'], $data['starts_at'], $data['ends_at'], $flashSale);
        $this->guardActiveEdit($flashSale, $data);

        $existingIds = $flashSale->items()->pluck('id')->all();
        $submittedIds = [];
        foreach ($data['items'] as $item) {
            if (! empty($item['id']) && in_array((int) $item['id'], $existingIds, true)) {
                $submittedIds[] = (int) $item['id'];
            }
        }

        // Items dropped from the submitted payload but referenced by an order_item must not
        // be removed from the sale: doing so would silently keep charging the flash price on
        // an order the admin believes was excluded. Abort the WHOLE update before any writes —
        // partially applying name/window/other-item changes while rejecting the removal would
        // leave the sale in a state the admin didn't ask for.
        $removedIds = array_diff($existingIds, $submittedIds);
        if (! empty($removedIds)) {
            $referenced = \App\Models\OrderItem::whereIn('flash_sale_item_id', $removedIds)
                ->pluck('flash_sale_item_id')->unique()->all();

            if (! empty($referenced)) {
                throw ValidationException::withMessages([
                    'items' => 'Varian yang sudah ada di pesanan tidak bisa dihapus dari Flash Sale yang berjalan. Untuk menghentikannya, akhiri (hapus) seluruh Flash Sale ini.',
                ]);
            }
        }

        DB::transaction(function () use ($flashSale, $data, $existingIds, $removedIds) {
            $flashSale->update([
                'name' => $data['name'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['items'] as $item) {
                if (! empty($item['id']) && in_array((int) $item['id'], $existingIds, true)) {
                    $flashSale->items()->where('id', $item['id'])->update([
                        'product_variant_id' => $item['product_variant_id'],
                        'sale_price' => $item['sale_price'],
                        'quota' => $item['quota'] ?? null,
                    ]);
                } else {
                    FlashSaleItem::create([
                        'flash_sale_id' => $flashSale->id,
                        'product_variant_id' => $item['product_variant_id'],
                        'sale_price' => $item['sale_price'],
                        'quota' => $item['quota'] ?? null,
                    ]);
                }
            }

            if (! empty($removedIds)) {
                FlashSaleItem::whereIn('id', $removedIds)->delete();
            }
        });

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash Sale diperbarui.');
    }

    public function destroy(FlashSale $flashSale)
    {
        $itemIds = $flashSale->items()->pluck('id');
        $hasOrders = $itemIds->isNotEmpty()
            && \App\Models\OrderItem::whereIn('flash_sale_item_id', $itemIds)->exists();

        if ($hasOrders) {
            // Preserve order history integrity: end the sale instead of hard-deleting.
            $flashSale->update(['is_active' => false, 'ends_at' => now()]);
            return redirect()->route('admin.flash-sales.index')->with('success', 'Flash Sale diakhiri (riwayat pesanan dipertahankan).');
        }

        $flashSale->delete();
        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash Sale dihapus.');
    }

    private function computeStatus(FlashSale $sale): string
    {
        if (! $sale->is_active) {
            return 'nonaktif';
        }
        $now = now();
        if ($sale->starts_at->gt($now)) {
            return 'terjadwal';
        }
        if ($sale->ends_at->lte($now)) {
            return 'selesai';
        }
        return 'aktif';
    }

    private function productOptions()
    {
        return Product::where('is_active', true)
            ->with(['variants' => function ($q) {
                $q->where('is_active', true)->select(['id', 'product_id', 'name', 'sku', 'price', 'discount_price']);
            }])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'price' => (float) $v->price,
                    'discount_price' => $v->discount_price !== null ? (float) $v->discount_price : null,
                ]),
            ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.product_variant_id' => 'required|integer|exists:product_variants,id|distinct',
            'items.*.sale_price' => 'required|numeric|min:0',
            'items.*.quota' => 'nullable|integer|min:0',
        ]);
    }

    /**
     * For each submitted item: (1) inversion guard via FlashSaleService —
     * sale_price must stay below normal price and below any active manual
     * discount; (2) anti-overlap guard — the same variant cannot belong to
     * two active/scheduled flash sales whose windows overlap.
     *
     * Both guard failures are converted to ValidationException so the admin
     * sees a normal form error instead of a 500.
     */
    private function guardItems(array $items, string $startsAt, string $endsAt, ?FlashSale $excluding): void
    {
        $service = app(FlashSaleService::class);

        foreach ($items as $index => $item) {
            $variant = \App\Models\ProductVariant::with('product')->find($item['product_variant_id']);
            if (! $variant) {
                continue; // exists rule already enforced by validateData
            }

            try {
                $service->assertSalePriceValid($variant, (float) $item['sale_price']);
            } catch (\Exception $e) {
                throw ValidationException::withMessages([
                    "items.{$index}.sale_price" => $e->getMessage(),
                ]);
            }

            $this->guardOverlap($item, $index, $startsAt, $endsAt, $excluding);
        }
    }

    /**
     * Reject if the variant is already in another flash_sale_item whose
     * flash_sale window overlaps [$startsAt, $endsAt) AND is active. The
     * sale currently being edited is excluded from the comparison (its own
     * items don't conflict with themselves).
     */
    private function guardOverlap(array $item, int $index, string $startsAt, string $endsAt, ?FlashSale $excluding): void
    {
        $query = FlashSaleItem::where('product_variant_id', $item['product_variant_id'])
            ->whereHas('flashSale', function ($q) use ($startsAt, $endsAt, $excluding) {
                $q->where('is_active', true)
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
                if ($excluding) {
                    $q->where('id', '!=', $excluding->id);
                }
            });

        if ($excluding) {
            // Exclude the item's own row when editing in place (same flash_sale, same item id).
            if (! empty($item['id'])) {
                $query->where('id', '!=', $item['id']);
            }
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                "items.{$index}.product_variant_id" => 'Varian ini sudah ikut Flash Sale lain yang waktunya beririsan.',
            ]);
        }
    }

    /**
     * While a sale is currently within its active window, disallow raising
     * sale_price (only lowering/equal allowed) and disallow setting quota
     * below the already-sold count. Extending ends_at and raising quota
     * remain allowed.
     */
    private function guardActiveEdit(FlashSale $sale, array $data): void
    {
        $now = now();
        $isCurrentlyActive = $sale->is_active && $sale->starts_at->lte($now) && $sale->ends_at->gt($now);

        if (! $isCurrentlyActive) {
            return;
        }

        $existingItems = $sale->items()->get()->keyBy('id');

        foreach ($data['items'] as $index => $item) {
            if (empty($item['id']) || ! $existingItems->has($item['id'])) {
                continue; // new item added while active — inversion/overlap guards already covered it
            }

            $existing = $existingItems->get($item['id']);

            if ((float) $item['sale_price'] > (float) $existing->sale_price) {
                throw ValidationException::withMessages([
                    "items.{$index}.sale_price" => 'Tidak bisa menaikkan harga flash saat sale sedang aktif.',
                ]);
            }

            if ($item['quota'] !== null && (int) $item['quota'] < $existing->sold_count) {
                throw ValidationException::withMessages([
                    "items.{$index}.quota" => "Kuota tidak bisa diturunkan di bawah jumlah terjual ({$existing->sold_count}) saat sale sedang aktif.",
                ]);
            }
        }
    }
}
