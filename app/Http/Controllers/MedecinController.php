<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class MedecinController extends Controller
{
    /**
     * Get all medecins with their profiles
     */
    public function index(Request $request)
    {
        $doctors = User::query()
            ->with(['role'])
            ->whereHas('role', fn($q) => $q->where('name', 'medecin'))
            ->leftJoin('medecin_profiles as mp', 'mp.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'mp.specialty',
                'mp.disponible',
                'mp.adresse',
            ])
            ->get()
            ->map(function ($row) {
                $specialtyStr = $row->specialty ?? '';
                $specialties = collect(preg_split('/[,;|]/', $specialtyStr))
                    ->map(fn($s) => trim($s))
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'specialty' => $specialtyStr,
                    'specialties' => $specialties,
                    'rating' => 4.6, // placeholder until ratings exist
                    'experience' => null,
                    'location' => $row->adresse,
                    'available' => (bool)$row->disponible,
                    'nextSlot' => null,
                    'profile' => [
                        'consultationFee' => null,
                        'description' => null,
                    ],
                ];
            });

        return response()->json($doctors);
    }

    public function show($id)
    {
        $doctor = User::query()
            ->with(['role'])
            ->where('users.id', $id)
            ->whereHas('role', fn($q) => $q->where('name', 'medecin'))
            ->leftJoin('medecin_profiles as mp', 'mp.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'mp.specialty',
                'mp.disponible',
                'mp.adresse',
                'mp.horaires',
                'mp.experience_years',
            ])
            ->firstOrFail();

        $specialtyStr = $doctor->specialty ?? '';
        $specialties = collect(preg_split('/[,;|]/', $specialtyStr))
            ->map(fn($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'id' => $doctor->id,
            'name' => $doctor->name,
            'specialty' => $specialtyStr,
            'specialties' => $specialties,
            'rating' => 4.6,
            'experience' => $doctor->experience_years ? "{$doctor->experience_years} ans d'expérience" : null,
            'location' => $doctor->adresse,
            'available' => (bool)$doctor->disponible,
            'nextSlot' => null,
            'profile' => [
                'consultationFee' => null,
                'description' => null,
            ],
            'schedule' => $doctor->horaires ? [ $doctor->horaires ] : [],
            'bio' => null,
        ]);
    }
}
