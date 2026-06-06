<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::with('stores:id,dfid,business_name,name')->orderBy('name')->paginate(50)]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(), 'stores' => $this->storeList()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'      => 'required|string|max:120',
            'email'     => 'required|email|unique:users,email',
            'role'      => 'required|in:'.implode(',', User::ROLES),
            'password'  => 'required|string|min:8|confirmed',
            'store_ids' => 'nullable|array',
            'store_ids.*' => 'integer|exists:stores,id',
        ]);
        $storeIds = $data['store_ids'] ?? [];
        unset($data['store_ids']);
        $user = User::create($data + ['is_active' => true]);
        $user->stores()->sync($storeIds);
        return redirect()->route('users.index')->with('status', 'User created');
    }

    public function edit(User $user)
    {
        $user->load('stores:id');
        return view('users.form', ['user' => $user, 'stores' => $this->storeList()]);
    }

    public function update(Request $r, User $user)
    {
        $data = $r->validate([
            'name'      => 'required|string|max:120',
            'email'     => 'required|email|unique:users,email,'.$user->id,
            'role'      => 'required|in:'.implode(',', User::ROLES),
            'password'  => 'nullable|string|min:8|confirmed',
            'is_active' => 'nullable|boolean',
            'store_ids' => 'nullable|array',
            'store_ids.*' => 'integer|exists:stores,id',
        ]);
        $storeIds = $data['store_ids'] ?? [];
        unset($data['store_ids']);
        if (empty($data['password'])) unset($data['password']);
        $data['is_active'] = (bool) $r->input('is_active', true);
        $user->update($data);
        $user->stores()->sync($storeIds);
        return redirect()->route('users.index')->with('status', 'User updated');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) return back()->with('error', "Can't delete self");
        $user->delete();
        return back()->with('status', 'User deleted');
    }

    protected function storeList()
    {
        return Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
    }
}
