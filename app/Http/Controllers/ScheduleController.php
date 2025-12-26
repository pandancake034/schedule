<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon; // Nodig voor datum berekeningen

class ScheduleController extends Controller
{
    // ==========================================
    // 1. PAGINA WEERGAVES (VIEWS)
    // ==========================================

    /**
     * Toont het dashboard met de kalender.
     */
    public function index() {
        return view('dashboard');
    }

    /**
     * Toont de lijst met teamleden.
     */
    public function team() {
        $users = User::all();
        return view('team', compact('users'));
    }

    /**
     * Toont de admin pagina voor instellingen en genereren.
     */
    public function admin() {
        return view('admin');
    }

    // ==========================================
    // 2. API (VOOR DE KALENDER)
    // ==========================================

    /**
     * Stuurt JSON data terug naar FullCalendar.
     */
    public function getEvents() {
        // Haal rooster op en koppel de naam van de gebruiker eraan
        $schedules = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->select('schedules.date', 'schedules.shift_type', 'users.name')
            ->get();

        // Format data voor de frontend
        $events = $schedules->map(function($row) {
            // Blauw voor Ochtend, Groen voor Middag
            $color = ($row->shift_type == 'AM') ? '#0070d2' : '#04844b';
            
            return [
                'title' => $row->name . ' (' . $row->shift_type . ')',
                'start' => $row->date,
                'color' => $color
            ];
        });

        return response()->json($events);
    }

    // ==========================================
    // 3. ACTIES (OPSLAAN & GENEREREN)
    // ==========================================

    /**
     * Maakt een nieuwe gebruiker aan + stelt basis beschikbaarheid in.
     */
    public function storeUser(Request $request) {
        // 1. Validatie (inclusief nieuwe velden)
        $request->validate([
            'name' => 'required', 
            'email' => 'required|email|unique:users,email',
            'contract_days' => 'required|integer|min:1|max:7', // <--- Nieuw
            'contract_hours' => 'required|integer|min:1',      // <--- Nieuw
            'shift_preference' => 'required' 
        ]);
        
        // 2. Maak de gebruiker aan met de contractgegevens
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contract_days' => $request->contract_days,   // <--- Opslaan
            'contract_hours' => $request->contract_hours, // <--- Opslaan
            'password' => bcrypt('welkom123')
        ]);

        // 3. Maak beschikbaarheid aan (rest blijft hetzelfde)
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        foreach($days as $day) {
            DB::table('availabilities')->insert([
                'user_id' => $user->id,
                'day_of_week' => $day,
                'shift_preference' => $request->shift_preference,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return redirect('/nieuwegein/team')->with('success', 'Collega ' . $user->name . ' succesvol toegevoegd!');
    }

    /**
     * HET ALGORITME: Vult het rooster automatisch.
     */
    public function generateSchedule(Request $request) {
        $startDate = Carbon::parse($request->start_date);
        
        // Loop door de komende 7 dagen (van gekozen startdatum)
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayNameEnglish = $currentDate->format('l'); // Bijv: 'Monday' of 'Saturday'

            // REGEL 1: BEZETTING BEPALEN
            // Do & Vr = 1 persoon. De rest (Za, Zo, Ma, Di, Wo) = 2 personen.
            if ($dayNameEnglish == 'Thursday' || $dayNameEnglish == 'Friday') {
                $neededPerShift = 1;
            } else {
                $neededPerShift = 2;
            }

            // We vullen zowel de Ochtend (AM) als Middag (PM) shift
            foreach(['AM', 'PM'] as $shift) {
                
                // 1. Tel hoeveel mensen er al staan (handmatig of eerder run)
                $currentCount = DB::table('schedules')
                    ->where('date', $currentDate->format('Y-m-d'))
                    ->where('shift_type', $shift)
                    ->count();

                // 2. Hoeveel plekken zijn er nog open?
                $slotsToFill = $neededPerShift - $currentCount;

                if ($slotsToFill > 0) {
                    // 3. Zoek geschikte kandidaten
                    $candidates = User::whereHas('availability', function($query) use ($dayNameEnglish, $shift) {
                        // Moet beschikbaar zijn op deze dag EN (voorkeur voor deze shift OF flexibel)
                        $query->where('day_of_week', $dayNameEnglish)
                              ->whereIn('shift_preference', [$shift, 'BOTH']);
                    })
                    // REGEL 2: Maximaal 5 werkdagen per week (garantie op 2 dagen vrij)
                    ->withCount(['schedules' => function($query) use ($startDate) {
                        $query->whereBetween('date', [$startDate, $startDate->copy()->addDays(6)]);
                    }])
                    ->having('schedules_count', '<', 5) 
                    ->inRandomOrder() // Husselen voor eerlijke verdeling
                    ->take($slotsToFill) // Pak zoveel mensen als we nodig hebben
                    ->get();

                    // 4. Inplannen
                    foreach($candidates as $candidate) {
                        // Check: Werkt deze persoon vandaag al? (Voorkom dubbele dienst op 1 dag)
                        $alreadyWorkingToday = DB::table('schedules')
                            ->where('user_id', $candidate->id)
                            ->where('date', $currentDate->format('Y-m-d'))
                            ->exists();

                        if (!$alreadyWorkingToday) {
                            DB::table('schedules')->insert([
                                'date' => $currentDate->format('Y-m-d'),
                                'shift_type' => $shift,
                                'user_id' => $candidate->id,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }
                }
            }
        }

        return redirect('/nieuwegein/schedule')->with('success', 'Rooster succesvol gegenereerd!');
    }
}

// ==========================================
    // 4. CRUD ACTIES (EDIT & DELETE)
    // ==========================================

    /**
     * Toon het bewerk formulier voor een specifieke gebruiker.
     */
    public function editUser($id) {
        $user = User::findOrFail($id);
        
        // Haal de huidige voorkeur op (we pakken er eentje, aangezien ze bij aanmaken allemaal hetzelfde zijn)
        $currentAvailability = $user->availability()->first();
        $currentPreference = $currentAvailability ? $currentAvailability->shift_preference : 'BOTH';

        return view('team_edit', compact('user', 'currentPreference'));
    }

    /**
     * Update de gebruiker in de database.
     */
    public function updateUser(Request $request, $id) {
        $user = User::findOrFail($id);

        // 1. Validatie
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$user->id, // Negeer eigen email bij check
            'contract_days' => 'required|integer|min:1|max:7',
            'contract_hours' => 'required|integer|min:1',
            'shift_preference' => 'required'
        ]);

        // 2. Update User tabel
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'contract_days' => $request->contract_days,
            'contract_hours' => $request->contract_hours,
        ]);

        // 3. Update Beschikbaarheid (alle dagen updaten naar nieuwe voorkeur)
        Availability::where('user_id', $user->id)
            ->update(['shift_preference' => $request->shift_preference]);

        return redirect('/nieuwegein/team')->with('success', 'Gegevens van ' . $user->name . ' bijgewerkt!');
    }

    /**
     * Verwijder een gebruiker.
     */
    public function deleteUser($id) {
        $user = User::findOrFail($id);
        $user->delete(); // Door 'cascade' in de database worden availability en schedules ook verwijderd.

        return redirect('/nieuwegein/team')->with('success', 'Collega verwijderd.');
    }
