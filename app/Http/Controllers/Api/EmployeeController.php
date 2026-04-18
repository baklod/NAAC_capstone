<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmployeeCredentialsMail;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::query()
            ->with([
                'branch:id,name,location,status',
                'user:id,employee_id,user_name,email,role',
            ])
            ->whereDoesntHave('user', function ($query) {
                $query->where('role', 'admin');
            })
            ->latest()
            ->get();

        return response()->json(['data' => $employees]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['required', 'integer', 'min:1', Rule::unique('employees', 'contact_number')],
            'contact_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('employees', 'contact_email'),
                Rule::unique('users', 'email'),
            ],
            'address' => ['required', 'string', 'max:255', Rule::unique('employees', 'address')],
            'profile_picture' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('employees', 'public');
            $validated['profile_picture'] = Storage::url($path);
        }

        $fullName = trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));
        $temporaryPassword = $this->generateTemporaryPassword();
        $createdUser = null;

        DB::beginTransaction();

        try {
            $employee = Employee::create($validated);

            $generatedUsername = $this->generateUniqueUsername(
                $validated['first_name'],
                $validated['last_name'] ?? null,
            );

            $createdUser = User::create([
                'employee_id' => $employee->id,
                'user_name' => $generatedUsername,
                'name' => $fullName,
                'email' => $validated['contact_email'],
                'password' => $temporaryPassword,
                'role' => 'staff',
                'is_active' => true,
                'is_online' => false,
            ]);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $employee->load('branch:id,name');
        $credentialsSent = false;

        try {
            Mail::to($validated['contact_email'])->send(
                new EmployeeCredentialsMail(
                    employeeName: $fullName,
                    username: $createdUser->user_name,
                    password: $temporaryPassword,
                    branchName: $employee->branch?->name,
                )
            );

            $credentialsSent = true;
        } catch (\Throwable $exception) {
            report($exception);
        }

        $employee->load([
            'branch:id,name,location,status',
            'user:id,employee_id,user_name,email,role',
        ]);

        return response()->json([
            'message' => $credentialsSent
                ? 'Employee created successfully. Login credentials were sent to the employee email.'
                : 'Employee created successfully, but credentials email could not be sent. Please check mail settings.',
            'credentials_sent' => $credentialsSent,
            'data' => $employee,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $employee = Employee::findOrFail($id);
        $currentUserId = $employee->user?->id;

        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'contact_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('employees', 'contact_number')->ignore($employee->id),
            ],
            'contact_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('employees', 'contact_email')->ignore($employee->id),
                Rule::unique('users', 'email')->ignore($currentUserId),
            ],
            'address' => [
                'required',
                'string',
                'max:255',
                Rule::unique('employees', 'address')->ignore($employee->id),
            ],
            'profile_picture' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('profile_picture')) {
            $this->deletePublicFileFromUrl($employee->profile_picture);

            $path = $request->file('profile_picture')->store('employees', 'public');
            $validated['profile_picture'] = Storage::url($path);
        }

        $employee->update($validated);

        if ($employee->user) {
            $employee->user->update([
                'name' => trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? '')),
                'email' => $validated['contact_email'],
            ]);
        }

        $employee->load([
            'branch:id,name,location,status',
            'user:id,employee_id,user_name,email,role',
        ]);

        return response()->json([
            'message' => 'Employee updated successfully.',
            'data' => $employee,
        ]);
    }

    public function destroy(int $id)
    {
        $employee = Employee::findOrFail($id);

        $this->deletePublicFileFromUrl($employee->profile_picture);

        $employee->delete();

        return response()->json([
            'message' => 'Employee deleted successfully.',
        ]);
    }

    private function generateUniqueUsername(string $firstName, ?string $lastName): string
    {
        $base = Str::of(trim($firstName . ' ' . ($lastName ?? '')))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();

        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $suffix = 1;

        while (User::where('user_name', $username)->exists()) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    private function generateTemporaryPassword(int $length = 12): string
    {
        $lettersAndNumbers = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $symbols = '!@#$%';
        $password = '';

        for ($index = 0; $index < $length - 2; $index++) {
            $password .= $lettersAndNumbers[random_int(0, strlen($lettersAndNumbers) - 1)];
        }

        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        $password .= strtoupper($lettersAndNumbers[random_int(0, strlen($lettersAndNumbers) - 1)]);

        return str_shuffle($password);
    }

    private function deletePublicFileFromUrl(?string $fileUrl): void
    {
        if (!$fileUrl) {
            return;
        }

        $path = parse_url($fileUrl, PHP_URL_PATH) ?: $fileUrl;

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $path = ltrim($path, '/');

        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
