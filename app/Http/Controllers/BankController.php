<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\AccountType;
use Illuminate\Http\Request;

class BankController extends Controller
{
    /**
     * Admin: List all banks with their account types
     */
    public function index(Request $request)
    {
        return response()->json(Bank::with('accountTypes')->get());
    }

    /**
     * Admin: Store a new bank
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'account_type_ids' => 'nullable|array',
            'account_type_ids.*' => 'exists:account_types,id'
        ]);

        $bank = Bank::create($validated);

        if ($request->has('account_type_ids')) {
            $bank->accountTypes()->sync($validated['account_type_ids']);
        }

        return response()->json($bank->load('accountTypes'), 201);
    }

    /**
     * Admin: Update a bank
     */
    public function update(Request $request, $id)
    {
        $bank = Bank::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_active' => 'boolean',
            'account_type_ids' => 'sometimes|array',
            'account_type_ids.*' => 'exists:account_types,id'
        ]);

        $bank->update($validated);

        if ($request->has('account_type_ids')) {
            $bank->accountTypes()->sync($validated['account_type_ids']);
        }

        return response()->json($bank->load('accountTypes'));
    }

    /**
     * Admin: Destroy a bank
     */
    public function destroy($id)
    {
        $bank = Bank::findOrFail($id);
        $bank->delete();
        return response()->json(null, 204);
    }

    /**
     * Admin: List all account types (global)
     */
    public function accountTypes()
    {
        return response()->json(AccountType::all());
    }

    /**
     * Admin: Store a new account type
     */
    public function storeAccountType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        $type = AccountType::create($validated);
        return response()->json($type, 201);
    }

    /**
     * PublicIndex: List active banks for registration/profile
     */
    public function publicIndex()
    {
        return response()->json(Bank::where('is_active', true)->with('accountTypes')->get());
    }
}
