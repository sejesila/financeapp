<?php

namespace App\Http\Controllers;

use App\Models\CafeteriaMenuItem;
use App\Models\CafeteriaOrder;
use App\Models\CafeteriaOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CafeteriaController extends Controller
{

    // ===================== DASHBOARD =====================

    public function index(Request $request)
    {
        $user = Auth::user();
        $month = $request->get('month', now()->format('Y-m'));
        $monthDate = Carbon::parse($month . '-01');

        $orders = CafeteriaOrder::with('items.menuItem')
            ->where('user_id', $user->id)
            ->whereYear('order_date', $monthDate->year)
            ->whereMonth('order_date', $monthDate->month)
            ->orderByDesc('order_date')
            ->paginate(10);

        $monthlyTotal = CafeteriaOrder::where('user_id', $user->id)
            ->whereYear('order_date', $monthDate->year)
            ->whereMonth('order_date', $monthDate->month)
            ->sum('total_amount');

        $todayTotal = CafeteriaOrder::where('user_id', $user->id)
            ->where('order_date', today())
            ->sum('total_amount');

        $weekTotal = CafeteriaOrder::where('user_id', $user->id)
            ->whereBetween('order_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('total_amount');

        // Top items this month
        $topItems = CafeteriaOrderItem::join('cafeteria_orders', 'cafeteria_order_items.cafeteria_order_id', '=', 'cafeteria_orders.id')
            ->join('cafeteria_menu_items', 'cafeteria_order_items.cafeteria_menu_item_id', '=', 'cafeteria_menu_items.id')
            ->where('cafeteria_orders.user_id', $user->id)
            ->whereYear('cafeteria_orders.order_date', $monthDate->year)
            ->whereMonth('cafeteria_orders.order_date', $monthDate->month)
            ->select('cafeteria_menu_items.name', DB::raw('SUM(cafeteria_order_items.quantity) as total_qty'), DB::raw('SUM(cafeteria_order_items.subtotal) as total_spent'))
            ->groupBy('cafeteria_menu_items.name')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        // Daily spend chart data
        $dailySpend = CafeteriaOrder::where('user_id', $user->id)
            ->whereYear('order_date', $monthDate->year)
            ->whereMonth('order_date', $monthDate->month)
            ->select('order_date', DB::raw('SUM(total_amount) as daily_total'))
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->map(fn($r) => [
                'date'  => ($r->order_date instanceof \Carbon\Carbon)
                    ? $r->order_date->format('d')
                    : \Carbon\Carbon::parse($r->order_date)->format('d'),
                'total' => (float) $r->daily_total,
            ]);

        return view('cafeteria.index', compact(
            'orders', 'monthlyTotal', 'todayTotal', 'weekTotal', 'topItems', 'dailySpend', 'month', 'monthDate'
        ));
    }

    // ===================== ORDERS =====================

    public function create()
    {
        $menuItems = CafeteriaMenuItem::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $mealTimes = CafeteriaOrder::$mealTimes;

        return view('cafeteria.create', compact('menuItems', 'mealTimes'));
    }

    public function store(Request $request)
    {
        $validMealTimes = array_keys(CafeteriaOrder::$mealTimes);

        $request->validate([
            'order_date'                   => 'required|date',
            'meal_time'                    => 'required|in:' . implode(',', $validMealTimes),
            'items'                        => 'required|array|min:1',
            'items.*.menu_item_id'         => 'required|exists:cafeteria_menu_items,id',
            'items.*.quantity'             => 'required|integer|min:1',
            'notes'                        => 'nullable|string|max:500',
        ]);

        // Calculate total amount before opening the transaction
        $totalAmount = 0;
        foreach ($request->items as $item) {
            if (empty($item['menu_item_id']) || empty($item['quantity'])) {
                continue;
            }
            $menuItem     = CafeteriaMenuItem::findOrFail($item['menu_item_id']);
            $totalAmount += $menuItem->unit_price * $item['quantity'];
        }

        // Check if user has enough budget
        $user = Auth::user();
        if (!$user->hasEnoughBudget($totalAmount)) {
            $remaining = $user->getRemainingBudgetThisMonth();
            return back()
                ->withInput()
                ->with('error', "Insufficient budget. You only have KES " . number_format($remaining, 0) . " remaining this month. This meal costs KES " . number_format($totalAmount, 0) . ".");
        }

        DB::transaction(function () use ($request, $user, $totalAmount) {
            $order = CafeteriaOrder::create([
                'user_id'      => $user->id,
                'order_date'   => $request->order_date,
                'meal_time'    => $request->meal_time,
                'notes'        => $request->notes,
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($request->items as $item) {
                if (empty($item['menu_item_id']) || empty($item['quantity'])) {
                    continue;
                }

                $menuItem = CafeteriaMenuItem::findOrFail($item['menu_item_id']);
                $subtotal  = $menuItem->unit_price * $item['quantity'];
                $total    += $subtotal;

                CafeteriaOrderItem::create([
                    'cafeteria_order_id'      => $order->id,
                    'cafeteria_menu_item_id'  => $menuItem->id,
                    'quantity'                => $item['quantity'],
                    'unit_price'              => $menuItem->unit_price,
                    'subtotal'                => $subtotal,
                ]);
            }

            if ($total === 0) {
                throw new \RuntimeException('Order must contain at least one valid item.');
            }

            $order->update(['total_amount' => $total]);

            // Update monthly spending tracker
            $user->getCurrentMonthlySpendings()->addSpending($total);
        });

        return redirect()->route('cafeteria.index')->with('success', 'Meal logged successfully!');
    }

    public function show(CafeteriaOrder $order)
    {
        abort_if($order->user_id !== Auth::id(), 403);
        $order->load('items.menuItem');
        return view('cafeteria.show', compact('order'));
    }

    public function edit(CafeteriaOrder $order)
    {
        abort_if($order->user_id !== Auth::id(), 403);

        if (!$order->isEditable()) {
            return redirect()->route('cafeteria.show', $order)
                ->with('error', 'This meal entry is no longer editable. Entries can only be edited within 1 hour of creation.');
        }

        $order->load('items.menuItem');

        $menuItems = CafeteriaMenuItem::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $mealTimes = CafeteriaOrder::$mealTimes;

        return view('cafeteria.edit', compact('order', 'menuItems', 'mealTimes'));
    }

    public function update(Request $request, CafeteriaOrder $order)
    {
        abort_if($order->user_id !== Auth::id(), 403);

        if (!$order->isEditable()) {
            return redirect()->route('cafeteria.show', $order)
                ->with('error', 'This meal entry is no longer editable. Entries can only be edited within 1 hour of creation.');
        }

        $validMealTimes = array_keys(CafeteriaOrder::$mealTimes);

        $request->validate([
            'order_date'                   => 'required|date',
            'meal_time'                    => 'required|in:' . implode(',', $validMealTimes),
            'items'                        => 'required|array|min:1',
            'items.*.menu_item_id'         => 'required|exists:cafeteria_menu_items,id',
            'items.*.quantity'             => 'required|integer|min:1',
            'notes'                        => 'nullable|string|max:500',
        ]);

        // Calculate new total before the transaction so we can budget-check first
        $newTotalAmount = 0;
        foreach ($request->items as $item) {
            if (empty($item['menu_item_id']) || empty($item['quantity'])) {
                continue;
            }
            $menuItem        = CafeteriaMenuItem::findOrFail($item['menu_item_id']);
            $newTotalAmount += $menuItem->unit_price * $item['quantity'];
        }

        // Only check budget for the incremental difference
        $user       = Auth::user();
        $oldAmount  = $order->total_amount;
        $difference = $newTotalAmount - $oldAmount;

        if ($difference > 0 && !$user->hasEnoughBudget($difference)) {
            $remaining = $user->getRemainingBudgetThisMonth();
            return back()
                ->withInput()
                ->with('error', "Insufficient budget. You only have KES " . number_format($remaining, 0) . " remaining. This change requires KES " . number_format($difference, 0) . " more.");
        }

        DB::transaction(function () use ($request, $order, $user, $oldAmount) {
            $order->update([
                'order_date' => $request->order_date,
                'meal_time'  => $request->meal_time,
                'notes'      => $request->notes,
            ]);

            $order->items()->delete();

            $total = 0;

            foreach ($request->items as $item) {
                if (empty($item['menu_item_id']) || empty($item['quantity'])) {
                    continue;
                }

                $menuItem = CafeteriaMenuItem::findOrFail($item['menu_item_id']);
                $subtotal  = $menuItem->unit_price * $item['quantity'];
                $total    += $subtotal;

                CafeteriaOrderItem::create([
                    'cafeteria_order_id'      => $order->id,
                    'cafeteria_menu_item_id'  => $menuItem->id,
                    'quantity'                => $item['quantity'],
                    'unit_price'              => $menuItem->unit_price,
                    'subtotal'                => $subtotal,
                ]);
            }

            if ($total === 0) {
                throw new \RuntimeException('Order must contain at least one valid item.');
            }

            $order->update(['total_amount' => $total]);

            // Adjust monthly spending by the difference
            $difference        = $total - $oldAmount;
            $monthlySpendings  = $user->getCurrentMonthlySpendings();

            if ($difference > 0) {
                $monthlySpendings->addSpending($difference);
            } elseif ($difference < 0) {
                $monthlySpendings->subtractSpending(abs($difference));
            }
        });

        return redirect()->route('cafeteria.index')->with('success', 'Meal updated successfully!');
    }

    public function destroy(CafeteriaOrder $order)
    {
        abort_if($order->user_id !== Auth::id(), 403);

        if (!$order->isEditable()) {
            return redirect()->route('cafeteria.show', $order)
                ->with('error', 'This meal entry is no longer deletable. Entries can only be deleted within 1 hour of creation.');
        }

        $amountToRefund = $order->total_amount;

        $order->items()->delete();
        $order->delete();

        // Refund the amount back to the monthly spending tracker
        $user = Auth::user();
        $user->getCurrentMonthlySpendings()->subtractSpending($amountToRefund);

        return redirect()->route('cafeteria.index')->with('success', 'Meal entry deleted and KES ' . number_format($amountToRefund, 0) . ' refunded to your budget.');
    }

    // ===================== BUDGET INFO =====================

    /**
     * Returns current budget status as JSON (for live UI updates).
     */
    public function getBudgetInfo()
    {
        $user = Auth::user();

        return response()->json([
            'monthly_limit' => (float) $user->cafeteria_monthly_limit,
            'total_spent'   => $user->getTotalSpentThisMonth(),
            'remaining'     => $user->getRemainingBudgetThisMonth(),
            'percentage'    => $user->getSpendingPercentageThisMonth(),
            'status'        => $user->getBudgetStatus(),
            'color'         => $user->getBudgetStatusColor(),
            'is_critical'   => $user->isCriticalBudget(),
            'is_warning'    => $user->isWarningBudget(),
            'is_exceeded'   => $user->hasExceededLimit(),
        ]);
    }

    // ===================== BUDGET LIMIT =====================

    /**
     * Update the user's monthly cafeteria budget limit.
     * Only allowed once per calendar month.
     */
    public function updateBudgetLimit(Request $request)
    {
        $request->validate([
            'cafeteria_monthly_limit' => 'required|numeric|min:1|max:999999',
        ]);

        $user = Auth::user();

        if (!$user->canEditMonthlyLimit()) {
            return back()->with('error',
                'You can only change your monthly budget limit once per month. '
                . 'Next change allowed from ' . $user->nextLimitEditAllowedAt() . '.'
            );
        }

        $user->updateMonthlyLimit((float) $request->cafeteria_monthly_limit);

        return back()->with('success', 'Monthly budget limit updated to KES '
            . number_format($request->cafeteria_monthly_limit, 0) . '.');
    }

    // ===================== MENU MANAGEMENT =====================

    public function menu()
    {
        $menuItems      = CafeteriaMenuItem::orderBy('category')->orderBy('name')->get()->groupBy('category');
        $categories     = CafeteriaMenuItem::$categories;
        $categoryIcons  = CafeteriaMenuItem::$categoryIcons;

        return view('cafeteria.menu', compact('menuItems', 'categories', 'categoryIcons'));
    }

    public function storeMenuItem(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'category'   => 'required|in:' . implode(',', array_keys(CafeteriaMenuItem::$categories)),
            'unit_price' => 'required|numeric|min:0',
        ]);

        CafeteriaMenuItem::create(
            $request->only('name', 'category', 'unit_price') + ['is_active' => true]
        );

        return redirect()->route('cafeteria.menu')->with('success', 'Menu item added!');
    }

    public function updateMenuItem(Request $request, CafeteriaMenuItem $menuItem)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'category'   => 'required|in:' . implode(',', array_keys(CafeteriaMenuItem::$categories)),
            'unit_price' => 'required|numeric|min:0',
            'is_active'  => 'boolean',
        ]);

        $menuItem->update([
            'name'       => $request->name,
            'category'   => $request->category,
            'unit_price' => $request->unit_price,
            'is_active'  => $request->boolean('is_active', true),
        ]);

        return redirect()->route('cafeteria.menu')->with('success', 'Menu item updated!');
    }

    public function hideMenuItem(CafeteriaMenuItem $menuItem)
    {
        $menuItem->update(['is_active' => false]);
        return redirect()->route('cafeteria.menu')->with('success', 'Menu item hidden.');
    }

    // ===================== SEEDER (admin only) =====================

    public function seedMenu()
    {
        $items = [
            // Main dishes - Rice
            ['name' => 'Rice Beef',           'category' => 'main_dish', 'unit_price' => 170],
            ['name' => 'Rice Chicken',         'category' => 'main_dish', 'unit_price' => 250],
            ['name' => 'Rice Liver',           'category' => 'main_dish', 'unit_price' => 250],
            ['name' => 'Rice Matumbo',         'category' => 'main_dish', 'unit_price' => 160],
            ['name' => 'Rice Goat',            'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Rice Beans',           'category' => 'main_dish', 'unit_price' => 120],
            // Ugali
            ['name' => 'Ugali Beef',           'category' => 'main_dish', 'unit_price' => 160],
            ['name' => 'Ugali Chicken',        'category' => 'main_dish', 'unit_price' => 200],
            ['name' => 'Ugali Matumbo',        'category' => 'main_dish', 'unit_price' => 160],
            ['name' => 'Ugali Goat',           'category' => 'main_dish', 'unit_price' => 280],
            // Chapati
            ['name' => 'Chapati Beef',         'category' => 'main_dish', 'unit_price' => 170],
            ['name' => 'Chapati Chicken',      'category' => 'main_dish', 'unit_price' => 220],
            ['name' => 'Chapati Goat',         'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Chapati Liver',        'category' => 'main_dish', 'unit_price' => 220],
            ['name' => 'Chapati Ndengu',       'category' => 'main_dish', 'unit_price' => 130],
            ['name' => 'Chapati Matumbo',      'category' => 'main_dish', 'unit_price' => 160],
            ['name' => 'Chapati/Rice/Kienyeji',           'category' => 'main_dish', 'unit_price' => 130],
            ['name' => 'Chapati/Rice/Beans/Ndengu',       'category' => 'main_dish', 'unit_price' => 130],
            // Mashed
            ['name' => 'Mashed Beef',          'category' => 'main_dish', 'unit_price' => 250],
            ['name' => 'Mashed Chicken',       'category' => 'main_dish', 'unit_price' => 290],
            ['name' => 'Mashed Matumbo',       'category' => 'main_dish', 'unit_price' => 230],
            ['name' => 'Mashed Liver',         'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Mashed Goat',          'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Mashed Veges',         'category' => 'main_dish', 'unit_price' => 130],
            // Matoke
            ['name' => 'Matoke Beef',          'category' => 'main_dish', 'unit_price' => 220],
            ['name' => 'Matoke Chicken',       'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Matoke Plain',         'category' => 'main_dish', 'unit_price' => 130],
            // Potato Wages
            ['name' => 'Potato Wages/Beef',    'category' => 'main_dish', 'unit_price' => 250],
            ['name' => 'Potato Wages/Goat',    'category' => 'main_dish', 'unit_price' => 290],
            ['name' => 'Potato Wages/Liver',   'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Potato Wages/Chicken', 'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Potato Wages Plain',   'category' => 'main_dish', 'unit_price' => 140],
            // Chips
            ['name' => 'Chips Plain',          'category' => 'main_dish', 'unit_price' => 100],
            ['name' => 'Chips Liver',          'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Chips Goat',           'category' => 'main_dish', 'unit_price' => 300],
            ['name' => 'Chips Chicken',        'category' => 'main_dish', 'unit_price' => 250],
            ['name' => 'Chips Masala Plain',   'category' => 'main_dish', 'unit_price' => 130],
            ['name' => 'Chips Masala Goat',    'category' => 'main_dish', 'unit_price' => 300],
            ['name' => 'Chips Masala Liver',   'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Chips Masala Chicken', 'category' => 'main_dish', 'unit_price' => 280],
            // Petite / Lyonnaise / Wedges
            ['name' => 'Wedges',               'category' => 'main_dish', 'unit_price' => 140],
            ['name' => 'Lyonnaise 1/2',        'category' => 'main_dish', 'unit_price' => 70],
            ['name' => 'Lyonnaise Full',       'category' => 'main_dish', 'unit_price' => 140],
            // Githeri / Mukimo
            ['name' => 'Githeri Plain',        'category' => 'main_dish', 'unit_price' => 120],
            ['name' => 'Mukimo Plain',         'category' => 'main_dish', 'unit_price' => 130],
            ['name' => 'Mukimo Beef',          'category' => 'main_dish', 'unit_price' => 240],
            ['name' => 'Mukimo Chicken',       'category' => 'main_dish', 'unit_price' => 270],
            ['name' => 'Mukimo Liver',         'category' => 'main_dish', 'unit_price' => 280],
            ['name' => 'Mukimo Matumbo',       'category' => 'main_dish', 'unit_price' => 230],
            ['name' => 'Mukimo Goat',          'category' => 'main_dish', 'unit_price' => 300],
            // Pilau
            ['name' => 'Pilau Beef',           'category' => 'main_dish', 'unit_price' => 250],
            ['name' => 'Pilau Liver',          'category' => 'main_dish', 'unit_price' => 300],
            ['name' => 'Pilau Matumbo',        'category' => 'main_dish', 'unit_price' => 230],
            ['name' => 'Pilau Beans',          'category' => 'main_dish', 'unit_price' => 180],
            ['name' => 'Pilau Plain',          'category' => 'main_dish', 'unit_price' => 130],
            ['name' => 'Pilau Chicken',        'category' => 'main_dish', 'unit_price' => 280],
            // Plain proteins
            ['name' => 'Beef Plain',           'category' => 'main_dish', 'unit_price' => 120],
            ['name' => 'Liver Plain',          'category' => 'main_dish', 'unit_price' => 130],
            ['name' => 'Matumbo Plain',        'category' => 'main_dish', 'unit_price' => 100],
            ['name' => 'Chicken Plain',        'category' => 'main_dish', 'unit_price' => 160],
            ['name' => 'Goat Plain',           'category' => 'main_dish', 'unit_price' => 180],
            ['name' => 'Ugali Plain',          'category' => 'main_dish', 'unit_price' => 50],
            // Veges
            ['name' => 'Kienyeji/Ugali',       'category' => 'main_dish', 'unit_price' => 130],
            ['name' => 'Kienyeji Plain',       'category' => 'main_dish', 'unit_price' => 80],
            ['name' => 'Beans',                'category' => 'main_dish', 'unit_price' => 80],
            ['name' => 'Ndengu',               'category' => 'main_dish', 'unit_price' => 80],
            ['name' => 'Egg Curry/Rice',       'category' => 'main_dish', 'unit_price' => 140],
            ['name' => 'Egg Curry/Chapati',    'category' => 'main_dish', 'unit_price' => 120],
            // SNACKS
            ['name' => 'Andazi',               'category' => 'snack', 'unit_price' => 15],
            ['name' => 'Samosa',               'category' => 'snack', 'unit_price' => 50],
            ['name' => 'Sausage',              'category' => 'snack', 'unit_price' => 50],
            ['name' => 'Kebab',                'category' => 'snack', 'unit_price' => 80],
            ['name' => 'Smokie',               'category' => 'snack', 'unit_price' => 40],
            ['name' => 'Chapati (plain)',       'category' => 'snack', 'unit_price' => 25],
            ['name' => 'Victoria Cake',        'category' => 'snack', 'unit_price' => 80],
            ['name' => 'Chocolate Cake',       'category' => 'snack', 'unit_price' => 80],
            ['name' => 'American Doughnut',    'category' => 'snack', 'unit_price' => 40],
            ['name' => 'Marble Cake',          'category' => 'snack', 'unit_price' => 70],
            ['name' => 'Meat Pie',             'category' => 'snack', 'unit_price' => 100],
            ['name' => 'Bun',                  'category' => 'snack', 'unit_price' => 40],
            ['name' => 'Doughnut',             'category' => 'snack', 'unit_price' => 40],
            ['name' => 'Ngwaci',               'category' => 'snack', 'unit_price' => 40],
            ['name' => 'Chicken Wings',        'category' => 'snack', 'unit_price' => 60],
            ['name' => 'Maize',                'category' => 'snack', 'unit_price' => 50],
            ['name' => 'Boiled Egg',           'category' => 'snack', 'unit_price' => 30],
            ['name' => 'Pan Cake',             'category' => 'snack', 'unit_price' => 60],
            ['name' => 'Ngumu',                'category' => 'snack', 'unit_price' => 25],
            ['name' => 'Nduma',                'category' => 'snack', 'unit_price' => 50],
            // BEVERAGES
            ['name' => 'Mixed Tea',                    'category' => 'beverage', 'unit_price' => 30],
            ['name' => 'Tea Scone',                    'category' => 'beverage', 'unit_price' => 30],
            ['name' => 'Nylon',                        'category' => 'beverage', 'unit_price' => 60],
            ['name' => 'Black Tea',                    'category' => 'beverage', 'unit_price' => 20],
            // SOFT DRINKS
            ['name' => 'Soda Mirinda',                 'category' => 'soft_drink', 'unit_price' => 50],
            ['name' => 'Plastic Soda 500ml/Coca-Cola', 'category' => 'soft_drink', 'unit_price' => 80],
            // FRUITS
            ['name' => 'Banana',      'category' => 'fruit', 'unit_price' => 15],
            ['name' => 'Mango',       'category' => 'fruit', 'unit_price' => 30],
            ['name' => 'Orange',      'category' => 'fruit', 'unit_price' => 20],
        ];

        foreach ($items as $item) {
            CafeteriaMenuItem::firstOrCreate(
                ['name' => $item['name'], 'category' => $item['category']],
                ['unit_price' => $item['unit_price'], 'is_active' => true]
            );
        }

        return redirect()->route('cafeteria.menu')->with('success', 'Menu seeded successfully!');
    }
}
