<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('emails')->get();
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'emails' => 'array',
            'emails.*' => 'email|unique:user_emails,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'name' => $request->first_name . ' ' . $request->last_name
        ]);

        // Додаємо додаткові електронні адреси
        if ($request->has('emails')) {
            foreach ($request->emails as $email) {
                UserEmail::create([
                    'user_id' => $user->id,
                    'email' => $email
                ]);
            }
        }

        $user->load('emails');

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('emails')->find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $id,
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'emails' => 'array',
            'emails.*' => 'email|unique:user_emails,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['first_name', 'last_name', 'phone', 'email']));
        
        if ($request->has('first_name') || $request->has('last_name')) {
            $user->update([
                'name' => ($request->first_name ?? $user->first_name) . ' ' . ($request->last_name ?? $user->last_name)
            ]);
        }

        // Update emails
        if ($request->has('emails')) {
            // Delete old emails
            $user->emails()->delete();
            
            // Add new emails
            foreach ($request->emails as $email) {
                UserEmail::create([
                    'user_id' => $user->id,
                    'email' => $email
                ]);
            }
        }

        $user->load('emails');

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Send welcome email to user's all email addresses
     */
    public function sendWelcomeEmail(string $id)
    {
        $user = User::with('emails')->find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $emails = collect([$user->email])->merge($user->emails->pluck('email'))->unique();

        foreach ($emails as $email) {
            Mail::to($email)->send(new WelcomeEmail($user->first_name));
        }

        return response()->json([
            'success' => true,
            'message' => 'Welcome emails sent to all user addresses',
            'emails_sent' => $emails->count()
        ]);
    }
}
