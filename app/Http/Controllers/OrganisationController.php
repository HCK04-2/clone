<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;

class OrganisationController extends Controller
{
    /**
     * Get all organizations
     */
    public function index(Request $request)
    {
        // For simplicity, return mock data
        return response()->json([
            [
                'id' => 1,
                'name' => 'Clinique Internationale',
                'type' => 'clinique',
                'location' => 'Casablanca Centre',
                'rating' => 4.6,
                'available' => true,
                'description' => 'Clinique multi-spécialités avec équipements modernes et personnel qualifié.',
                'specialties' => ['Chirurgie', 'Cardiologie', 'Pédiatrie', 'Orthopédie']
            ],
            [
                'id' => 2,
                'name' => 'Pharmacie Centrale',
                'type' => 'pharmacie',
                'location' => 'Casablanca Maarif',
                'rating' => 4.5,
                'available' => true,
                'description' => 'Pharmacie offrant une large gamme de médicaments et produits de santé.',
                'specialties' => ['Médicaments', 'Parapharmacie', 'Conseil santé']
            ],
            [
                'id' => 3,
                'name' => 'Centre d\'Imagerie Médicale',
                'type' => 'centre_radiologie',
                'location' => 'Rabat',
                'rating' => 4.8,
                'available' => false,
                'description' => 'Centre d\'imagerie de pointe proposant IRM, scanner et radiographie.',
                'specialties' => ['IRM', 'Scanner', 'Radiographie', 'Échographie']
            ]
        ]);
    }
}
