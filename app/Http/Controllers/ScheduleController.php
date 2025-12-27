<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Availability;
use Carbon\Carbon;

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
        $schedules = DB::table('schedules')
            ->join('users', 'schedules.user_id', '=', 'users.id')
            ->select('schedules.date', 'schedules.shift_type', 'users.name')
            ->get();

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
    // 3. ACTIES (OPSLAAN, GENEREREN & WISSEN)
    // ==========================================

    /**
     * Maakt een nieuwe gebruiker aan (inclusief vaste dagen).
     */
    public function storeUser(Request $request) {
        $request->validate([
            'name' => 'required', 
            'email' => 'required|email|unique:users,email',
            'contract_days' => 'required|integer|min:1|max:7',
            'contract_hours' => 'required|integer|min:1',
            'shift_preference' => 'required',
            'fixed_days' => 'array' // Mag leeg zijn, maar moet array zijn
        ]);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contract_days' => $request->contract_days,
            'contract_hours' => $request->contract_hours,
            'fixed_days' => $request->fixed_days, // Opslaan vaste dagen
            'password' => bcrypt('welkom123')
        ]);

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
     * HET ALGORITME (Vernieuwd met vaste dagen & contract regels).
     */
    public function generateSchedule(Request $request) {
        $startDate = Carbon::parse($request->start_date);
        
        // Loop 7 dagen
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayNameEnglish = $currentDate->format('l'); 

            // STAP 1: BEZETTING BEPALEN
            // Do & Vr = GEEN bezorgdagen (dus doel is 0), tenzij vaste mensen.
            // Overige dagen = 2 personen per shift.
            if ($dayNameEnglish == 'Thursday' || $dayNameEnglish == 'Friday') {
                $neededPerShift = 0; 
            } else {
                $neededPerShift = 2;
            }

            foreach(['AM', 'PM'] as $shift) {
                
                // STAP 2: EERST DE "VASTE DAGEN" MENSEN INPLANNEN
                // We zoeken iedereen die deze dag als vaste dag heeft
                $fixedUsers = User::whereJsonContains('fixed_days', $dayNameEnglish)->get();

                foreach($fixedUsers as $fixedUser) {
                    // Check voorkeur (respecteer AM/PM voorkeur)
                    $pref = $fixedUser->availability()->where('day_of_week', $dayNameEnglish)->first()->shift_preference ?? 'BOTH';
                    
                    if ($pref == 'BOTH' || $pref == $shift) {
                        // Check dubbel
                        $exists = DB::table('schedules')
                            ->where('user_id', $fixedUser->id)
                            ->where('date', $currentDate->format('Y-m-d'))
                            ->exists();
                        
                        if (!$exists) {
                            DB::table('schedules')->insert([
                                'date' => $currentDate->format('Y-m-d'),
                                'shift_type' => $shift,
                                'user_id' => $fixedUser->id,
                                'created_at' => now(), 'updated_at' => now()
                            ]);
                        }
                    }
                }

                // STAP 3: AANVULLEN MET OVERIGE MENSEN (Alleen als needed > 0)
                $currentCount = DB::table('schedules')
                    ->where('date', $currentDate->format('Y-m-d'))
                    ->where('shift_type', $shift)
                    ->count();

                $slotsToFill = $neededPerShift - $currentCount;

                if ($slotsToFill > 0) {
                    $candidates = User::whereHas('availability', function($query) use ($dayNameEnglish, $shift) {
                        $query->where('day_of_week', $dayNameEnglish)
                              ->whereIn('shift_preference', [$shift, 'BOTH']);
                    })
                    // REGEL: Check of ze hun CONTRACT DAGEN nog niet bereikt hebben in deze week
                    ->withCount(['schedules' => function($query) use ($startDate) {
                        $query->whereBetween('date', [$startDate, $startDate->copy()->addDays(6)]);
                    }])
                    ->get() // Eerst ophalen
                    ->filter(function ($user) {
                        // Alleen mensen die nog dagen 'over' hebben in hun contract
                        return $user->schedules_count < $user->contract_days;
                    })
                    ->shuffle() // Husselen
                    ->take($slotsToFill);

                    foreach($candidates as $candidate) {
                        $alreadyWorkingToday = DB::table('schedules')
                            ->where('user_id', $candidate->id)
                            ->where('date', $currentDate->format('Y-m-d'))
                            ->exists();

                        if (!$alreadyWorkingToday) {
                            DB::table('schedules')->insert([
                                'date' => $currentDate->format('Y-m-d'),
                                'shift_type' => $shift,
                                'user_id' => $candidate->id,
                                'created_at' => now(), 'updated_at' => now()
                            ]);
                        }
                    }
                }
            }
        }

        return redirect('/nieuwegein/schedule')->with('success', 'Rooster gegenereerd op basis van contracturen en vaste dagen!');
    }

    /**
     * Wist alle diensten uit de database (reset het rooster).
     */
    public function clearSchedule() {
        DB::table('schedules')->truncate();
        return redirect('/nieuwegein/admin')->with('success', 'Het volledige rooster is gewist!');
    }

    // ==========================================
    // 4. CRUD ACTIES (EDIT & DELETE)
    // ==========================================

    /**
     * Toon het bewerk formulier voor een specifieke gebruiker.
     */
    public function editUser($id) {
        $user = User::findOrFail($id);
        
        // Haal de huidige voorkeur op
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
            'email' => 'required|email|unique:users,email,'.$user->id,
            'contract_days' => 'required|integer|min:1|max:7',
            'contract_hours' => 'required|integer|min:1',
            'shift_preference' => 'required',
            'fixed_days' => 'array'
        ]);

        // 2. Update User tabel
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'contract_days' => $request->contract_days,
            'contract_hours' => $request->contract_hours,
            'fixed_days' => $request->fixed_days, // Updaten vaste dagen
        ]);

        // 3. Update Beschikbaarheid
        Availability::where('user_id', $user->id)
            ->update(['shift_preference' => $request->shift_preference]);

        return redirect('/nieuwegein/team')->with('success', 'Gegevens van ' . $user->name . ' bijgewerkt!');
    }

    /**
     * Verwijder een gebruiker.
     */
    public function deleteUser($id) {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect('/nieuwegein/team')->with('success', 'Collega verwijderd.');
    }
}