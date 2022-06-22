<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $this->authorize('view-roles');
        $roles = Role::all();
        return response()->json($roles);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', Role::class);
        $data = $request->validate([
            'name'      => ['required', 'unique:roles,name'],
        ]);
        $role = Role::create($data);
        // $role->permissions()->sync($request->permission);
        return response()->json(['message' => 'Role has been Created Successfully!', 'role' => $role], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);
        return response()->json($role);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);
        $data = $request->validate([
            'name'      => ['required'],
        ]);
        $role->update($data);
        $role->fresh();

        return response()->json(['message' => 'Role has been Updated Successfully!', 'role' => $role], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);
        $role->delete();
        return response()->json(['message' => 'Role has been Deleted Successfully!!'], 200);
    }

    public function assignPermission(Request $request, Role $role)
    {
        $this->authorize('update', $role);
        $data = $request->validate([
            'permission' => ['required', 'array'],
            'permission.*' => ['numeric', 'exists:permissions,id'],
        ]);
        $role->permissions()->sync($data['permission']);
        $role->fresh();
        $role->load('permissions');
        return response()->json(['message' => 'Permission has been assigned to given role!', 'permissions' => $role], 200);
    }
}
