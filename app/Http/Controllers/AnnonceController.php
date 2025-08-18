<?php

namespace App\Http\Controllers;

use App\Models\Annonce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AnnonceController extends Controller
{
    /**
     * Display a listing of the annonces.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            Log::info('Fetching annonces for user: ' . Auth::id());

            // For admin/dashboard purposes, can include filter by user
            if ($request->has('user_id') && Auth::user()->role_id === 1) { // Assuming role_id 1 is admin
                $annonces = Annonce::where('user_id', $request->user_id)
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                // Get current user's annonces
                $annonces = Annonce::where('user_id', Auth::id())
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            Log::info('Annonces found: ' . $annonces->count());
            Log::info('Annonces data: ' . json_encode($annonces));

            return response()->json($annonces);
        } catch (\Exception $e) {
            Log::error('Error fetching annonces: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve announcements'], 500);
        }
    }

    /**
     * Display a listing of public annonces (for patients/visitors).
     *
     * @return \Illuminate\Http\Response
     */
    public function publicIndex(Request $request)
    {
        $query = Annonce::with('user')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc');

        // Apply search if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply filters if provided
        if ($request->has('category')) {
            // Assuming users have roles/categories
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role_id', $request->category);
            });
        }

        $annonces = $query->get();
        return response()->json($annonces);
    }

    /**
     * Store a newly created annonce.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            Log::info('Annonce request data: ' . json_encode($request->all()));

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'address' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'required|email|max:255',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'is_active' => 'required|in:0,1,true,false',
                'pourcentage_reduction' => 'nullable|integer|min:0|max:20', // Add validation for percentage reduction
            ]);

            if ($validator->fails()) {
                Log::warning('Annonce validation failed: ' . json_encode($validator->errors()->toArray()));
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Handle image uploads
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('annonces', 'public');
                    $imagePaths[] = '/storage/' . $path;
                }
            }

            // Convert is_active from string/boolean to boolean
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);

            // Handle percentage reduction
            $pourcentageReduction = $request->pourcentage_reduction ?? 0;
            $pourcentageReduction = max(0, min(20, intval($pourcentageReduction)));

            // Create the annonce
            $annonce = new Annonce();
            $annonce->user_id = Auth::id();
            $annonce->title = $request->title;
            $annonce->description = $request->description;
            $annonce->price = $request->price;
            $annonce->address = $request->address;
            $annonce->phone = $request->phone;
            $annonce->email = $request->email;
            $annonce->images = !empty($imagePaths) ? $imagePaths : null;
            $annonce->is_active = $isActive;
            $annonce->pourcentage_reduction = $pourcentageReduction;

            $result = $annonce->save();

            Log::info('Annonce saved result: ' . ($result ? 'success' : 'failure'));
            Log::info('Annonce ID: ' . $annonce->id);

            return response()->json($annonce, 201);
        } catch (\Exception $e) {
            Log::error('Error creating annonce: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'error' => 'An error occurred while creating the announcement',
                'details' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Display the specified annonce.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $annonce = Annonce::with('user')->findOrFail($id);

            // Check if current user can view this annonce
            if (!$annonce->is_active && Auth::check() && $annonce->user_id !== Auth::id() && Auth::user()->role_id !== 1) {
                return response()->json(['message' => 'You are not authorized to view this announcement'], 403);
            }

            return response()->json($annonce);
        } catch (\Exception $e) {
            Log::error('Error retrieving annonce: ' . $e->getMessage());
            return response()->json(['error' => 'Announcement not found'], 404);
        }
    }

    /**
     * Update the specified annonce.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $annonce = Annonce::findOrFail($id);

            // Check if current user owns this annonce
            if ($annonce->user_id !== Auth::id() && Auth::user()->role_id !== 1) {
                return response()->json(['message' => 'You are not authorized to update this announcement'], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'price' => 'sometimes|required|numeric|min:0',
                'address' => 'sometimes|required|string|max:255',
                'phone' => 'sometimes|required|string|max:20',
                'email' => 'sometimes|required|email|max:255',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'is_active' => 'sometimes|required|in:0,1,true,false',
                'pourcentage_reduction' => 'nullable|integer|min:0|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Handle image uploads if new images are provided
            if ($request->hasFile('images')) {
                $imagePaths = [];

                // Get existing images
                $existingImages = $annonce->images ?? [];

                // Add new images
                foreach ($request->file('images') as $image) {
                    $path = $image->store('annonces', 'public');
                    $imagePaths[] = '/storage/' . $path;
                }

                // Merge with existing images if keep_images is true
                if ($request->has('keep_images') && $request->keep_images) {
                    $imagePaths = array_merge($existingImages, $imagePaths);
                }

                $annonce->images = $imagePaths;
            }

            // Update other fields
            if ($request->has('title')) $annonce->title = $request->title;
            if ($request->has('description')) $annonce->description = $request->description;
            if ($request->has('price')) $annonce->price = $request->price;
            if ($request->has('address')) $annonce->address = $request->address;
            if ($request->has('phone')) $annonce->phone = $request->phone;
            if ($request->has('email')) $annonce->email = $request->email;

            if ($request->has('is_active')) {
                $annonce->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            }

            if ($request->has('pourcentage_reduction')) {
                $pourcentageReduction = max(0, min(20, intval($request->pourcentage_reduction)));
                $annonce->pourcentage_reduction = $pourcentageReduction;
            }

            $annonce->save();

            return response()->json($annonce);
        } catch (\Exception $e) {
            Log::error('Error updating annonce: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while updating the announcement'], 500);
        }
    }

    /**
     * Toggle the active status of an annonce.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $annonce = Annonce::findOrFail($id);

            // Check if current user owns this annonce
            if ($annonce->user_id !== Auth::id() && Auth::user()->role_id !== 1) {
                return response()->json(['message' => 'You are not authorized to update this announcement'], 403);
            }

            // If is_active is provided, use it; otherwise toggle the current value
            if ($request->has('is_active')) {
                $annonce->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            } else {
                $annonce->is_active = !$annonce->is_active;
            }

            $annonce->save();

            return response()->json($annonce);
        } catch (\Exception $e) {
            Log::error('Error toggling annonce status: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while toggling the announcement status'], 500);
        }
    }

    /**
     * Remove the specified annonce.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $annonce = Annonce::findOrFail($id);

            // Check if current user owns this annonce
            if ($annonce->user_id !== Auth::id() && Auth::user()->role_id !== 1) {
                return response()->json(['message' => 'You are not authorized to delete this announcement'], 403);
            }

            // Delete associated images
            if (!empty($annonce->images)) {
                foreach ($annonce->images as $image) {
                    // Remove /storage/ from the path
                    $path = str_replace('/storage/', '', $image);
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            $annonce->delete();

            return response()->json(['message' => 'Announcement deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting annonce: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while deleting the announcement'], 500);
        }
    }
}
