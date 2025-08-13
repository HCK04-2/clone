<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\PatientProfile;
use App\Models\MedecinProfile;
use App\Models\KineProfile;
use App\Models\OrthophonisteProfile;
use App\Models\PsychologueProfile;
use App\Models\CliniqueProfile;
use App\Models\PharmacieProfile;
use App\Models\ParapharmacieProfile;
use App\Models\LaboAnalyseProfile;
use App\Models\CentreRadiologieProfile;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validate common fields - REMOVE username requirement
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'email' => 'required|string|email|max:100|unique:users',
                'password' => 'required|string|min:8',
                'phone' => 'required|string|max:20',
                'role_id' => 'required|exists:roles,id'
                // Remove any username validation here
            ]);

            // Create user without username field
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'phone' => $validated['phone'],
                'role_id' => $validated['role_id'],
                'is_verified' => 0,
                'is_subscribed' => 0
            ]);

            $role = Role::find($validated['role_id']);

            if (!$role) {
                throw new \Exception('Role not found');
            }

            // Handle profile creation based on role
            switch ($role->name) {
                case 'patient':
                    // Create patient profile
                    PatientProfile::create([
                        'user_id' => $user->id,
                        'age' => $request->age,
                        'gender' => $request->gender,
                        'blood_type' => $request->blood_type,
                        'allergies' => $request->allergies ? json_encode($request->allergies) : null,
                        'chronic_diseases' => $request->chronic_diseases ? json_encode($request->chronic_diseases) : null,
                        'missed_rdv' => 0
                    ]);
                    break;

                case 'medecin':
                    // Handle file uploads
                    $diplomaPaths = [];
                    if ($request->hasFile('diplomas')) {
                        foreach ($request->file('diplomas') as $file) {
                            $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                            $path = $file->storeAs('public/diplomas', $filename);
                            $diplomaPaths[] = str_replace('public/', '', $path);
                        }
                    }

                    // Process specialty data for medecin
                    $specialtyData = [];
                    if ($request->has('specialty')) {
                        $specialtyData = json_decode($request->specialty, true);
                    }
                    $specialtyString = !empty($specialtyData) ? implode(', ', $specialtyData) : null;

                    MedecinProfile::create([
                        'user_id' => $user->id,
                        'specialty' => $specialtyString,
                        'experience_years' => $request->experience_years,
                        'horaires' => json_encode([
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ]),
                        'diplomas' => json_encode($diplomaPaths),
                        'adresse' => $request->adresse,
                        'disponible' => true
                    ]);
                    break;

                case 'kine':
                case 'orthophoniste':
                case 'psychologue':
                    // Handle file uploads
                    $diplomaPaths = [];
                    if ($request->hasFile('diplomas')) {
                        foreach ($request->file('diplomas') as $file) {
                            $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                            $path = $file->storeAs('public/diplomas', $filename);
                            $diplomaPaths[] = str_replace('public/', '', $path);
                        }
                    }

                    // Process specialty data
                    $specialtyData = [];
                    if ($request->has('specialty')) {
                        $specialtyData = json_decode($request->specialty, true);

                        // Check for "Autres" option and replace with custom specialty
                        if (is_array($specialtyData) && in_array("Autres", $specialtyData) && $request->has('other_specialty')) {
                            $specialtyData = array_filter($specialtyData, function($item) {
                                return $item !== "Autres";
                            });
                            $specialtyData[] = $request->other_specialty;
                        }
                    }

                    $specialtyString = !empty($specialtyData) ? implode(', ', $specialtyData) : null;

                    // Get the appropriate model class
                    $modelClass = 'App\\Models\\' . ucfirst($role->name) . 'Profile';

                    // Create the profile with complete data
                    $modelClass::create([
                        'user_id' => $user->id,
                        'specialty' => $specialtyString,
                        'experience_years' => $request->experience_years,
                        'horaires' => json_encode([
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ]),
                        'diplomas' => json_encode($diplomaPaths),
                        'adresse' => $request->adresse,
                        'disponible' => true
                    ]);
                    break;

                case 'clinique':
                    // Process services data
                    $servicesData = [];
                    if ($request->has('services')) {
                        $servicesData = json_decode($request->services, true);
                        
                        // Handle "Autres" option with custom service
                        if (is_array($servicesData) && in_array("Autres", $servicesData) && $request->has('other_service')) {
                            // Remove "Autres" from the array
                            $servicesData = array_filter($servicesData, function($item) {
                                return $item !== "Autres";
                            });
                            // Add the custom service
                            $servicesData[] = $request->other_service;
                        }
                    }

                    CliniqueProfile::create([
                        'user_id' => $user->id,
                        'nom_clinique' => $request->nom_etablissement,
                        'adresse' => $request->adresse,
                        'localisation' => $request->localisation,
                        'horaires' => json_encode([
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ]),
                        'nbr_personnel' => $request->nbr_personnel,
                        'gerant_name' => $request->gerant_name,
                        'services' => $servicesData,
                        'disponible' => true
                    ]);
                    break;

                case 'pharmacie':
                case 'parapharmacie':
                case 'labo_analyse':
                case 'centre_radiologie':
                    $modelClass = 'App\\Models\\' . str_replace('_', '', ucfirst($role->name)) . 'Profile';

                    // Process services data
                    $servicesData = [];
                    if ($request->has('services')) {
                        $servicesData = json_decode($request->services, true);
                        
                        // Handle "Autres" option with custom service
                        if (is_array($servicesData) && in_array("Autres", $servicesData) && $request->has('other_service')) {
                            // Remove "Autres" from the array
                            $servicesData = array_filter($servicesData, function($item) {
                                return $item !== "Autres";
                            });
                            // Add the custom service
                            $servicesData[] = $request->other_service;
                        }
                    }

                    $profileData = [
                        'user_id' => $user->id,
                        'adresse' => $request->adresse,
                        'disponible' => true
                    ];

                    // Handle organization-specific fields
                    if ($role->name === 'labo_analyse') {
                        $profileData['nom_labo'] = $request->nom_etablissement;
                        $profileData['gerant_name'] = $request->gerant_name;
                        $profileData['services'] = $servicesData; // REMOVE json_encode
                        $profileData['horaires'] = [
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ];
                    } elseif ($role->name === 'centre_radiologie') {
                        $profileData['nom_centre'] = $request->nom_etablissement;
                        $profileData['gerant_name'] = $request->gerant_name;
                        $profileData['services'] = $servicesData; // REMOVE json_encode
                        $profileData['horaires'] = [
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ];
                    } elseif ($role->name === 'pharmacie') {
                        $profileData['nom_pharmacie'] = $request->nom_etablissement;
                        $profileData['gerant_name'] = $request->gerant_name;
                        $profileData['horaires'] = [
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ];
                    } elseif ($role->name === 'parapharmacie') {
                        $profileData['nom_parapharmacie'] = $request->nom_etablissement;
                        $profileData['gerant_name'] = $request->gerant_name;
                        $profileData['horaires'] = [
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ];
                    }

                    $modelClass::create($profileData);
                    break;
            }

            DB::commit();

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'token' => $token,
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration error: ' . $e->getMessage());

            if (isset($user)) {
                $user->delete();
            }

            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
