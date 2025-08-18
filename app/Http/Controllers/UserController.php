<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Get user profile
     */
    public function profile()
    {
        $user = auth()->user();
        
        // Ensure user has a profile
        if (!$user->profile) {
            $user->profile()->create([
                'allergies' => 'Aucune',
                'chronic_diseases' => 'Aucune',
                'gender' => '',
                'blood_type' => '',
                'age' => null
            ]);
        }
        
        // Add debugging
        \Log::info('User profile data:', [
            'user_id' => $user->id,
            'profile' => $user->profile ? [
                'id' => $user->profile->id,
                'age' => $user->profile->age,
                'gender' => $user->profile->gender,
                'blood_type' => $user->profile->blood_type,
            ] : null,
            'patient_profile' => $user->patientProfile ? [
                'id' => $user->patientProfile->id,
                'age' => $user->patientProfile->age,
                'gender' => $user->patientProfile->gender,
                'blood_type' => $user->patientProfile->blood_type,
            ] : null
        ]);
        
        // Load both profiles
        return response()->json($user->load(['profile', 'patientProfile']));
    }

    /**
     * Update user profile avatar
     */
    public function updateProfileAvatar(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'avatar' => 'required|image|max:3072', // 3MB max
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = time() . '_' . $file->getClientOriginalName();

            // Store in public/uploads/avatars folder
            $path = $file->storeAs('avatars', $filename, 'public');

            // Get or create profile
            $profile = $user->profile ?? $user->profile()->create();

            // Delete old image if exists
            if ($profile->profile_image && Storage::disk('public')->exists(str_replace('/storage/', '', $profile->profile_image))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $profile->profile_image));
            }

            // Update profile image path
            $profile->profile_image = '/storage/' . $path;
            $profile->save();

            return response()->json([
                'message' => 'Avatar updated successfully',
                'path' => '/storage/' . $path
            ]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'age' => 'nullable|numeric',
            'gender' => 'nullable|string',
            'blood_type' => 'nullable|string',
            'allergies' => 'nullable',
            'chronic_diseases' => 'nullable',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Update user basic info
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Get or create profile
        $profile = $user->profile;
        if (!$profile) {
            $profile = new \App\Models\Profile();
            $profile->user_id = $user->id;
        }

        // Always save allergies/chronic_diseases as JSON array string
        $profile->age = $request->age;
        $profile->gender = $request->gender ?? '';
        $profile->blood_type = $request->blood_type ?? '';

        // Convert allergies to JSON array string if not empty
        if (is_array($request->allergies)) {
            $profile->allergies = json_encode($request->allergies);
        } elseif ($request->allergies && $request->allergies !== "Aucune") {
            // If it's a string, split and encode
            $profile->allergies = json_encode(array_map('trim', explode(',', $request->allergies)));
        } else {
            $profile->allergies = json_encode(["Aucune"]);
        }

        // Convert chronic_diseases to JSON array string if not empty
        if (is_array($request->chronic_diseases)) {
            $profile->chronic_diseases = json_encode($request->chronic_diseases);
        } elseif ($request->chronic_diseases && $request->chronic_diseases !== "Aucune") {
            $profile->chronic_diseases = json_encode(array_map('trim', explode(',', $request->chronic_diseases)));
        } else {
            $profile->chronic_diseases = json_encode(["Aucune"]);
        }

        $profile->save();

        // If this is a patient, also update patient_profile
        if ($user->role_id == 1) { // Assuming 1 is the patient role ID
            $patientProfile = $user->patientProfile;
            if ($patientProfile) {
                $patientProfile->age = $request->age;
                $patientProfile->gender = $request->gender ?? '';
                $patientProfile->blood_type = $request->blood_type ?? '';
                $patientProfile->allergies = $request->allergies ?: 'Aucune';
                $patientProfile->chronic_diseases = $request->chronic_diseases ?: 'Aucune';
                $patientProfile->save();
            }
        }

        // Log what was updated
        \Log::info('Profile updated:', [
            'user_id' => $user->id,
            'profile_data' => [
                'age' => $profile->age,
                'gender' => $profile->gender,
                'blood_type' => $profile->blood_type,
            ],
            'patient_profile' => $user->patientProfile ? [
                'age' => $user->patientProfile->age,
                'gender' => $user->patientProfile->gender,
                'blood_type' => $user->patientProfile->blood_type,
            ] : null
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()->load(['profile', 'patientProfile'])
        ]);
    }
}
