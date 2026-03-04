<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrizeRedemption;
use Illuminate\Http\Request;

class RedemptionController extends Controller
{
    public function index()
    {
        $redemptions = PrizeRedemption::with(['user', 'prize'])->latest()->paginate(20);
        return view('admin.redemptions.index', compact('redemptions'));
    }

    public function updateStatus(Request $request, PrizeRedemption $redemption)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,delivered,rejected',
            'admin_notes' => 'nullable|string',
        ]);
        $redemption->update($request->only('status', 'admin_notes'));
        if ($request->status === 'rejected') {
            $redemption->user->increment('points', $redemption->prize->points_required);
            $redemption->prize->increment('stock');
        }
        return back()->with('success', 'Estado de canje actualizado.');
    }
}
